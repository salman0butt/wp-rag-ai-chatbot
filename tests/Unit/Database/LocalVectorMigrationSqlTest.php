<?php
/**
 * Local vector table migration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/**
 * Verifies dedicated versioned local-vector schema.
 */
final class LocalVectorMigrationSqlTest extends TestCase {
	/**
	 * V003 creates a dedicated collection/profile table.
	 */
	public function test_v003_creates_vector_collections_table(): void {
		$class = 'WpRagAiChatbot\\Database\\Migrations\\V003CreateVectorCollectionsTable';
		if ( ! class_exists( $class ) ) {
			self::fail( 'V003CreateVectorCollectionsTable must exist before Task 4 migration behavior can pass.' );
		}

		$tables = new TableNames( 'wp_' );
		self::assertTrue( method_exists( $tables, 'vector_collections' ), 'TableNames must expose vector_collections().' );
		$migration  = new $class( $tables );
		$connection = new RecordingConnection();
		self::assertSame( 3, call_user_func( array( $migration, 'version' ) ) );
		call_user_func( array( $migration, 'up' ), $connection );

		self::assertCount( 1, $connection->db_delta_queries );
		$sql = $connection->db_delta_queries[0];
		self::assertStringContainsString( 'CREATE TABLE wp_rag_ai_vector_collections', $sql );
		self::assertStringContainsString( 'collection_key varchar(191) NOT NULL', $sql );
		self::assertStringContainsString( 'fingerprint char(64) NOT NULL', $sql );
		self::assertStringContainsString( 'dimensions int unsigned NOT NULL', $sql );
		self::assertStringContainsString( 'UNIQUE KEY collection_key (collection_key)', $sql );
	}

	/**
	 * V004 creates vector rows with collection/fingerprint lookup indexes.
	 */
	public function test_v004_creates_vectors_table(): void {
		$class = 'WpRagAiChatbot\\Database\\Migrations\\V004CreateVectorsTable';
		if ( ! class_exists( $class ) ) {
			self::fail( 'V004CreateVectorsTable must exist before Task 4 migration behavior can pass.' );
		}

		$tables = new TableNames( 'wp_' );
		self::assertTrue( method_exists( $tables, 'vectors' ), 'TableNames must expose vectors().' );
		$migration  = new $class( $tables );
		$connection = new RecordingConnection();
		self::assertSame( 4, call_user_func( array( $migration, 'version' ) ) );
		call_user_func( array( $migration, 'up' ), $connection );

		self::assertCount( 1, $connection->db_delta_queries );
		$sql = $connection->db_delta_queries[0];
		self::assertStringContainsString( 'CREATE TABLE wp_rag_ai_vectors', $sql );
		self::assertStringContainsString( 'vector_key varchar(191) NOT NULL', $sql );
		self::assertStringContainsString( 'collection_key varchar(191) NOT NULL', $sql );
		self::assertStringContainsString( 'fingerprint char(64) NOT NULL', $sql );
		self::assertStringContainsString( 'vector_json longtext NOT NULL', $sql );
		self::assertStringContainsString( 'metadata_json longtext NULL', $sql );
		self::assertStringContainsString( 'UNIQUE KEY collection_vector (collection_key,vector_key)', $sql );
		self::assertStringContainsString( 'KEY collection_fingerprint (collection_key,fingerprint)', $sql );
	}
}
