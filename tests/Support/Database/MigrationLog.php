<?php
/**
 * Migration log fixture.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Database;

/**
 * Mutable migration execution log shared by test migrations.
 */
final class MigrationLog {
	/**
	 * Executed migration versions.
	 *
	 * @var int[]
	 */
	public array $versions = array();
}
