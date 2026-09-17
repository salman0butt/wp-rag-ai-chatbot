<?php
/**
 * OpenAI-compatible embedding adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\OpenAiCompatible\OpenAiCompatibleChatProvider;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Tests\Support\Providers\Http\QueuedHttpTransport;

/**
 * Verifies Gemini's OpenAI-compatible embedding boundary.
 */
final class OpenAiCompatibleEmbeddingTest extends TestCase {
	/** Gemini embeddings use the fixed endpoint, exact request body, and identity. */
	public function test_embed_uses_gemini_endpoint_body_and_provider_identity(): void {
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse(
					200,
					array(),
					'{"data":[{"index":0,"embedding":[0.1,0.2]},{"index":1,"embedding":[0.3,0.4]}],"model":"gemini-embedding-001","usage":{"prompt_tokens":7}}'
				),
			)
		);
		$provider  = $this->provider( $transport );

		self::assertInstanceOf( EmbeddingProvider::class, $provider );
		$result = $provider->embed( new EmbeddingRequest( 'gemini-embedding-001', array( 'one', 'two' ), 2 ) );

		$request = $transport->requests[0];
		self::assertSame( 'POST', $request->method );
		self::assertSame( 'https://generativelanguage.googleapis.com/v1beta/openai/embeddings', $request->url );
		self::assertSame( 'Bearer gemini-secret', $request->headers['Authorization'] );
		self::assertSame(
			array(
				'model'      => 'gemini-embedding-001',
				'input'      => array( 'one', 'two' ),
				'dimensions' => 2,
			),
			$request->json_body
		);
		self::assertSame( ProviderIds::GEMINI_DIRECT, $result->provider_id );
		self::assertSame( 7, $result->usage->input_tokens );
	}

	/** Embedding vectors retain the response order and reject malformed vectors. */
	public function test_embed_preserves_response_order_and_rejects_malformed_vectors(): void {
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse( 200, array(), '{"data":[{"index":1,"embedding":[0.3]},{"index":0,"embedding":[0.1]}]}' ),
				new HttpResponse( 200, array(), '{"data":[{"index":0,"embedding":["not-a-number"]}]}' ),
			)
		);
		$provider  = $this->provider( $transport );

		$result = $provider->embed( new EmbeddingRequest( 'gemini-embedding-001', array( 'one', 'two' ) ) );
		self::assertSame( array( 1, 0 ), array_map( static fn ( $vector ): int => $vector->index, $result->vectors ) );

		try {
			$provider->embed( new EmbeddingRequest( 'gemini-embedding-001', array( 'one' ) ) );
			self::fail( 'Expected malformed embedding response.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::MALFORMED_RESPONSE, $exception->error_code );
		}
	}

	/** Gemini's documented embedding endpoint accepts a single input as a string. */
	public function test_gemini_single_input_uses_scalar_request_body(): void {
		$transport = new QueuedHttpTransport(
			array( new HttpResponse( 200, array(), '{"data":[{"index":0,"embedding":[0.1]}]}' ) )
		);
		$this->provider( $transport )->embed( new EmbeddingRequest( 'gemini-embedding-001', array( 'one' ) ) );

		self::assertSame(
			array(
				'model' => 'gemini-embedding-001',
				'input' => 'one',
			),
			$transport->requests[0]->json_body
		);
	}

	/** Gemini may omit OpenAI's optional response index for a single embedding. */
	public function test_embedding_response_defaults_missing_index_to_response_order(): void {
		$transport = new QueuedHttpTransport(
			array( new HttpResponse( 200, array(), '{"data":[{"embedding":[0.1]}]}' ) )
		);

		$result = $this->provider( $transport )->embed( new EmbeddingRequest( 'gemini-embedding-001', array( 'one' ) ) );

		self::assertSame( 0, $result->vectors[0]->index );
	}

	/** Upstream embedding errors redact the configured Gemini secret. */
	public function test_embed_redacts_authorization_secret_from_upstream_failure(): void {
		$secret    = 'gemini-secret-value';
		$transport = new QueuedHttpTransport(
			array( new HttpResponse( 500, array(), '{"error":"' . $secret . ' leaked"}' ) )
		);

		try {
			$this->provider( $transport, $secret )->embed( new EmbeddingRequest( 'gemini-embedding-001', array( 'one' ) ) );
			self::fail( 'Expected upstream embedding failure.' );
		} catch ( ProviderException $exception ) {
			self::assertStringNotContainsString( $secret, $exception->getMessage() );
			self::assertStringContainsString( '[REDACTED]', $exception->getMessage() );
		}
	}

	/**
	 * Build one compatible provider around deterministic credential and HTTP boundaries.
	 *
	 * @param QueuedHttpTransport $transport Deterministic transport.
	 * @param string              $credential Provider API key.
	 */
	private function provider( QueuedHttpTransport $transport, string $credential = 'gemini-secret' ): OpenAiCompatibleChatProvider {
		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( $credential );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( null );

		return new OpenAiCompatibleChatProvider(
			ProviderIds::GEMINI_DIRECT,
			'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
			'https://generativelanguage.googleapis.com/v1beta/openai/models',
			new CredentialResolver( $reader, $store ),
			new ProviderHttpClient( $transport ),
			new SecretRedactor(),
			'https://generativelanguage.googleapis.com/v1beta/openai/embeddings'
		);
	}
}
