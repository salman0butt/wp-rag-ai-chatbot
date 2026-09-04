<?php
/**
 * OpenRouter direct embedding adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\OpenRouter;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\OpenRouter\OpenRouterProvider;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Tests\Support\Providers\Http\QueuedHttpTransport;

/**
 * Verifies fixed, one-shot OpenRouter embedding requests and normalized responses.
 */
final class OpenRouterEmbeddingTest extends TestCase {
	/**
	 * Embeddings use the fixed endpoint and preserve provider order.
	 */
	public function test_embed_uses_fixed_endpoint_and_normalizes_ordered_response(): void {
		$body      = '{"object":"list","data":[{"object":"embedding","index":0,"embedding":[0.1,0.2]},{"object":"embedding","index":1,"embedding":[0.3,0.4]}],"model":"provider/embed-model","usage":{"prompt_tokens":9,"total_tokens":9}}';
		$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array( 'x-request-id' => 'or_embed' ), $body ) ) );
		$provider  = $this->provider( $transport );

		self::assertInstanceOf( EmbeddingProvider::class, $provider );
		$result = $provider->embed( new EmbeddingRequest( 'provider/embed-model', array( 'one', 'two' ) ) );

		self::assertCount( 1, $transport->requests );
		$request = $transport->requests[0];
		self::assertSame( 'POST', $request->method );
		self::assertSame( 'https://openrouter.ai/api/v1/embeddings', $request->url );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'Bearer openrouter-secret', $request->headers['Authorization'] );
		self::assertSame(
			array(
				'model' => 'provider/embed-model',
				'input' => array( 'one', 'two' ),
			),
			$request->json_body
		);
		self::assertSame( ProviderIds::OPENROUTER_DIRECT, $result->provider_id );
		self::assertSame( 'provider/embed-model', $result->model );
		self::assertSame( array( 0, 1 ), array_map( static fn ( $vector ): int => $vector->index, $result->vectors ) );
		self::assertSame( 9, $result->usage->input_tokens );
	}

	/**
	 * Optional dimensions are emitted only when the caller requests them.
	 */
	public function test_embed_includes_dimensions_only_when_requested(): void {
		$body      = '{"data":[{"index":0,"embedding":[0.1,0.2]}],"model":"provider/embed-model"}';
		$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array(), $body ) ) );
		$provider  = $this->provider( $transport );
		$result    = $provider->embed( new EmbeddingRequest( 'provider/embed-model', array( 'one' ), 2 ) );

		self::assertSame(
			array(
				'model'      => 'provider/embed-model',
				'input'      => array( 'one' ),
				'dimensions' => 2,
			),
			$transport->requests[0]->json_body
		);
		self::assertFalse( $result->usage->known );
	}

	/**
	 * Billable embedding calls are never retried and upstream errors are redacted.
	 */
	public function test_embed_does_not_retry_billable_http_failure_and_redacts_secret(): void {
		$secret    = 'openrouter-secret-value';
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse( 503, array(), '{"error":{"message":"' . $secret . ' upstream"}}' ),
				new HttpResponse( 200, array(), '{"data":[{"index":0,"embedding":[0.1]}],"model":"provider/embed-model"}' ),
			)
		);

		try {
			$this->provider( $transport, $secret )->embed( new EmbeddingRequest( 'provider/embed-model', array( 'one' ) ) );
			self::fail( 'Expected upstream embedding failure.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::UPSTREAM_SERVER, $exception->error_code );
			self::assertStringNotContainsString( $secret, $exception->getMessage() );
			self::assertCount( 1, $transport->requests );
		}
	}

	/**
	 * Build an OpenRouter provider around deterministic credential and HTTP boundaries.
	 */
	private function provider( QueuedHttpTransport $transport, ?string $credential = 'openrouter-secret' ): OpenRouterProvider {
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
}
