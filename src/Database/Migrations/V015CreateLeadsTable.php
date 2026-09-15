<?php
/**
 * Leads table migration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Migrations;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\TableNames;

/** Creates bounded lead/contact capture persistence. */
final class V015CreateLeadsTable implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct( private readonly TableNames $tables ) {
	}

	/** Migration version. */
	public function version(): int {
		return 15;
	}

	/**
	 * Create the leads table.
	 *
	 * @param Connection $connection Database connection.
	 * @throws DatabaseException When the table remains missing after dbDelta().
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->leads();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tlead_id varchar(32) NOT NULL,\n"
			. "\tconversation_id varchar(191) NOT NULL,\n"
			. "\tbot_id varchar(32) NOT NULL,\n"
			. "\tname varchar(160) NULL,\n"
			. "\temail varchar(254) NULL,\n"
			. "\tphone varchar(64) NULL,\n"
			. "\tnote text NULL,\n"
			. "\tsource varchar(100) NULL,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tupdated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY lead_id (lead_id),\n"
			. "\tKEY conversation_created (conversation_id,created_at),\n"
			. "\tKEY bot_created (bot_id,created_at)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );
		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Leads table was not created successfully.' );
		}
	}
}
