<?php
/**
 * M13 persisted knowledge inspection integration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\PagedResult;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Retrieval\Lexical\ChunkInspectionStore;

/** Verifies bounded chunk inspection reads the existing persisted lexical projection. */
final class KnowledgeAdminInspectionTest extends TestCase {
	/** The concrete chunk-search store must page one document deterministically. */
	public function test_chunk_search_store_pages_persisted_document_chunks(): void {
		$connection = $this->createMock( Connection::class );
		$connection->method( 'prepare' )->willReturnCallback(
			static function ( string $query, mixed ...$args ): string {
				self::assertSame( 'wp_rag_ai_chunk_search', $args[0] ?? null );
				self::assertSame( 'doc:42', $args[1] ?? null );

				return str_contains( $query, 'COUNT(*)' ) ? 'count-query' : 'page-query';
			}
		);
		$connection->expects( self::once() )->method( 'get_var' )->with( 'count-query' )->willReturn( 21 );
		$connection->expects( self::once() )->method( 'get_results' )->with( 'page-query' )->willReturn(
			array(
				array(
					'chunk_key'     => str_repeat( 'c', 64 ),
					'document_key'  => 'doc:42',
					'source_id'     => 7,
					'document_type' => 'wordpress_post',
					'title'         => 'Privacy policy',
					'canonical_url' => 'https://example.test/privacy',
					'content'       => 'Persisted chunk content',
					'content_hash'  => str_repeat( 'b', 64 ),
					'language'      => 'en',
					'visibility'    => 'public',
					'sequence'      => 20,
					'metadata_json' => '{}',
				)
			)
		);

		$class = 'WpRagAiChatbot\\Retrieval\\Lexical\\WpdbChunkSearchStore';
		$store = new $class( $connection, new TableNames( 'wp_' ) );
		self::assertInstanceOf( ChunkInspectionStore::class, $store );

		$result = call_user_func_array( array( $store, 'paginate_document_chunks' ), array( 'doc:42', 2, 20 ) );
		self::assertInstanceOf( PagedResult::class, $result );
		self::assertSame( 21, $result->total );
		self::assertSame( 2, $result->page );
		self::assertSame( 20, $result->perPage );
		self::assertSame( 'doc:42', $result->items[0]->document_key );
		self::assertSame( 20, $result->items[0]->sequence );
	}
}
