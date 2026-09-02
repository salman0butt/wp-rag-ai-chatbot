<?php
/**
 * WordPress wpdb connection adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Adapts the small database contract to WordPress's wpdb API.
 */
final class WpdbConnection implements Connection {
	/**
	 * Create the adapter.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 */
	public function __construct( private readonly \wpdb $wpdb ) {
	}

	/**
	 * Get the current site prefix.
	 */
	public function prefix(): string {
		return $this->wpdb->prefix;
	}

	/**
	 * Get the current database name.
	 */
	public function database_name(): string {
		return (string) DB_NAME;
	}

	/**
	 * Get the WordPress charset/collation fragment.
	 */
	public function charset_collate(): string {
		return $this->wpdb->get_charset_collate();
	}

	/**
	 * Prepare a SQL statement supplied by trusted repository or migration code.
	 *
	 * @param literal-string $query SQL with placeholders.
	 * @param mixed          ...$args Placeholder values.
	 * @throws DatabaseException When wpdb cannot prepare the statement.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$prepared = $this->wpdb->prepare( $query, ...$args );
		if ( ! is_string( $prepared ) ) {
			throw new DatabaseException( 'Could not prepare database query.' );
		}
		return $prepared;
	}

	/**
	 * Execute a SQL statement that was prepared by the caller when values are present.
	 *
	 * @param string $query SQL statement.
	 */
	public function query( string $query ): int|bool {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Connection callers prepare all value-bearing SQL before execution.
		return $this->wpdb->query( $query );
	}

	/**
	 * Fetch one scalar value from caller-prepared SQL.
	 *
	 * @param string $query SQL statement.
	 */
	public function get_var( string $query ): ?string {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Connection callers prepare all value-bearing SQL before execution.
		$value = $this->wpdb->get_var( $query );
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Fetch one associative row from caller-prepared SQL.
	 *
	 * @param string $query SQL statement.
	 * @return array<string, mixed>|null
	 */
	public function get_row( string $query ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Connection callers prepare all value-bearing SQL before execution.
		$row = $this->wpdb->get_row( $query, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fetch associative rows from caller-prepared SQL.
	 *
	 * @param string $query SQL statement.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( string $query ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Connection callers prepare all value-bearing SQL before execution.
		$rows = $this->wpdb->get_results( $query, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert one row.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Row values.
	 * @param string[]             $format Value formats.
	 */
	public function insert( string $table, array $data, array $format = array() ): int|bool {
		return $this->wpdb->insert( $table, $data, $format );
	}

	/**
	 * Update matching rows.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Row values.
	 * @param array<string, mixed> $where Match values.
	 * @param string[]             $format Value formats.
	 * @param string[]             $where_format Match formats.
	 */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int|bool {
		return $this->wpdb->update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * Delete matching rows.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $where Match values.
	 * @param string[]             $where_format Match formats.
	 */
	public function delete( string $table, array $where, array $where_format = array() ): int|bool {
		return $this->wpdb->delete( $table, $where, $where_format );
	}

	/**
	 * Return the last auto-increment identifier.
	 */
	public function insert_id(): int {
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Apply a WordPress dbDelta DDL statement.
	 *
	 * @param string $sql DDL statement.
	 * @return array<int|string, mixed>
	 */
	public function db_delta( string $sql ): array {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		return dbDelta( $sql );
	}

	/**
	 * Determine whether a table exists exactly.
	 *
	 * @param string $table Table name.
	 */
	public function table_exists( string $table ): bool {
		$sql = $this->prepare( 'SHOW TABLES LIKE %s', $table );
		return $table === $this->get_var( $sql );
	}
}
