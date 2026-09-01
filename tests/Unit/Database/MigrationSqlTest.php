<?php
/**
 * Incremental migration SQL tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\Migrations\V001CreateSourcesTable;
use WpRagAiChatbot\Database\Migrations\V002CreateDocumentsTable;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/**
 * Verifies portable, incremental M02 schema DDL.
 */
final class MigrationSqlTest extends TestCase {
	/**
	 * V001 creates only the sources table with required indexes.
	 */
	public function test_v001_creates_sources_schema(): void {
		self::assertTrue( class_exists( V001CreateSourcesTable::class ), 'V001CreateSourcesTable must exist before source schema behavior can pass.' );

		$connection = new RecordingConnection();
		$migration  = new V001CreateSourcesTable( new TableNames( 'wp_' ) );

		self::assertSame( 1, $migration->version() );
		$migration->up( $connection );
		self::assertCount( 1, $connection->db_delta_queries );

		$sql = $connection->db_delta_queries[0];

		self::assertStringContainsString( 'CREATE TABLE wp_rag_ai_sources', $sql );
		self::assertStringContainsString( 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT', $sql );
		self::assertStringContainsString( 'source_key varchar(191) NOT NULL', $sql );
		self::assertStringContainsString( 'UNIQUE KEY source_key (source_key)', $sql );
		self::assertStringContainsString( 'KEY source_type (source_type)', $sql );
		self::assertStringContainsString( 'KEY status (status)', $sql );
		self::assertStringContainsString( 'KEY external_id (external_id)', $sql );
		self::assertStringContainsString( 'created_at datetime NOT NULL', $sql );
		self::assertStringContainsString( 'updated_at datetime NOT NULL', $sql );
		self::assertStringNotContainsString( 'rag_ai_documents', $sql );
		$this->assert_forbidden_future_schema_absent( $sql );
	}

	/**
	 * V002 creates only the documents table with required indexes.
	 */
	public function test_v002_creates_documents_schema(): void {
		self::assertTrue( class_exists( V002CreateDocumentsTable::class ), 'V002CreateDocumentsTable must exist before document schema behavior can pass.' );

		$connection = new RecordingConnection();
		$migration  = new V002CreateDocumentsTable( new TableNames( 'wp_' ) );

		self::assertSame( 2, $migration->version() );
		$migration->up( $connection );
		self::assertCount( 1, $connection->db_delta_queries );

		$sql = $connection->db_delta_queries[0];

		self::assertStringContainsString( 'CREATE TABLE wp_rag_ai_documents', $sql );
		self::assertStringContainsString( 'document_key varchar(191) NOT NULL', $sql );
		self::assertStringContainsString( 'source_id bigint(20) unsigned NOT NULL', $sql );
		self::assertStringContainsString( 'content longtext NOT NULL', $sql );
		self::assertStringContainsString( 'metadata_json longtext NULL', $sql );
		self::assertStringContainsString( 'UNIQUE KEY document_key (document_key)', $sql );
		self::assertStringContainsString( 'KEY source_id (source_id)', $sql );
		self::assertStringContainsString( 'KEY external_id (external_id)', $sql );
		self::assertStringContainsString( 'KEY document_type (document_type)', $sql );
		self::assertStringContainsString( 'KEY content_hash (content_hash)', $sql );
		self::assertStringNotContainsString( 'rag_ai_sources (', $sql );
		$this->assert_forbidden_future_schema_absent( $sql );
	}

	/**
	 * A source migration reports failure when dbDelta does not leave the table present.
	 */
	public function test_v001_throws_when_sources_table_is_missing_after_db_delta(): void {
		self::assertTrue( class_exists( V001CreateSourcesTable::class ), 'V001CreateSourcesTable must exist before source schema behavior can pass.' );

		$migration = new V001CreateSourcesTable( new TableNames( 'wp_' ) );

		$this->expectException( DatabaseException::class );
		$migration->up( new RecordingConnection( table_exists: false ) );
	}

	/**
	 * A document migration reports failure when dbDelta does not leave the table present.
	 */
	public function test_v002_throws_when_documents_table_is_missing_after_db_delta(): void {
		self::assertTrue( class_exists( V002CreateDocumentsTable::class ), 'V002CreateDocumentsTable must exist before document schema behavior can pass.' );

		$migration = new V002CreateDocumentsTable( new TableNames( 'wp_' ) );

		$this->expectException( DatabaseException::class );
		$migration->up( new RecordingConnection( table_exists: false ) );
	}

	/**
	 * Assert later-milestone schema and non-portable constructs are absent.
	 *
	 * @param string $sql Migration SQL.
	 */
	private function assert_forbidden_future_schema_absent( string $sql ): void {
		$normalized = strtolower( $sql );

		self::assertStringNotContainsString( 'foreign key', $normalized );
		self::assertDoesNotMatchRegularExpression( '/\sjson\s/', $normalized );
		self::assertStringNotContainsString( 'embedding', $normalized );
		self::assertStringNotContainsString( 'vector', $normalized );
		self::assertStringNotContainsString( 'rag_ai_jobs', $normalized );
		self::assertStringNotContainsString( 'rag_ai_conversations', $normalized );
	}
}
