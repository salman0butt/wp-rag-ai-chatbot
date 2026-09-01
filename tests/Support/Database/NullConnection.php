<?php
/**
 * Null database connection fixture.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Database;

use WpRagAiChatbot\Database\Connection;

/**
 * No-op database connection for pure migration runner tests.
 */
final class NullConnection implements Connection {
	/** Get the fixture site prefix. */
	public function prefix(): string {
		return 'wp_';
	}

	/** Get the fixture database name. */
	public function database_name(): string {
		return 'wordpress_db';
	}

	/** Get the fixture charset/collation. */
	public function charset_collate(): string {
		return '';
	}

	/**
	 * Return query unchanged.
	 *
	 * @param string $query SQL statement.
	 * @param mixed  ...$args Placeholder values.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		return $query;
	}

	/**
	 * Execute no-op query.
	 *
	 * @param string $query SQL statement.
	 */
	public function query( string $query ): int|bool {
		return 0;
	}

	/**
	 * Return no scalar.
	 *
	 * @param string $query SQL statement.
	 */
	public function get_var( string $query ): string|int|float|null {
		return null;
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

	/** Get the fixture insert identifier. */
	public function insert_id(): int {
		return 1;
	}

	/**
	 * Simulate dbDelta success.
	 *
	 * @param string $sql DDL statement.
	 * @return array<int|string, mixed>
	 */
	public function db_delta( string $sql ): array {
		return array();
	}

	/**
	 * Report table existence.
	 *
	 * @param string $table Table name.
	 */
	public function table_exists( string $table ): bool {
		return true;
	}
}
