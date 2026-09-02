<?php
/**
 * OpenRouter direct provider adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\OpenRouter;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\HttpTransportException;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\OpenRouter\OpenRouterProvider;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Tests\Support\Providers\Http\QueuedHttpTransport;

/**
 * Verifies fixed OpenRouter HTTP shapes and provider-neutral normalization.
 */
final class OpenRouterProviderTest extends TestCase {
	/**
	 * Direct OpenRouter is runtime-available and local health is configuration-only.
	 */
	public function test_local_health_never_performs_provider_request(): void {
		$this->require_adapter();
		$configured_transport = new QueuedHttpTransport();
		$configured           = $this->provider( $configured_transport, 'router-secret' );
		self::assertTrue( $configured->available() );
		self::assertSame( ProviderIds::OPENROUTER_DIRECT, $configured->provider_id() );
		self::assertSame( ProviderHealthStatus::CONFIGURED, $configured->health()->status );
		self::assertCount( 0, $configured_transport->requests );

		$unconfigured_transport = new QueuedHttpTransport();
		$unconfigured           = $this->provider( $unconfigured_transport, null );
		self::assertTrue( $unconfigured->available() );
		self::assertSame( ProviderHealthStatus::UNCONFIGURED, $unconfigured->health()->status );
		self::assertCount( 0, $unconfigured_transport->requests );
	}

	/**
	 * Generation uses fixed chat-completions policy and normalizes explicit fields.
	 */
	public function test_generate_sends_fixed_request_and_normalizes_success(): void {
		$this->require_adapter();
		$body      = '{"id":"gen_router_1","model":"openrouter/actual","choices":[{"message":{"content":"Hello world"},"finish_reason":"stop"}],"usage":{"prompt_tokens":4,"completion_tokens":2,"total_tokens":6}}';
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse(
					200,
					array( 'x-request-id' => 'req_router_1' ),
					$body
				),
			)
		);
		$result = $this->provider( $transport, 'router-secret' )->generate(
			new GenerationRequest( 'openrouter/requested', 'Say hello', 'Be concise', 64 )
		);

		self::assertCount( 1, $transport->requests );
		$request = $transport->requests[0];
		self::assertSame( ProviderIds::OPENROUTER_DIRECT, $request->provider_id );
		self::assertSame( 'POST', $request->method );
		self::assertSame( 'https://openrouter.ai/api/v1/chat/completions', $request->url );
		self::assertSame( 45, $request->timeout );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'Bearer router-secret', $request->headers['Authorization'] );
		self::assertSame( 'application/json', $request->headers['Content-Type'] );
		self::assertSame(
			array(
				'model'      => 'openrouter/requested',
				'messages'   => array(
					array(
						'role'    => 'system',
						'content' => 'Be concise',
					),
					array(
						'role'    => 'user',
						'content' => 'Say hello',
					),
				),
				'max_tokens' => 64,
			),
			$request->json_body
		);
		self::assertSame( ProviderIds::OPENROUTER_DIRECT, $result->provider_id );
		self::assertSame( 'openrouter/actual', $result->model_id );
		self::assertSame( 'Hello world', $result->output_text );
		self::assertSame( GenerationStatus::COMPLETED, $result->status );
		self::assertSame( 4, $result->usage->input_tokens );
		self::assertSame( 2, $result->usage->output_tokens );
		self::assertSame( 6, $result->usage->total_tokens );
		self::assertSame( 'req_router_1', $result->request_id );
	}

	/**
	 * Empty optional fields are omitted and response fallbacks remain explicit.
	 */
	public function test_generate_uses_request_model_and_top_level_id_fallbacks(): void {
		$this->require_adapter();
		$body      = '{"id":"gen_router_fallback","model":"","choices":[{"message":{"content":"partial"},"finish_reason":"length"}],"usage":{"prompt_tokens":"4"}}';
		$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array(), $body ) ) );
		$result    = $this->provider( $transport )->generate( new GenerationRequest( 'openrouter/requested', 'Hello', '   ' ) );

		self::assertSame(
			array(
				'model'    => 'openrouter/requested',
				'messages' => array(
					array(
						'role'    => 'user',
						'content' => 'Hello',
					),
				),
			),
			$transport->requests[0]->json_body
		);
		self::assertSame( 'openrouter/requested', $result->model_id );
		self::assertSame( GenerationStatus::INCOMPLETE, $result->status );
		self::assertNull( $result->usage->input_tokens );
		self::assertNull( $result->usage->output_tokens );
		self::assertNull( $result->usage->total_tokens );
		self::assertSame( 'gen_router_fallback', $result->request_id );
	}

	/**
	 * Unknown finish reasons remain unknown rather than being inferred.
	 */
	public function test_generate_keeps_unknown_finish_reason_unknown(): void {
		$this->require_adapter();
		$body      = '{"choices":[{"message":{"content":"text"},"finish_reason":"tool_calls"}]}';
		$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array(), $body ) ) );
		$result    = $this->provider( $transport )->generate( new GenerationRequest( 'openrouter/test', 'Hello' ) );

		self::assertSame( GenerationStatus::UNKNOWN, $result->status );
	}

	/**
	 * Invalid JSON and successful payloads without text are malformed.
	 */
	public function test_generate_rejects_malformed_success_payloads(): void {
		$this->require_adapter();
		$bodies = array(
			'{not-json',
			'{"choices":[]}',
			'{"choices":[{"message":{"content":12}}]}',
		);

		foreach ( $bodies as $body ) {
			$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array(), $body ) ) );
			try {
				$this->provider( $transport )->generate( new GenerationRequest( 'openrouter/test', 'Hello' ) );
				self::fail( 'Expected malformed OpenRouter response.' );
			} catch ( ProviderException $exception ) {
				self::assertSame( ProviderErrorCode::MALFORMED_RESPONSE, $exception->error_code );
			}
		}
	}

	/**
	 * Provider HTTP failures map to the shared normalized error codes.
	 */
	public function test_generate_maps_http_error_statuses(): void {
		$this->require_adapter();
		$cases = array(
			401 => ProviderErrorCode::AUTHENTICATION,
			403 => ProviderErrorCode::AUTHORIZATION,
			429 => ProviderErrorCode::RATE_LIMIT,
			500 => ProviderErrorCode::UPSTREAM_SERVER,
		);

		foreach ( $cases as $status => $expected ) {
			$transport = new QueuedHttpTransport(
				array(
					new HttpResponse(
						$status,
						array( 'x-request-id' => 'req_router_error' ),
						'{"error":{"message":"upstream failure"}}'
					),
				)
			);
			try {
				$this->provider( $transport )->generate( new GenerationRequest( 'openrouter/test', 'Hello' ) );
				self::fail( 'Expected mapped OpenRouter provider error.' );
			} catch ( ProviderException $exception ) {
				self::assertSame( $expected, $exception->error_code );
				self::assertSame( 'req_router_error', $exception->request_id );
			}
		}
	}

	/**
	 * Transport timeout classification is preserved across the adapter boundary.
	 */
	public function test_generate_preserves_timeout_transport_code(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport(
			array(
				new HttpTransportException( ProviderErrorCode::TIMEOUT, 'Provider request timed out.' ),
			)
		);

		try {
			$this->provider( $transport )->generate( new GenerationRequest( 'openrouter/test', 'Hello' ) );
			self::fail( 'Expected timeout provider exception.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::TIMEOUT, $exception->error_code );
			self::assertSame( 'Provider request timed out.', $exception->getMessage() );
		}
	}

	/**
	 * Error bodies are redacted and truncated before becoming diagnostics.
	 */
	public function test_generate_redacts_and_truncates_upstream_error_body(): void {
		$this->require_adapter();
		$secret     = 'router-secret-value';
		$error_body = '{"error":{"message":"' . $secret . ' ' . str_repeat( 'x', 2200 ) . '"}}';
		$transport  = new QueuedHttpTransport( array( new HttpResponse( 500, array(), $error_body ) ) );

		try {
			$this->provider( $transport, $secret )->generate( new GenerationRequest( 'openrouter/test', 'Hello' ) );
			self::fail( 'Expected upstream provider exception.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::UPSTREAM_SERVER, $exception->error_code );
			self::assertStringNotContainsString( $secret, $exception->getMessage() );
			self::assertStringContainsString( '[REDACTED]', $exception->getMessage() );
			self::assertStringContainsString( '[TRUNCATED]', $exception->getMessage() );
		}
	}

	/**
	 * Missing credentials fail locally without sending a request.
	 */
	public function test_generate_without_credential_fails_before_transport(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport();

		try {
			$this->provider( $transport, null )->generate( new GenerationRequest( 'openrouter/test', 'Hello' ) );
			self::fail( 'Expected configuration provider exception.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::CONFIGURATION, $exception->error_code );
			self::assertCount( 0, $transport->requests );
		}
	}

	/**
	 * Model discovery retries safely and normalizes only explicit metadata.
	 */
	public function test_models_use_fixed_discovery_request_retry_and_explicit_metadata(): void {
		$this->require_adapter();
		$body      = '{"data":[{"id":"router/a","name":"Router Alpha","context_length":128000,"architecture":{"input_modalities":["text"],"output_modalities":["text"]},"supported_parameters":["temperature","max_tokens"]},{"id":"router/b"}]}';
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse( 502, array(), '{"error":"temporary"}' ),
				new HttpResponse( 200, array(), $body ),
			)
		);
		$models = $this->provider( $transport, 'router-secret' )->models();

		self::assertCount( 2, $transport->requests );
		foreach ( $transport->requests as $request ) {
			self::assertSame( 'GET', $request->method );
			self::assertSame( 'https://openrouter.ai/api/v1/models', $request->url );
			self::assertSame( 10, $request->timeout );
			self::assertSame( 0, $request->redirection );
			self::assertSame( 'Bearer router-secret', $request->headers['Authorization'] );
			self::assertNull( $request->json_body );
		}

		self::assertCount( 2, $models );
		self::assertSame( 'router/a', $models[0]->model_id );
		self::assertSame( 'Router Alpha', $models[0]->display_name );
		self::assertSame( array( 'text' ), $models[0]->input_modalities );
		self::assertSame( array( 'text' ), $models[0]->output_modalities );
		self::assertSame( array( 'temperature', 'max_tokens' ), $models[0]->capabilities );
		self::assertSame( 128000, $models[0]->context_window );
		self::assertSame( 'router/b', $models[1]->display_name );
		self::assertSame( array(), $models[1]->input_modalities );
		self::assertSame( array(), $models[1]->output_modalities );
		self::assertSame( array(), $models[1]->capabilities );
		self::assertNull( $models[1]->context_window );
	}

	/**
	 * Model catalogs reject entries without a non-empty string ID.
	 */
	public function test_models_reject_malformed_items(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse( 200, array(), '{"data":[{"id":""}]}' ),
			)
		);

		try {
			$this->provider( $transport )->models();
			self::fail( 'Expected malformed model catalog exception.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::MALFORMED_RESPONSE, $exception->error_code );
		}
	}

	/**
	 * Build an OpenRouter provider around deterministic boundaries.
	 *
	 * @param QueuedHttpTransport $transport Deterministic HTTP transport.
	 * @param string|null         $credential Optional resolved credential.
	 */
	private function provider( QueuedHttpTransport $transport, ?string $credential = 'router-secret' ): OpenRouterProvider {
		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( $credential );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( null );

		return new OpenRouterProvider(
			new CredentialResolver( $reader, $store ),
			new ProviderHttpClient( $transport ),
			new SecretRedactor()
		);
	}

	/**
	 * Require the intended missing-adapter RED before implementation.
	 */
	private function require_adapter(): void {
		self::assertTrue(
			class_exists( OpenRouterProvider::class ),
			'OpenRouterProvider must exist before OpenRouter adapter behavior can pass.'
		);
	}
}
