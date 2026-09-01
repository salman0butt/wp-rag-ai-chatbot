<?php
/**
 * Database connection contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Narrow persistence operations required by migrations and repositories.
 */
interface Connection {
	/**
	 * Get the current WordPress site table prefix.
	 */
	public function prefix(): string;

	/**
	 * Get the current database name.
	 */
	public function database_name(): string;

	/**
	 * Get the WordPress charset/collation DDL fragment.
	 */
	public function charset_collate(): string;

	/**
	 * Prepare a SQL statement.
	 *
	 * @param string $query SQL with WordPress placeholders.
	 * @param mixed  ...$args Placeholder values.
	 */
	public function prepare( string $query, mixed ...$args ): string;

	/**
	 * Execute a SQL statement.
	 *
	 * @param string $query SQL statement.
	 */
	public function query( string $query ): int|bool;

	/**
	 * Fetch one scalar value.
	 *
	 * @param string $query SQL statement.
	 */
	public function get_var( string $query ): string|int|float|null;

	/**
	 * Fetch one associative row.
	 *
	 * @param string $query SQL statement.
	 * @return array<string, mixed>|null
	 */
	public function get_row( string $query ): ?array;

	/**
	 * Fetch associative rows.
	 *
	 * @param string $query SQL statement.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( string $query ): array;

	/**
	 * Insert one row.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Row values.
	 * @param string[]             $format WordPress value formats.
	 */
	public function insert( string $table, array $data, array $format = array() ): int|bool;

	/**
	 * Update matching rows.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Row values.
	 * @param array<string, mixed> $where Match values.
	 * @param string[]             $format Value formats.
	 * @param string[]             $where_format Match formats.
	 */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int|bool;

	/**
	 * Delete matching rows.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $where Match values.
	 * @param string[]             $where_format Match formats.
	 */
	public function delete( string $table, array $where, array $where_format = array() ): int|bool;

	/**
	 * Return the last auto-increment identifier.
	 */
	public function insert_id(): int;

	/**
	 * Apply a WordPress dbDelta DDL statement.
	 *
	 * @param string $sql DDL statement.
	 * @return array<int|string, mixed>
	 */
	public function db_delta( string $sql ): array;

	/**
	 * Determine whether a table exists exactly.
	 *
	 * @param string $table Table name.
	 */
	public function table_exists( string $table ): bool;
}
