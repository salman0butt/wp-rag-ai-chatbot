<?php
/**
 * Terminal job cleanup persistence contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use DateTimeImmutable;

/**
 * Deletes only terminal queue history before a cutoff.
 */
interface JobCleanupStore {
	/**
	 * Delete a bounded set of terminal jobs before the cutoff.
	 *
	 * @param DateTimeImmutable $before Cleanup cutoff.
	 * @param int               $limit Maximum rows to delete.
	 */
	public function delete_terminal_before( DateTimeImmutable $before, int $limit ): int;
}
