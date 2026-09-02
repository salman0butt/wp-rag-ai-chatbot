<?php
/**
 * Database uninstall policy.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Deletes plugin-owned database state only after explicit opt-in.
 */
final class DatabaseUninstaller {
	/**
	 * Run the destructive uninstall boundary when deletion was explicitly enabled.
	 *
	 * @throws DatabaseException When plugin-owned tables cannot be removed.
	 */
	public static function run(): void {
		$delete_data = get_option( DatabaseSchema::DELETE_DATA_OPTION, false );
		if ( ! self::is_delete_enabled( $delete_data ) ) {
			return;
		}

		global $wpdb;
		if ( ! $wpdb instanceof \wpdb ) {
			return;
		}

		$connection = new WpdbConnection( $wpdb );
		$tables     = new TableNames( $connection->prefix() );

		foreach ( $tables->all() as $table ) {
			$result = $connection->query( $connection->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
			if ( false === $result ) {
				throw new DatabaseException( 'Could not remove plugin database table during uninstall.' );
			}
		}

		delete_option( DatabaseSchema::VERSION_OPTION );
		delete_option( DatabaseSchema::DELETE_DATA_OPTION );
	}

	/**
	 * Accept only explicit true/one forms WordPress can persist for this setting.
	 *
	 * @param mixed $value Persisted option value.
	 */
	private static function is_delete_enabled( mixed $value ): bool {
		return in_array( $value, array( true, 1, '1' ), true );
	}

	/**
	 * Static service only.
	 */
	private function __construct() {
	}
}
