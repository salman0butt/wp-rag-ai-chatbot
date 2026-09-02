<?php
/**
 * OpenAI direct provider adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\OpenAI;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\HttpTransportException;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\OpenAI\OpenAiProvider;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Tests\Support\Providers\Http\QueuedHttpTransport;

/**
 * Verifies fixed OpenAI HTTP shapes and provider-neutral normalization.
 */
final class OpenAiProviderTest extends TestCase {
	/**
	 * Direct OpenAI is runtime-available and local health reflects configuration only.
	 */
	public function test_local_health_never_performs_provider_request(): void {
		$this->require_adapter();
		$configured_transport = new QueuedHttpTransport();
		$configured           = $this->provider( $configured_transport, 'openai-secret' );
		self::assertTrue( $configured->available() );
		self::assertSame( ProviderIds::OPENAI_DIRECT, $configured->provider_id() );
		self::assertSame( ProviderHealthStatus::CONFIGURED, $configured->health()->status );
		self::assertCount( 0, $configured_transport->requests );

		$unconfigured_transport = new QueuedHttpTransport();
		$unconfigured           = $this->provider( $unconfigured_transport, null );
		self::assertTrue( $unconfigured->available() );
		self::assertSame( ProviderHealthStatus::UNCONFIGURED, $unconfigured->health()->status );
		self::assertCount( 0, $unconfigured_transport->requests );
	}

	/**
	 * Generation uses the fixed Responses endpoint and normalizes explicit output data.
	 */
	public function test_generate_sends_fixed_request_and_normalizes_success(): void {
		$this->require_adapter();
		$body      = '{"model":"gpt-5-test","status":"completed","output":[{"content":[{"type":"output_text","text":"Hello "},{"type":"refusal","text":"ignored"}]},{"content":[{"type":"output_text","text":"world"}]}],"usage":{"input_tokens":4,"output_tokens":2,"total_tokens":6}}';
		$transport = new QueuedHttpTransport(
			array( new HttpResponse( 200, array( 'x-request-id' => 'req_openai_1' ), $body ) )
		);
		$provider  = $this->provider( $transport, 'openai-secret' );
		$result    = $provider->generate( new GenerationRequest( 'gpt-requested', 'Say hello', 'Be concise', 64 ) );

		self::assertCount( 1, $transport->requests );
		$request = $transport->requests[0];
		self::assertSame( ProviderIds::OPENAI_DIRECT, $request->provider_id );
		self::assertSame( 'POST', $request->method );
		self::assertSame( 'https://api.openai.com/v1/responses', $request->url );
		self::assertSame( 45, $request->timeout );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'Bearer openai-secret', $request->headers['Authorization'] );
		self::assertSame( 'application/json', $request->headers['Content-Type'] );
		self::assertSame(
			array(
				'model'             => 'gpt-requested',
				'input'             => 'Say hello',
				'instructions'      => 'Be concise',
				'max_output_tokens' => 64,
			),
			$request->json_body
		);
		self::assertSame( ProviderIds::OPENAI_DIRECT, $result->provider_id );
		self::assertSame( 'gpt-5-test', $result->model_id );
		self::assertSame( 'Hello world', $result->output_text );
		self::assertSame( GenerationStatus::COMPLETED, $result->status );
		self::assertSame( 4, $result->usage->input_tokens );
		self::assertSame( 2, $result->usage->output_tokens );
		self::assertSame( 6, $result->usage->total_tokens );
		self::assertSame( 'req_openai_1', $result->request_id );
	}

	/**
	 * Empty optional generation fields are omitted and non-numeric usage stays unknown.
	 */
	public function test_generate_omits_empty_optional_fields_and_keeps_unknown_usage_null(): void {
		$this->require_adapter();
		$body      = '{"model":"gpt-test","status":"completed","output":[{"content":[{"type":"output_text","text":"ok"}]}],"usage":{"input_tokens":"4"}}';
		$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array(), $body ) ) );
		$result    = $this->provider( $transport )->generate( new GenerationRequest( 'gpt-test', 'Hello', '   ', null ) );

		self::assertSame( array( 'model' => 'gpt-test', 'input' => 'Hello' ), $transport->requests[0]->json_body );
		self::assertNull( $result->usage->input_tokens );
		self::assertNull( $result->usage->output_tokens );
		self::assertNull( $result->usage->total_tokens );
		self::assertNull( $result->request_id );
	}

	/**
	 * OpenAI completion states map only from explicit provider status values.
	 */
	public function test_generate_maps_explicit_status_values(): void {
		$this->require_adapter();
		$cases = array(
			'incomplete' => GenerationStatus::INCOMPLETE,
			'failed'     => GenerationStatus::FAILED,
			'other'      => GenerationStatus::UNKNOWN,
		);

		foreach ( $cases as $provider_status => $expected ) {
			$body      = sprintf( '{"model":"gpt-test","status":"%s","output":[{"content":[{"type":"output_text","text":"text"}]}]}', $provider_status );
			$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array(), $body ) ) );
			$result    = $this->provider( $transport )->generate( new GenerationRequest( 'gpt-test', 'Hello' ) );
			self::assertSame( $expected, $result->status );
		}
	}

	/**
	 * Invalid JSON and successful responses without output text are malformed.
	 */
	public function test_generate_rejects_malformed_success_payloads(): void {
		$this->require_adapter();
		$bodies = array(
			'{not-json',
			'{"model":"gpt-test","status":"completed","output":[{"content":[{"type":"refusal","text":"no text"}]}]}',
		);

		foreach ( $bodies as $body ) {
			$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array(), $body ) ) );
			try {
				$this->provider( $transport )->generate( new GenerationRequest( 'gpt-test', 'Hello' ) );
				self::fail( 'Expected malformed OpenAI response.' );
			} catch ( ProviderException $exception ) {
				self::assertSame( ProviderErrorCode::MALFORMED_RESPONSE, $exception->error_code );
			}
		}
	}

	/**
	 * Provider HTTP status failures map to stable provider-neutral errors.
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
				array( new HttpResponse( $status, array( 'x-request-id' => 'req_error' ), '{"error":{"message":"upstream failure"}}' ) )
			);
			try {
				$this->provider( $transport )->generate( new GenerationRequest( 'gpt-test', 'Hello' ) );
				self::fail( 'Expected mapped OpenAI provider error.' );
			} catch ( ProviderException $exception ) {
				self::assertSame( $expected, $exception->error_code );
				self::assertSame( 'req_error', $exception->request_id );
			}
		}
	}

	/**
	 * Transport timeout classification is preserved across the adapter boundary.
	 */
	public function test_generate_preserves_timeout_transport_code(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport(
			array( new HttpTransportException( ProviderErrorCode::TIMEOUT, 'Provider request timed out.' ) )
		);

		try {
			$this->provider( $transport )->generate( new GenerationRequest( 'gpt-test', 'Hello' ) );
			self::fail( 'Expected timeout provider exception.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::TIMEOUT, $exception->error_code );
			self::assertSame( 'Provider request timed out.', $exception->getMessage() );
		}
	}

	/**
	 * Error response bodies are redacted and truncated before exposure.
	 */
	public function test_generate_redacts_and_truncates_upstream_error_body(): void {
		$this->require_adapter();
		$secret    = 'openai-secret-value';
		$error_body = '{"error":{"message":"' . $secret . ' ' . str_repeat( 'x', 2200 ) . '"}}';
		$transport = new QueuedHttpTransport( array( new HttpResponse( 500, array(), $error_body ) ) );

		try {
			$this->provider( $transport, $secret )->generate( new GenerationRequest( 'gpt-test', 'Hello' ) );
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
			$this->provider( $transport, null )->generate( new GenerationRequest( 'gpt-test', 'Hello' ) );
			self::fail( 'Expected configuration provider exception.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::CONFIGURATION, $exception->error_code );
			self::assertCount( 0, $transport->requests );
		}
	}

	/**
	 * Model discovery uses retry policy and keeps unknown capabilities unknown.
	 */
	public function test_models_use_fixed_discovery_request_retry_and_safe_metadata_only(): void {
		$this->require_adapter();
		$body      = '{"data":[{"id":"gpt-a","created":123,"owned_by":"openai","object":"model"},{"id":"gpt-b"}]}';
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse( 503, array(), '{"error":"temporary"}' ),
				new HttpResponse( 200, array(), $body ),
			)
		);
		$models = $this->provider( $transport, 'openai-secret' )->models();

		self::assertCount( 2, $transport->requests );
		foreach ( $transport->requests as $request ) {
			self::assertSame( 'GET', $request->method );
			self::assertSame( 'https://api.openai.com/v1/models', $request->url );
			self::assertSame( 10, $request->timeout );
			self::assertSame( 0, $request->redirection );
			self::assertSame( 'Bearer openai-secret', $request->headers['Authorization'] );
			self::assertNull( $request->json_body );
		}
		self::assertCount( 2, $models );
		self::assertSame( 'gpt-a', $models[0]->model_id );
		self::assertSame( 'gpt-a', $models[0]->display_name );
		self::assertSame( array(), $models[0]->input_modalities );
		self::assertSame( array(), $models[0]->output_modalities );
		self::assertSame( array(), $models[0]->capabilities );
		self::assertNull( $models[0]->context_window );
		self::assertSame( array( 'created' => 123, 'owned_by' => 'openai' ), $models[0]->provider_metadata );
		self::assertSame( array(), $models[1]->provider_metadata );
	}

	/**
	 * Model catalogs reject entries without a non-empty string ID.
	 */
	public function test_models_reject_malformed_items(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport(
			array( new HttpResponse( 200, array(), '{"data":[{"id":""}]}' ) )
		);

		try {
			$this->provider( $transport )->models();
			self::fail( 'Expected malformed model catalog exception.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::MALFORMED_RESPONSE, $exception->error_code );
		}
	}

	/**
	 * Build an OpenAI provider around deterministic credential and HTTP boundaries.
	 *
	 * @param QueuedHttpTransport $transport Deterministic HTTP transport.
	 * @param string|null         $credential Optional resolved credential.
	 */
	private function provider( QueuedHttpTransport $transport, ?string $credential = 'openai-secret' ): OpenAiProvider {
		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( $credential );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( null );

		return new OpenAiProvider(
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
			class_exists( OpenAiProvider::class ),
			'OpenAiProvider must exist before OpenAI adapter behavior can pass.'
		);
	}
}
