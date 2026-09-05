<?php
/**
 * M09 jobs migration contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Migrations\V005CreateJobsTable;
use WpRagAiChatbot\Database\TableNames;

/**
 * Verifies the jobs schema is owned by migration V5.
 */
final class JobsMigrationContractTest extends TestCase {
	/**
	 * The jobs table remains owned by migration V5 even as later migrations advance the schema.
	 */
	public function test_jobs_table_is_owned_by_schema_version_five(): void {
		$migration = new V005CreateJobsTable( new TableNames( 'wp_' ) );

		self::assertSame( 5, $migration->version() );
	}

	/**
	 * Jobs remain a per-site plugin-owned table.
	 */
	public function test_jobs_table_name_uses_current_site_prefix(): void {
		$tables = new TableNames( 'wp_9_' );

		self::assertSame( 'wp_9_rag_ai_jobs', $tables->jobs() );
		self::assertContains( $tables->jobs(), $tables->all() );
	}
}
