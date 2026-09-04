<?php
/**
 * Database migration composition root.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

use WpRagAiChatbot\Database\Migrations\V001CreateSourcesTable;
use WpRagAiChatbot\Database\Migrations\V002CreateDocumentsTable;
use WpRagAiChatbot\Database\Migrations\V003CreateVectorCollectionsTable;
use WpRagAiChatbot\Database\Migrations\V004CreateVectorsTable;

/**
 * Composes and executes database migrations at WordPress lifecycle boundaries.
 */
final class DatabaseBootstrap {
	/**
	 * WordPress activation action callback.
	 */
	public static function on_activate(): void {
		self::migrate();
	}

	/**
	 * Early plugins_loaded callback for automatic upgrades.
	 */
	public static function on_plugins_loaded(): void {
		self::migrate_if_needed();
	}

	/**
	 * Run pending migrations and return the execution status.
	 */
	public static function migrate(): MigrationStatus {
		return self::runner()->run();
	}

	/**
	 * Avoid migration composition when the stored version is already current.
	 */
	public static function migrate_if_needed(): MigrationStatus {
		$versions = new WordPressSchemaVersionStore();
		if ( $versions->current() >= DatabaseSchema::VERSION ) {
			return MigrationStatus::UP_TO_DATE;
		}
		return self::runner( $versions )->run();
	}

	/**
	 * Build the migration runner from WordPress services.
	 *
	 * @param SchemaVersionStore|null $versions Optional version-store instance.
	 */
	private static function runner( ?SchemaVersionStore $versions = null ): MigrationRunner {
		global $wpdb;

		$connection = new WpdbConnection( $wpdb );
		$tables     = new TableNames( $connection->prefix() );
		$versions ??= new WordPressSchemaVersionStore();

		return new MigrationRunner(
			$connection,
			$versions,
			new WpdbNamedMigrationLock( $connection ),
			array(
				new V001CreateSourcesTable( $tables ),
				new V002CreateDocumentsTable( $tables ),
				new V003CreateVectorCollectionsTable( $tables ),
				new V004CreateVectorsTable( $tables ),
			)
		);
	}
}
