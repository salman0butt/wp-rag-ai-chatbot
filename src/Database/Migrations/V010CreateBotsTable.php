<?php
/**
 * Bots table migration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Migrations;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\TableNames;

/** Creates independently addressable bot configurations. */
final class V010CreateBotsTable implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct( private readonly TableNames $tables ) {
	}

	/** Migration version. */
	public function version(): int {
		return 10;
	}

	/**
	 * Create the bots table.
	 *
	 * @param Connection $connection Database connection.
	 * @throws DatabaseException When the table remains missing after dbDelta().
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->bots();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tbot_id varchar(32) NOT NULL,\n"
			. "\tname varchar(191) NOT NULL,\n"
			. "\tenabled tinyint(1) NOT NULL DEFAULT 1,\n"
			. "\tprovider_id varchar(191) NOT NULL,\n"
			. "\tmodel_id varchar(191) NOT NULL,\n"
			. "\tversion bigint(20) unsigned NOT NULL DEFAULT 1,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tupdated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY bot_id (bot_id),\n"
			. "\tKEY created_bot (created_at,bot_id)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );
		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Bots table was not created successfully.' );
		}
	}
}
