<?php
/**
 * Conversation bot-association migration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Migrations;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\TableNames;

/** Adds explicit nullable bot association to existing conversations. */
final class V014AddConversationBotAssociation implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct( private readonly TableNames $tables ) {
	}

	/** Migration version. */
	public function version(): int {
		return 14;
	}

	/**
	 * Add nullable bot identity through WordPress dbDelta.
	 *
	 * Existing conversations intentionally remain unassigned so historical rows
	 * continue to be readable without inventing bot ownership.
	 *
	 * @param Connection $connection Database connection.
	 * @throws DatabaseException When the conversations table remains missing after dbDelta().
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->conversations();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tconversation_id varchar(191) NOT NULL,\n"
			. "\towner_scope varchar(191) NOT NULL,\n"
			. "\tbot_id varchar(32) NULL,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tupdated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY owner_conversation (owner_scope,conversation_id),\n"
			. "\tKEY owner_updated (owner_scope,updated_at),\n"
			. "\tKEY bot_updated (bot_id,updated_at)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );
		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Conversations table was not updated successfully.' );
		}
	}
}
