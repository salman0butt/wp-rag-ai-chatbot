<?php
/**
 * Local vectors table migration.
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
 * Creates the per-site local vectors table.
 */
final class V004CreateVectorsTable implements Migration {
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
		return 4;
	}

	/**
	 * Create the local vectors table.
	 *
	 * @param Connection $connection Database connection.
	 * @throws DatabaseException When the table remains missing after dbDelta().
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->vectors();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tvector_key varchar(191) NOT NULL,\n"
			. "\tcollection_key varchar(191) NOT NULL,\n"
			. "\tfingerprint char(64) NOT NULL,\n"
			. "\tvector_json longtext NOT NULL,\n"
			. "\tmetadata_json longtext NULL,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tupdated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY collection_vector (collection_key,vector_key),\n"
			. "\tKEY collection_fingerprint (collection_key,fingerprint),\n"
			. "\tKEY vector_key (vector_key)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );
		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Vectors table was not created successfully.' );
		}
	}
}
