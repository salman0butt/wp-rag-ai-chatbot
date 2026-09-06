<?php
/**
 * Searchable chunk projection migration.
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
 * Creates the per-site searchable chunk projection.
 */
final class V006CreateChunkSearchTable implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct( private readonly TableNames $tables ) {
	}

	/** Migration version. */
	public function version(): int {
		return 6;
	}

	/**
	 * Create the searchable chunk projection.
	 *
	 * @param Connection $connection Database connection.
	 * @throws DatabaseException When the table remains missing after dbDelta().
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->chunk_search();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tcollection_id varchar(128) NOT NULL,\n"
			. "\tchunk_key char(64) NOT NULL,\n"
			. "\tdocument_key varchar(191) NOT NULL,\n"
			. "\tsource_id bigint(20) unsigned NOT NULL,\n"
			. "\tdocument_type varchar(100) NOT NULL,\n"
			. "\ttitle text NOT NULL,\n"
			. "\tcanonical_url text NULL,\n"
			. "\tcontent longtext NOT NULL,\n"
			. "\tcontent_hash char(64) NOT NULL,\n"
			. "\tlanguage varchar(35) NULL,\n"
			. "\tvisibility varchar(32) NOT NULL,\n"
			. "\tsequence int unsigned NOT NULL,\n"
			. "\tmetadata_json longtext NULL,\n"
			. "\tupdated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY collection_chunk (collection_id,chunk_key),\n"
			. "\tKEY collection_visibility (collection_id,visibility),\n"
			. "\tKEY collection_language (collection_id,language),\n"
			. "\tKEY collection_document (collection_id,document_key),\n"
			. "\tKEY collection_source (collection_id,source_id)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );
		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Chunk-search table was not created successfully.' );
		}
	}
}
