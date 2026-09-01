<?php
/**
 * Recording database connection fixture.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Database;

use WpRagAiChatbot\Database\Connection;

/**
 * Records database calls for adapter and migration unit tests.
 */
final class RecordingConnection implements Connection {
	/**
	 * Prepared statement calls.
	 *
	 * @var array<int, array{query: string, args: array<int, mixed>}>
	 */
	public array $prepared_calls = array();

	/**
	 * Scalar queries received by get_var().
	 *
	 * @var string[]
	 */
	public array $get_var_queries = array();

	/**
	 * DDL statements received by db_delta().
	 *
	 * @var string[]
	 */
	public array $db_delta_queries = array();

	/**
	 * Create the recording connection.
	 *
	 * @param string                $database_name Database name.
	 * @param string                $prefix Site prefix.
	 * @param string|int|float|null $get_var_result Scalar result returned by get_var().
	 * @param bool                  $table_exists Whether table checks succeed.
	 */
	public function __construct(
		private readonly string $database_name = 'wordpress_db',
		private readonly string $prefix = 'wp_',
		private string|int|float|null $get_var_result = null,
		private readonly bool $table_exists = true
	) {
	}

	/** Get the fixture site prefix. */
	public function prefix(): string {
		return $this->prefix;
	}

	/** Get the fixture database name. */
	public function database_name(): string {
		return $this->database_name;
	}

	/** Get the fixture charset/collation. */
	public function charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	/**
	 * Record a prepared statement call.
	 *
	 * @param string $query SQL statement.
	 * @param mixed  ...$args Placeholder values.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared_calls[] = array(
			'query' => $query,
			'args'  => $args,
		);

		return $query;
	}

	/**
	 * Simulate a query execution.
	 *
	 * @param string $query SQL statement.
	 */
	public function query( string $query ): int|bool {
		return 0;
	}

	/**
	 * Record and return the configured scalar result.
	 *
	 * @param string $query SQL statement.
	 */
	public function get_var( string $query ): string|int|float|null {
		$this->get_var_queries[] = $query;
		return $this->get_var_result;
	}

	/**
	 * Return no row.
	 *
	 * @param string $query SQL statement.
	 * @return array<string, mixed>|null
	 */
	public function get_row( string $query ): ?array {
		return null;
	}

	/**
	 * Return no rows.
	 *
	 * @param string $query SQL statement.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( string $query ): array {
		return array();
	}

	/**
	 * Simulate insert success.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Row values.
	 * @param string[]             $format Value formats.
	 */
	public function insert( string $table, array $data, array $format = array() ): int|bool {
		return 1;
	}

	/**
	 * Simulate update success.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Row values.
	 * @param array<string, mixed> $where Match values.
	 * @param string[]             $format Value formats.
	 * @param string[]             $where_format Match formats.
	 */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int|bool {
		return 1;
	}

	/**
	 * Simulate delete success.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $where Match values.
	 * @param string[]             $where_format Match formats.
	 */
	public function delete( string $table, array $where, array $where_format = array() ): int|bool {
		return 1;
	}

	/** Return a fixture insert identifier. */
	public function insert_id(): int {
		return 1;
	}

	/**
	 * Record a dbDelta statement.
	 *
	 * @param string $sql DDL statement.
	 * @return array<int|string, mixed>
	 */
	public function db_delta( string $sql ): array {
		$this->db_delta_queries[] = $sql;
		return array();
	}

	/**
	 * Return the configured table-existence result.
	 *
	 * @param string $table Table name.
	 */
	public function table_exists( string $table ): bool {
		return $this->table_exists;
	}
}
