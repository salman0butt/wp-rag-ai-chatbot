<?php
/**
 * M13 persisted canonical chunk lookup tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval\Lexical;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Retrieval\Lexical\ChunkLookupStore;
use WpRagAiChatbot\Retrieval\Lexical\WpdbChunkSearchStore;

/**
 * Proves semantic hydration can resolve one canonical persisted chunk without running lexical ranking.
 */
final class WpdbChunkSearchLookupTest extends TestCase {
	/** A persisted chunk key must resolve only inside the explicit collection boundary. */
	public function test_finds_one_canonical_chunk_by_collection_and_key(): void {
		$chunk_key  = hash( 'sha256', 'chunk-1' );
		$connection = $this->connection_for_row(
			$chunk_key,
			array(
				'chunk_key'     => $chunk_key,
				'document_key'  => 'doc-1',
				'source_id'     => 7,
				'document_type' => 'post',
				'title'         => 'Guide',
				'canonical_url' => 'https://example.test/guide',
				'content'       => 'Canonical content',
				'content_hash'  => hash( 'sha256', 'Canonical content' ),
				'language'      => 'en',
				'visibility'    => 'public',
				'sequence'      => 0,
				'metadata_json' => '{"origin":"wordpress"}',
			)
		);
		$store      = new WpdbChunkSearchStore( $connection, new TableNames( 'wp_' ) );

		self::assertInstanceOf( ChunkLookupStore::class, $store );
		$record = $store->find_chunk( 'production-rag', $chunk_key );

		self::assertNotNull( $record );
		self::assertSame( $chunk_key, $record->chunk_key );
		self::assertSame( 'doc-1', $record->document_key );
		self::assertSame( 7, $record->source_id );
		self::assertSame( 'Canonical content', $record->content );
	}

	/** A missing key must return null without falling back to another collection. */
	public function test_missing_chunk_returns_null(): void {
		$chunk_key = hash( 'sha256', 'missing-chunk' );
		$store     = new WpdbChunkSearchStore( $this->connection_for_row( $chunk_key, null ), new TableNames( 'wp_' ) );

		self::assertNull( $store->find_chunk( 'production-rag', $chunk_key ) );
	}

	/**
	 * Build the exact bounded lookup connection double.
	 *
	 * @param string                    $chunk_key Expected canonical key.
	 * @param array<string, mixed>|null $row Persisted row or null when absent.
	 */
	private function connection_for_row( string $chunk_key, ?array $row ): Connection&MockObject {
		$connection = $this->createMock( Connection::class );
		$connection
			->expects( self::once() )
			->method( 'prepare' )
			->with(
				'SELECT chunk_key, document_key, source_id, document_type, title, canonical_url, content, content_hash, language, visibility, sequence, metadata_json FROM %i WHERE collection_id = %s AND chunk_key = %s LIMIT 1',
				'wp_rag_ai_chunk_search',
				'production-rag',
				$chunk_key
			)
			->willReturn( 'prepared-chunk-lookup' );
		$connection
			->expects( self::once() )
			->method( 'get_row' )
			->with( 'prepared-chunk-lookup' )
			->willReturn( $row );
		return $connection;
	}
}
