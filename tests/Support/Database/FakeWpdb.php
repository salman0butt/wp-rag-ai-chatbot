<?php
/**
 * Minimal wpdb runtime fixture for uninstall tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Database;

/**
 * Supplies only the wpdb behavior exercised by DatabaseUninstaller through WpdbConnection.
 */
final class FakeWpdb {
	/**
	 * WordPress table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Last insert identifier required by the adapter shape.
	 *
	 * @var int
	 */
	public int $insert_id = 0;

	/**
	 * Executed SQL statements.
	 *
	 * @var string[]
	 */
	public array $queries = array();

	/**
	 * Create a deterministic query-result fixture.
	 *
	 * @param int|false $query_result Result returned by query().
	 */
	public function __construct( private readonly int|false $query_result ) {
	}

	/**
	 * Prepare the identifier-only SQL used by uninstall cleanup.
	 *
	 * @param string $query SQL template.
	 * @param mixed  ...$args Placeholder values.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$prepared = $query;
		foreach ( $args as $arg ) {
			$identifier = '`' . str_replace( '`', '``', (string) $arg ) . '`';
			$prepared   = (string) preg_replace( '/%i/', $identifier, $prepared, 1 );
		}
		return $prepared;
	}

	/**
	 * Record and return the configured query result.
	 *
	 * @param string $query SQL statement.
	 */
	public function query( string $query ): int|false {
		$this->queries[] = $query;
		return $this->query_result;
	}
}
