<?php
/**
 * Persisted jobs table migration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Migrations;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\TableNames;

/**
 * Creates the per-site persisted jobs table.
 */
final class V005CreateJobsTable implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct( private readonly TableNames $tables ) {
	}

	/**
	 * Migration version.
	 */
	public function version(): int {
		return 5;
	}

	/**
	 * Create the persisted jobs table.
	 *
	 * @param Connection $connection Database connection.
	 * @throws DatabaseException When the table remains missing after dbDelta().
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->jobs();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tjob_key varchar(191) NOT NULL,\n"
			. "\ttype varchar(100) NOT NULL,\n"
			. "\tstatus varchar(20) NOT NULL,\n"
			. "\tidempotency_key varchar(191) NULL,\n"
			. "\tpayload_json longtext NOT NULL,\n"
			. "\tattempts int unsigned NOT NULL DEFAULT 0,\n"
			. "\tmax_attempts int unsigned NOT NULL DEFAULT 3,\n"
			. "\tavailable_at datetime NOT NULL,\n"
			. "\tlease_owner varchar(191) NULL,\n"
			. "\tlease_expires_at datetime NULL,\n"
			. "\tcancel_requested_at datetime NULL,\n"
			. "\tprogress_current bigint(20) unsigned NULL,\n"
			. "\tprogress_total bigint(20) unsigned NULL,\n"
			. "\tprogress_message varchar(191) NULL,\n"
			. "\tlast_error_code varchar(100) NULL,\n"
			. "\tlast_error_message varchar(500) NULL,\n"
			. "\tstarted_at datetime NULL,\n"
			. "\tcompleted_at datetime NULL,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tupdated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY job_key (job_key),\n"
			. "\tKEY queue_scan (status,available_at,id),\n"
			. "\tKEY lease_recovery (status,lease_expires_at,id),\n"
			. "\tKEY idempotency_lookup (type,idempotency_key,id),\n"
			. "\tKEY terminal_cleanup (status,completed_at,id)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );
		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Jobs table was not created successfully.' );
		}
	}
}
