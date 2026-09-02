<?php
/**
 * Provider request-ID redaction regression tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Security;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\OpenAI\OpenAiProvider;
use WpRagAiChatbot\Providers\OpenRouter\OpenRouterProvider;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Tests\Support\Providers\Http\QueuedHttpTransport;

/**
 * Ensures provider-controlled diagnostic IDs cannot publish configured credentials.
 */
final class ProviderRequestIdRedactionTest extends TestCase {
	/**
	 * OpenAI success and error request IDs containing the credential are discarded.
	 */
	public function test_openai_request_ids_never_expose_configured_secret(): void {
		$secret = 'openai-request-id-secret';

		$success_transport = new QueuedHttpTransport(
			array(
				new HttpResponse(
					200,
					array( 'x-request-id' => 'req-' . $secret ),
					'{"model":"gpt-test","status":"completed","output":[{"content":[{"type":"output_text","text":"ok"}]}]}'
				),
			)
		);
		$result = $this->openai( $success_transport, $secret )->generate( new GenerationRequest( 'gpt-test', 'Hello' ) );
		self::assertNull( $result->request_id );

		$error_transport = new QueuedHttpTransport(
			array( new HttpResponse( 500, array( 'x-request-id' => 'req-' . $secret ), '{"error":"failed"}' ) )
		);

		try {
			$this->openai( $error_transport, $secret )->generate( new GenerationRequest( 'gpt-test', 'Hello' ) );
			self::fail( 'Expected OpenAI provider failure.' );
		} catch ( ProviderException $exception ) {
			self::assertNull( $exception->request_id );
		}
	}

	/**
	 * OpenRouter header and top-level fallback request IDs containing the credential are discarded.
	 */
	public function test_openrouter_request_ids_never_expose_configured_secret(): void {
		$secret = 'openrouter-request-id-secret';

		$success_transport = new QueuedHttpTransport(
			array(
				new HttpResponse(
					200,
					array(),
					'{"id":"gen-' . $secret . '","model":"router/test","choices":[{"message":{"content":"ok"},"finish_reason":"stop"}]}'
				),
			)
		);
		$result = $this->openrouter( $success_transport, $secret )->generate( new GenerationRequest( 'router/test', 'Hello' ) );
		self::assertNull( $result->request_id );

		$error_transport = new QueuedHttpTransport(
			array( new HttpResponse( 500, array( 'x-request-id' => 'req-' . $secret ), '{"error":"failed"}' ) )
		);

		try {
			$this->openrouter( $error_transport, $secret )->generate( new GenerationRequest( 'router/test', 'Hello' ) );
			self::fail( 'Expected OpenRouter provider failure.' );
		} catch ( ProviderException $exception ) {
			self::assertNull( $exception->request_id );
		}
	}

	/**
	 * Build OpenAI around deterministic credential and transport boundaries.
	 *
	 * @param QueuedHttpTransport $transport Fake transport.
	 * @param string              $secret Configured credential.
	 */
	private function openai( QueuedHttpTransport $transport, string $secret ): OpenAiProvider {
		return new OpenAiProvider(
			$this->resolver( $secret ),
			new ProviderHttpClient( $transport ),
			new SecretRedactor()
		);
	}

	/**
	 * Build OpenRouter around deterministic credential and transport boundaries.
	 *
	 * @param QueuedHttpTransport $transport Fake transport.
	 * @param string              $secret Configured credential.
	 */
	private function openrouter( QueuedHttpTransport $transport, string $secret ): OpenRouterProvider {
		return new OpenRouterProvider(
			$this->resolver( $secret ),
			new ProviderHttpClient( $transport ),
			new SecretRedactor()
		);
	}

	/**
	 * Resolve one deterministic environment credential.
	 *
	 * @param string $secret Credential value.
	 */
	private function resolver( string $secret ): CredentialResolver {
		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( $secret );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( null );

		return new CredentialResolver( $reader, $store );
	}
}
