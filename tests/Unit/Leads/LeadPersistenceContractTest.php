<?php
/**
 * M16 lead persistence contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Leads;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\DatabaseSchema;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\Repository\WpdbLeadRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Leads\LeadDraft;
use WpRagAiChatbot\Leads\LeadRepository;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/**
 * Proves lead capture is bounded, separately persisted, and query-indexed only for proven admin shapes.
 */
final class LeadPersistenceContractTest extends TestCase {
	/** Bot identifiers follow the existing 32-byte bot persistence authority. */
	public function test_lead_draft_rejects_bot_identity_beyond_existing_persistence_limit(): void {
		$this->expectException( InvalidArgumentException::class );

		new LeadDraft( 'conversation-1', str_repeat( 'b', 33 ) );
	}

	/** V015 creates a dedicated lead table with only the required PII and query indexes. */
	public function test_lead_table_is_schema_version_fifteen(): void {
		$migration_class = 'WpRagAiChatbot\\Database\\Migrations\\V015CreateLeadsTable';

		self::assertSame( 15, DatabaseSchema::VERSION );
		self::assertTrue( class_exists( $migration_class ), 'M16 Task 4A requires V015CreateLeadsTable.' );

		$tables = new TableNames( 'wp_' );
		self::assertSame( 'wp_rag_ai_leads', $tables->leads() );

		$migration = new $migration_class( $tables );
		self::assertInstanceOf( Migration::class, $migration );
		self::assertSame( 15, $migration->version() );

		$connection = new RecordingConnection();
		$migration->up( $connection );
		self::assertCount( 1, $connection->db_delta_queries );

		$sql = $connection->db_delta_queries[0];
		self::assertStringContainsString( 'lead_id varchar(32) NOT NULL', $sql );
		self::assertStringContainsString( 'conversation_id varchar(191) NOT NULL', $sql );
		self::assertStringContainsString( 'bot_id varchar(32) NOT NULL', $sql );
		self::assertStringContainsString( 'name varchar(160) NULL', $sql );
		self::assertStringContainsString( 'email varchar(254) NULL', $sql );
		self::assertStringContainsString( 'phone varchar(64) NULL', $sql );
		self::assertStringContainsString( 'note text NULL', $sql );
		self::assertStringContainsString( 'source varchar(100) NULL', $sql );
		self::assertStringContainsString( 'UNIQUE KEY lead_id (lead_id)', $sql );
		self::assertStringContainsString( 'KEY conversation_created (conversation_id,created_at)', $sql );
		self::assertStringContainsString( 'KEY bot_created (bot_id,created_at)', $sql );
	}

	/** The write repository persists only normalized allow-listed lead columns. */
	public function test_wpdb_repository_creates_normalized_lead(): void {
		$connection = new RecordingConnection();
		$repository = new WpdbLeadRepository( $connection, new TableNames( 'wp_' ) );

		self::assertInstanceOf( LeadRepository::class, $repository );

		$lead = $repository->create(
			new LeadDraft(
				'conversation-1',
				'bot-1',
				' Salman Butt ',
				' SALMAN@EXAMPLE.COM ',
				' +92 300 1234567 ',
				' Please call tomorrow. ',
				' pre_chat '
			)
		);

		self::assertMatchesRegularExpression( '/^[a-f0-9]{32}$/D', $lead->lead_id );
		self::assertSame( 'conversation-1', $lead->conversation_id );
		self::assertSame( 'bot-1', $lead->bot_id );
		self::assertSame( 'Salman Butt', $lead->name );
		self::assertSame( 'salman@example.com', $lead->email );
		self::assertSame( '+92 300 1234567', $lead->phone );
		self::assertSame( 'Please call tomorrow.', $lead->note );
		self::assertSame( 'pre_chat', $lead->source );
		self::assertNotSame( '', $lead->created_at );
		self::assertSame( $lead->created_at, $lead->updated_at );

		self::assertCount( 1, $connection->insert_calls );
		self::assertSame( 'wp_rag_ai_leads', $connection->insert_calls[0]['table'] );
		self::assertSame( $lead->lead_id, $connection->insert_calls[0]['data']['lead_id'] );
		self::assertSame( 'conversation-1', $connection->insert_calls[0]['data']['conversation_id'] );
		self::assertSame( 'bot-1', $connection->insert_calls[0]['data']['bot_id'] );
		self::assertSame( 'salman@example.com', $connection->insert_calls[0]['data']['email'] );
		self::assertArrayNotHasKey( 'owner_scope', $connection->insert_calls[0]['data'] );
	}

	/** Database failures stay behind the repository boundary and do not return a phantom lead. */
	public function test_wpdb_repository_fails_closed_when_insert_fails(): void {
		$connection                = new RecordingConnection();
		$connection->insert_result = false;
		$repository                = new WpdbLeadRepository( $connection, new TableNames( 'wp_' ) );

		$this->expectException( DatabaseException::class );
		$repository->create( new LeadDraft( 'conversation-1', 'bot-1' ) );
	}
}
