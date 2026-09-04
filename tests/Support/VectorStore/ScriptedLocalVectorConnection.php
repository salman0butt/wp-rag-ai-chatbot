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
	/** @var array<int, array{query:string,args:array<int,mixed>}> */
	public array $prepared_calls = array();
	/** @var array<int, array{table:string,data:array<string,mixed>}> */
	public array $inserts = array();
	/** @var array<int, array{table:string,data:array<string,mixed>,where:array<string,mixed>}> */
	public array $updates = array();
	/** @var array<int, array{table:string,where:array<string,mixed>}> */
	public array $deletes = array();
	/** @var array<int, array<string,mixed>|null> */
	public array $row_results = array();
	/** @var array<int, array<int, array<string,mixed>>> */
	public array $result_sets = array();
	/** @var list<int|bool> */
	public array $delete_results = array();

	public function prefix(): string { return 'wp_'; }
	public function database_name(): string { return 'wordpress'; }
	public function charset_collate(): string { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }

	/** @param literal-string $query SQL with placeholders. */
	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared_calls[] = array( 'query' => $query, 'args' => $args );
		return $query;
	}

	public function query( string $query ): int|bool { return 0; }
	public function get_var( string $query ): string|int|float|null { return null; }

	/** @return array<string,mixed>|null */
	public function get_row( string $query ): ?array {
		return array_shift( $this->row_results );
	}

	/** @return array<int,array<string,mixed>> */
	public function get_results( string $query ): array {
		return array_shift( $this->result_sets ) ?? array();
	}

	/** @param array<string,mixed> $data @param string[] $format */
	public function insert( string $table, array $data, array $format = array() ): int|bool {
		$this->inserts[] = array( 'table' => $table, 'data' => $data );
		return 1;
	}

	/** @param array<string,mixed> $data @param array<string,mixed> $where @param string[] $format @param string[] $where_format */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int|bool {
		$this->updates[] = array( 'table' => $table, 'data' => $data, 'where' => $where );
		return 1;
	}

	/** @param array<string,mixed> $where @param string[] $where_format */
	public function delete( string $table, array $where, array $where_format = array() ): int|bool {
		$this->deletes[] = array( 'table' => $table, 'where' => $where );
		return array_shift( $this->delete_results ) ?? 0;
	}

	public function insert_id(): int { return 1; }
	/** @return array<int|string,mixed> */
	public function db_delta( string $sql ): array { return array(); }
	public function table_exists( string $table ): bool { return true; }
}
