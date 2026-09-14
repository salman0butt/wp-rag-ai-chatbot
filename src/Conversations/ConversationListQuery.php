<?php
/**
 * Bounded conversation administration list query.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

/**
 * Immutable normalized pagination authority for conversation administration reads.
 */
final readonly class ConversationListQuery {
	private const MAX_PAGE_SIZE = 100;

	/**
	 * Create a bounded list query.
	 *
	 * @param int $page Requested one-based page.
	 * @param int $page_size Requested rows per page.
	 */
	public function __construct(
		public int $page = 1,
		public int $page_size = 25
	) {
		$this->page      = max( 1, $page );
		$this->page_size = min( self::MAX_PAGE_SIZE, max( 1, $page_size ) );
	}
}
