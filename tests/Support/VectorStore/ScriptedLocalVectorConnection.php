<?php
/**
 * Scripted database connection for local vector-store integration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\VectorStore;

use WpRagAiChatbot\Database\Connection;

/**
 * Records persistence calls while returning scripted query results.
 */
final class ScriptedLocalVectorConnection implements Connection {
	/**
	 * Prepared calls.
	 *
	 * @var array<int, array{query:string,args:array<int,mixed>}>
	 */
	public array $prepared_calls = array();

	/**
	 * Insert calls.
	 *
	 * @var array<int, array{table:string,data:array<string,mixed>}>
	 */
	public array $inserts = array();

	/**
	 * Update calls.
	 *
	 * @var array<int, array{table:string,data:array<string,mixed>,where:array<string,mixed>}>
	 */
	public array $updates = array();

	/**
	 * Delete calls.
	 *
	 * @var array<int, array{table:string,where:array<string,mixed>}>
	 */
	public array $deletes = array();

	/**
	 * Scripted row results.
	 *
	 * @var array<int, array<string,mixed>|null>
	 */
	public array $row_results = array();

	/**
	 * Scripted result sets.
	 *
	 * @var array<int, array<int, array<string,mixed>>>
	 */
	public array $result_sets = array();

	/**
	 * Scripted delete results.
	 *
	 * @var list<int|bool>
	 */
	public array $delete_results = array();

	/**
	 * Return the test site prefix.
	 */
	public function prefix(): string {
		return 'wp_';
	}

	/**
	 * Return the test database name.
	 */
	public function database_name(): string {
		return 'wordpress_test';
	}

	/**
	 * Return the test charset and collation.
	 */
	public function charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	/**
	 * Record a prepared statement.
	 *
	 * @param string $query SQL with placeholders.
	 * @param mixed  ...$args Placeholder arguments.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared_calls[] = array(
			'query' => $query,
			'args'  => $args,
		);
		return $query;
	}

	/**
	 * Simulate a direct query.
	 *
	 * @param string $query SQL statement.
	 */
	public function query( string $query ): int|bool {
		return 0;
	}

	/**
	 * Return no scalar result.
	 *
	 * @param string $query SQL statement.
	 */
	public function get_var( string $query ): string|int|float|null {
		return null;
	}

	/**
	 * Return the next scripted row.
	 *
	 * @param string $query SQL statement.
	 * @return array<string,mixed>|null
	 */
	public function get_row( string $query ): ?array {
		return array_shift( $this->row_results );
	}

	/**
	 * Return the next scripted result set.
	 *
	 * @param string $query SQL statement.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_results( string $query ): array {
		return array_shift( $this->result_sets ) ?? array();
	}

	/**
	 * Record an insert.
	 *
	 * @param string               $table Table name.
	 * @param array<string,mixed>  $data Row values.
	 * @param string[]             $format Value formats.
	 */
	public function insert( string $table, array $data, array $format = array() ): int|bool {
		$this->inserts[] = array(
			'table' => $table,
			'data'  => $data,
		);
		return 1;
	}

	/**
	 * Record an update.
	 *
	 * @param string               $table Table name.
	 * @param array<string,mixed>  $data Row values.
	 * @param array<string,mixed>  $where Match values.
	 * @param string[]             $format Value formats.
	 * @param string[]             $where_format Match formats.
	 */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int|bool {
		$this->updates[] = array(
			'table' => $table,
			'data'  => $data,
			'where' => $where,
		);
		return 1;
	}

	/**
	 * Record a delete.
	 *
	 * @param string               $table Table name.
	 * @param array<string,mixed>  $where Match values.
	 * @param string[]             $where_format Match formats.
	 */
	public function delete( string $table, array $where, array $where_format = array() ): int|bool {
		$this->deletes[] = array(
			'table' => $table,
			'where' => $where,
		);
		return array_shift( $this->delete_results ) ?? 0;
	}

	/**
	 * Return a test insert identifier.
	 */
	public function insert_id(): int {
		return 1;
	}

	/**
	 * Simulate dbDelta.
	 *
	 * @param string $sql DDL statement.
	 * @return array<int|string,mixed>
	 */
	public function db_delta( string $sql ): array {
		return array();
	}

	/**
	 * Report test tables as present.
	 *
	 * @param string $table Table name.
	 */
	public function table_exists( string $table ): bool {
		return true;
	}
}
