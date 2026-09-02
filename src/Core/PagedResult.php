<?php
/**
 * Immutable pagination result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Core;

use InvalidArgumentException;

// phpcs:disable WordPress.NamingConventions -- Public DTO property names follow the approved domain contract.
/**
 * Carries a bounded page of repository records.
 */
final readonly class PagedResult {
	/**
	 * Create a page result.
	 *
	 * @param array<int, mixed> $items Page items.
	 * @param int               $total Total matching records.
	 * @param int               $page Current one-based page.
	 * @param int               $perPage Effective page size.
	 */
	public function __construct(
		public array $items,
		public int $total,
		public int $page,
		public int $perPage
	) {
		if ( $page < 1 ) {
			throw new InvalidArgumentException( 'Page must be at least 1.' );
		}
		if ( $perPage < 1 ) {
			throw new InvalidArgumentException( 'Per-page value must be at least 1.' );
		}
	}
}
// phpcs:enable WordPress.NamingConventions
