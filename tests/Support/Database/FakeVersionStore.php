<?php
/**
 * In-memory schema version store fixture.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Database;

use WpRagAiChatbot\Database\SchemaVersionStore;

/**
 * Records schema version writes for migration tests.
 */
final class FakeVersionStore implements SchemaVersionStore {
	/**
	 * Persisted version writes.
	 *
	 * @var int[]
	 */
	public array $writes = array();

	/**
	 * Create a version store.
	 *
	 * @param int $version Initial version.
	 */
	public function __construct( private int $version ) {
	}

	/**
	 * Read current version.
	 */
	public function current(): int {
		return $this->version;
	}

	/**
	 * Simulate a version change performed by another process without recording a local write.
	 *
	 * @param int $version Externally persisted version.
	 */
	public function advanceExternally( int $version ): void {
		$this->version = $version;
	}

	/**
	 * Save a version.
	 *
	 * @param int $version Applied version.
	 */
	public function save( int $version ): void {
		$this->version  = $version;
		$this->writes[] = $version;
	}
}
