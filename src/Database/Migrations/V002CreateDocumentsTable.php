<?php
/**
 * Documents table migration.
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
 * Creates the per-site normalized documents table.
 */
final class V002CreateDocumentsTable implements Migration {
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
		return 2;
	}

	/**
	 * Create the documents table and verify it exists.
	 *
	 * @param Connection $connection Database connection.
	 */
	public function up( Connection $connection ): void {
		$table = $this->tables->documents();
		$sql   = "CREATE TABLE {$table} (\n"
			. "\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "\tdocument_key varchar(191) NOT NULL,\n"
			. "\tsource_id bigint(20) unsigned NOT NULL,\n"
			. "\texternal_id varchar(191) DEFAULT NULL,\n"
			. "\tdocument_type varchar(64) NOT NULL,\n"
			. "\ttitle text NOT NULL,\n"
			. "\tcanonical_url text NULL,\n"
			. "\tcontent longtext NOT NULL,\n"
			. "\tmetadata_json longtext NULL,\n"
			. "\tsource_version varchar(191) DEFAULT NULL,\n"
			. "\tcontent_hash char(64) NOT NULL,\n"
			. "\tlanguage varchar(20) DEFAULT NULL,\n"
			. "\tcreated_at datetime NOT NULL,\n"
			. "\tupdated_at datetime NOT NULL,\n"
			. "\tPRIMARY KEY  (id),\n"
			. "\tUNIQUE KEY document_key (document_key),\n"
			. "\tKEY source_id (source_id),\n"
			. "\tKEY external_id (external_id),\n"
			. "\tKEY document_type (document_type),\n"
			. "\tKEY content_hash (content_hash)\n"
			. ') ' . $connection->charset_collate() . ';';

		$connection->db_delta( $sql );

		if ( ! $connection->table_exists( $table ) ) {
			throw new DatabaseException( 'Documents table was not created successfully.' );
		}
	}
}
