<?php
/**
 * Chunk-search projection migration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseSchema;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/**
 * Defines the durable lexical projection schema contract.
 */
final class ChunkSearchMigrationContractTest extends TestCase {
	/**
	 * V006 creates the bounded searchable chunk projection and required scope indexes.
	 */
	public function test_v006_creates_chunk_search_projection(): void {
		$class = 'WpRagAiChatbot\\Database\\Migrations\\V006CreateChunkSearchTable';
		if ( ! class_exists( $class ) ) {
			self::fail( 'V006CreateChunkSearchTable must exist before M10 Task 3 can pass.' );
		}

		$tables = new TableNames( 'wp_' );
		self::assertTrue( method_exists( $tables, 'chunk_search' ), 'TableNames must expose chunk_search().' );
		self::assertSame( 6, DatabaseSchema::VERSION );

		$migration  = new $class( $tables );
		$connection = new RecordingConnection();
		self::assertSame( 6, call_user_func( array( $migration, 'version' ) ) );
		call_user_func( array( $migration, 'up' ), $connection );

		self::assertCount( 1, $connection->db_delta_queries );
		$sql = $connection->db_delta_queries[0];
		self::assertStringContainsString( 'CREATE TABLE wp_rag_ai_chunk_search', $sql );
		self::assertStringContainsString( 'collection_id varchar(128) NOT NULL', $sql );
		self::assertStringContainsString( 'chunk_key char(64) NOT NULL', $sql );
		self::assertStringContainsString( 'document_key varchar(191) NOT NULL', $sql );
		self::assertStringContainsString( 'source_id bigint(20) unsigned NOT NULL', $sql );
		self::assertStringContainsString( 'content longtext NOT NULL', $sql );
		self::assertStringContainsString( 'metadata_json longtext NULL', $sql );
		self::assertStringContainsString( 'UNIQUE KEY collection_chunk (collection_id,chunk_key)', $sql );
		self::assertStringContainsString( 'KEY collection_visibility (collection_id,visibility)', $sql );
		self::assertStringContainsString( 'KEY collection_language (collection_id,language)', $sql );
		self::assertStringContainsString( 'KEY collection_document (collection_id,document_key)', $sql );
		self::assertStringContainsString( 'KEY collection_source (collection_id,source_id)', $sql );
	}
}
