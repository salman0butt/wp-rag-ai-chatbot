<?php
/**
 * Sources table migration.
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
 * Creates the per-site knowledge sources table.
 */
final class V001CreateSourcesTable implements Migration {
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
		return 1;
	}

	/**
	 * Create the sources table and verify it exists.
	 *
	 * @param Connection $connection Database connection.
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->sources();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tsource_key varchar(191) NOT NULL,\n"
			. "\tsource_type varchar(64) NOT NULL,\n"
			. "\texternal_id varchar(191) DEFAULT NULL,\n"
			. "\ttitle text NOT NULL,\n"
			. "\tcanonical_url text NULL,\n"
			. "\tstatus varchar(32) NOT NULL DEFAULT 'active',\n"
			. "\tconfig_json longtext NULL,\n"
			. "\tsource_hash char(64) DEFAULT NULL,\n"
			. "\tlast_synced_at datetime DEFAULT NULL,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tupdated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY source_key (source_key),\n"
			. "\tKEY source_type (source_type),\n"
			. "\tKEY status (status),\n"
			. "\tKEY external_id (external_id)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );

		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Sources table was not created successfully.' );
		}
	}
}
