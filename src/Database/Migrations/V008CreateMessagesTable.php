<?php
/**
 * Messages table migration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Migrations;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\TableNames;

/** Creates ownership-scoped conversation messages. */
final class V008CreateMessagesTable implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct( private readonly TableNames $tables ) {
	}

	/** Migration version. */
	public function version(): int {
		return 8;
	}

	/**
	 * Create the messages table.
	 *
	 * @param Connection $connection Database connection.
	 * @throws DatabaseException When the table remains missing after dbDelta().
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->messages();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tconversation_id varchar(191) NOT NULL,\n"
			. "\towner_scope varchar(191) NOT NULL,\n"
			. "\trole varchar(32) NOT NULL,\n"
			. "\tcontent longtext NOT NULL,\n"
			. "\tmetadata_json longtext NULL,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tKEY conversation_owner_id (conversation_id,owner_scope,id)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );
		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Messages table was not created successfully.' );
		}
	}
}
