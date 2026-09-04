<?php
/**
 * Vector collections table migration.
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
 * Creates the per-site local vector collections table.
 */
final class V003CreateVectorCollectionsTable implements Migration {
	/**
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct( private readonly TableNames $tables ) {
	}

	/**
	 * Migration version.
	 */
	public function version(): int {
		return 3;
	}

	/**
	 * Create the vector collections table.
	 *
	 * @param Connection $connection Database connection.
	 * @throws DatabaseException When the table remains missing after dbDelta().
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->vector_collections();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tcollection_key varchar(191) NOT NULL,\n"
			. "\tfingerprint char(64) NOT NULL,\n"
			. "\tdimensions int unsigned NOT NULL,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tupdated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY collection_key (collection_key),\n"
			. "\tKEY fingerprint (fingerprint)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );
		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Vector collections table was not created successfully.' );
		}
	}
}
