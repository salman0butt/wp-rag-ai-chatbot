<?php
/**
 * OpenAI managed vector-store adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore\OpenAI;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Tests\Support\VectorStore\QdrantFakeTransport;
use WpRagAiChatbot\VectorStore\VectorDeleteStore;
use WpRagAiChatbot\VectorStore\VectorSearchStore;
use WpRagAiChatbot\VectorStore\VectorUpsertStore;

/**
 * Verifies truthful managed OpenAI vector-store behavior offline.
 */
final class OpenAiManagedVectorStoreTest extends TestCase {
	/** Managed OpenAI stores must never advertise raw-vector operations. */
	public function test_store_exposes_only_managed_capabilities(): void {
		$store = $this->store( new QdrantFakeTransport( array() ) );

		self::assertFalse( $store instanceof VectorUpsertStore );
		self::assertFalse( $store instanceof VectorDeleteStore );
		self::assertFalse( $store instanceof VectorSearchStore );
		self::assertFalse( $store->capabilities()->upsert );
		self::assertFalse( $store->capabilities()->delete );
		self::assertFalse( $store->capabilities()->search );
		self::assertTrue( $store->capabilities()->managed_ingestion );
		self::assertTrue( $store->capabilities()->managed_search );
	}

	/** Managed configuration is fixed to OpenAI and validates identifiers/secrets. */
	public function test_config_rejects_invalid_values(): void {
		$config_class = 'WpRagAiChatbot\\VectorStore\\OpenAI\\OpenAiVectorStoreConfig';
		self::assertTrue( class_exists( $config_class ), 'OpenAiVectorStoreConfig must exist.' );

		foreach ( array( array( '', 'vs_abc123' ), array( 'secret', '../unsafe' ), array( 'secret', '' ) ) as $args ) {
			try {
				new $config_class( $args[0], $args[1] );
				self::fail( 'Invalid OpenAI managed vector-store configuration must be rejected.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/** File attachment uses the fixed managed-file endpoint with server-side auth. */
	public function test_attach_file_maps_fixed_endpoint_auth_attributes_and_no_redirects(): void {
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), '{"id":"file_123","object":"vector_store.file","status":"in_progress"}' ) ) );
		$result    = $this->store( $transport )->attach_file( 'file_123', array( 'lang' => 'en' ) );

		self::assertTrue( $result->changed );
		self::assertCount( 1, $transport->requests );
		$request = $transport->requests[0];
		self::assertSame( 'POST', $request->method );
		self::assertSame( 'https://api.openai.com/v1/vector_stores/vs_abc123/files', $request->url );
		self::assertSame( 'Bearer secret', $request->headers['Authorization'] ?? null );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'file_123', $request->json_body['file_id'] ?? null );
		self::assertSame( array( 'lang' => 'en' ), $request->json_body['attributes'] ?? null );
	}

	/** Managed search is query-text based, bounded, and maps safe result data. */
	public function test_search_uses_text_query_and_bounded_result_contract(): void {
		$body      = '{"object":"vector_store.search_results.page","search_query":["return policy"],"data":[{"file_id":"file_123","filename":"policy.txt","score":0.95,"attributes":{"lang":"en"},"content":[{"type":"text","text":"Returns are accepted."}]}],"has_more":false,"next_page":null}';
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), $body ) ) );
		$result    = $this->store( $transport )->managed_search( 'return policy', 5 );

		self::assertCount( 1, $result->matches );
		self::assertSame( 'file_123', $result->matches[0]->file_id );
		self::assertSame( 'policy.txt', $result->matches[0]->filename );
		self::assertSame( 0.95, $result->matches[0]->score );
		self::assertSame( array( 'Returns are accepted.' ), $result->matches[0]->content );
		self::assertSame( 'en', $result->matches[0]->attributes['lang'] ?? null );
		$request = $transport->requests[0];
		self::assertSame( 'https://api.openai.com/v1/vector_stores/vs_abc123/search', $request->url );
		self::assertSame( 'return policy', $request->json_body['query'] ?? null );
		self::assertSame( 5, $request->json_body['max_num_results'] ?? null );
		self::assertSame( 0, $request->redirection );
	}

	/** Managed file deletion stays scoped to the configured vector store. */
	public function test_delete_file_uses_managed_file_endpoint(): void {
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), '{"id":"file_123","deleted":true,"object":"vector_store.file.deleted"}' ) ) );
		$result    = $this->store( $transport )->delete_file( 'file_123' );

		self::assertTrue( $result->changed );
		self::assertCount( 1, $transport->requests );
		self::assertSame( 'DELETE', $transport->requests[0]->method );
		self::assertSame( 'https://api.openai.com/v1/vector_stores/vs_abc123/files/file_123', $transport->requests[0]->url );
	}

	/** Remote failures must not leak opaque bodies or credentials. */
	public function test_remote_failure_is_sanitized(): void {
		$transport = new QdrantFakeTransport( array( new HttpResponse( 500, array(), 'opaque-secret-provider-body' ) ) );
		try {
			$this->store( $transport )->attach_file( 'file_123' );
			self::fail( 'OpenAI managed vector-store failure must be normalized.' );
		} catch ( \Throwable $exception ) {
			self::assertStringNotContainsString( 'opaque-secret-provider-body', $exception->getMessage() );
			self::assertStringNotContainsString( 'secret', $exception->getMessage() );
		}
	}

	/** Explicit health performs one bounded retrieve request and no constructor I/O. */
	public function test_health_is_explicit_and_bounded(): void {
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), '{"id":"vs_abc123","object":"vector_store","status":"completed"}' ) ) );
		$store     = $this->store( $transport );
		self::assertCount( 0, $transport->requests );

		$health = $store->health();
		self::assertTrue( $health->healthy );
		self::assertCount( 1, $transport->requests );
		self::assertSame( 'GET', $transport->requests[0]->method );
		self::assertSame( 'https://api.openai.com/v1/vector_stores/vs_abc123', $transport->requests[0]->url );
		self::assertSame( 0, $transport->requests[0]->redirection );
	}

	/** A managed store is not healthy until OpenAI reports it ready for use. */
	public function test_health_rejects_non_ready_store_status(): void {
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), '{"id":"vs_abc123","object":"vector_store","status":"in_progress"}' ) ) );

		self::assertFalse( $this->store( $transport )->health()->healthy );
	}

	/**
	 * Create the adapter under test with offline dependencies only.
	 *
	 * @param QdrantFakeTransport $transport Recording fake transport.
	 */
	private function store( QdrantFakeTransport $transport ): object {
		$config_class = 'WpRagAiChatbot\\VectorStore\\OpenAI\\OpenAiVectorStoreConfig';
		$store_class  = 'WpRagAiChatbot\\VectorStore\\OpenAI\\OpenAiManagedVectorStore';
		self::assertTrue( class_exists( $config_class ), 'OpenAiVectorStoreConfig must exist.' );
		self::assertTrue( class_exists( $store_class ), 'OpenAiManagedVectorStore must exist.' );

		return new $store_class( new $config_class( 'secret', 'vs_abc123' ), $transport );
	}
}
