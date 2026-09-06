<?php
/**
 * Message citations table migration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Migrations;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\TableNames;

/** Creates canonical citations attached to persisted assistant messages. */
final class V009CreateMessageCitationsTable implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct( private readonly TableNames $tables ) {
	}

	/** Migration version. */
	public function version(): int {
		return 9;
	}

	/**
	 * Create the message-citations table.
	 *
	 * @param Connection $connection Database connection.
	 * @throws DatabaseException When the table remains missing after dbDelta().
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->message_citations();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tmessage_id bigint(20) unsigned NOT NULL,\n"
			. "\tcitation_key varchar(32) NOT NULL,\n"
			. "\tdocument_key varchar(191) NOT NULL,\n"
			. "\ttitle text NOT NULL,\n"
			. "\tcanonical_url text NULL,\n"
			. "\tmetadata_json longtext NULL,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY message_citation (message_id,citation_key),\n"
			. "\tKEY message_id (message_id)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );
		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Message citations table was not created successfully.' );
		}
	}
}
