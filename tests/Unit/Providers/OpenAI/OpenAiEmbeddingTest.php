<?php
/**
 * OpenAI direct embedding adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\OpenAI;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\OpenAI\OpenAiProvider;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Tests\Support\Providers\Http\QueuedHttpTransport;

/**
 * Verifies fixed, one-shot OpenAI embedding requests and normalized responses.
 */
final class OpenAiEmbeddingTest extends TestCase {
	public function test_embed_uses_fixed_endpoint_and_normalizes_ordered_response(): void {
		$body      = '{"object":"list","data":[{"object":"embedding","index":0,"embedding":[0.1,0.2]},{"object":"embedding","index":1,"embedding":[0.3,0.4]}],"model":"text-embedding-test","usage":{"prompt_tokens":7,"total_tokens":7}}';
		$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array( 'x-request-id' => 'req_embed' ), $body ) ) );
		$provider  = $this->provider( $transport );

		self::assertInstanceOf( EmbeddingProvider::class, $provider );
		$result = $provider->embed( new EmbeddingRequest( 'text-embedding-test', array( 'one', 'two' ), 2 ) );

		self::assertCount( 1, $transport->requests );
		$request = $transport->requests[0];
		self::assertSame( 'POST', $request->method );
		self::assertSame( 'https://api.openai.com/v1/embeddings', $request->url );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'Bearer openai-secret', $request->headers['Authorization'] );
		self::assertSame(
			array(
				'model'      => 'text-embedding-test',
				'input'      => array( 'one', 'two' ),
				'dimensions' => 2,
			),
			$request->json_body
		);
		self::assertSame( ProviderIds::OPENAI_DIRECT, $result->provider_id );
		self::assertSame( 'text-embedding-test', $result->model );
		self::assertSame( array( 0, 1 ), array_map( static fn ( $vector ): int => $vector->index, $result->vectors ) );
		self::assertSame( 7, $result->usage->input_tokens );
	}

	public function test_embed_omits_dimensions_when_not_requested(): void {
		$body      = '{"data":[{"index":0,"embedding":[0.1]}],"model":"text-embedding-test"}';
		$transport = new QueuedHttpTransport( array( new HttpResponse( 200, array(), $body ) ) );
		$provider  = $this->provider( $transport );
		$result    = $provider->embed( new EmbeddingRequest( 'text-embedding-test', array( 'one' ) ) );

		self::assertSame( array( 'model' => 'text-embedding-test', 'input' => array( 'one' ) ), $transport->requests[0]->json_body );
		self::assertFalse( $result->usage->known );
	}

	public function test_embed_does_not_retry_billable_http_failure_and_redacts_secret(): void {
		$secret    = 'openai-secret-value';
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse( 503, array(), '{"error":{"message":"' . $secret . ' upstream"}}' ),
				new HttpResponse( 200, array(), '{"data":[{"index":0,"embedding":[0.1]}],"model":"text-embedding-test"}' ),
			)
		);

		try {
			$this->provider( $transport, $secret )->embed( new EmbeddingRequest( 'text-embedding-test', array( 'one' ) ) );
			self::fail( 'Expected upstream embedding failure.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::UPSTREAM_SERVER, $exception->error_code );
			self::assertStringNotContainsString( $secret, $exception->getMessage() );
			self::assertCount( 1, $transport->requests );
		}
	}

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
}
