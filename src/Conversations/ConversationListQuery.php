<?php
/**
 * Bounded conversation administration list query.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

use InvalidArgumentException;

/**
 * Immutable normalized pagination and filter authority for conversation administration reads.
 */
final readonly class ConversationListQuery {
	private const MAX_PAGE_SIZE = 100;
	private const MAX_SEARCH_LENGTH = 200;

	/** One-based page number. */
	public int $page;

	/** Bounded rows per page. */
	public int $page_size;

	/** Optional concrete bot identifier. */
	public ?string $bot_id;

	/** Whether only historical conversations without a bot association are requested. */
	public bool $unassigned_only;

	/** Optional inclusive lower date boundary. */
	public ?string $date_from;

	/** Optional inclusive upper date boundary. */
	public ?string $date_to;

	/** Optional bounded search text. */
	public ?string $search;

	/**
	 * Create a bounded list query.
	 *
	 * @param int         $page Requested one-based page.
	 * @param int         $page_size Requested rows per page.
	 * @param string|null $bot_id Optional bot identifier.
	 * @param bool        $unassigned_only Whether to select only unassigned conversations.
	 * @param string|null $date_from Optional inclusive lower date boundary.
	 * @param string|null $date_to Optional inclusive upper date boundary.
	 * @param string|null $search Optional search text.
	 */
	public function __construct(
		int $page = 1,
		int $page_size = 25,
		?string $bot_id = null,
		bool $unassigned_only = false,
		?string $date_from = null,
		?string $date_to = null,
		?string $search = null
	) {
		$bot_id    = self::normalizeOptionalString( $bot_id );
		$date_from = self::normalizeOptionalString( $date_from );
		$date_to   = self::normalizeOptionalString( $date_to );
		$search    = self::normalizeOptionalString( $search );

		if ( null !== $bot_id && $unassigned_only ) {
			throw new InvalidArgumentException( 'A bot filter cannot be combined with the unassigned filter.' );
		}

		if ( null !== $date_from && null !== $date_to && $date_from > $date_to ) {
			throw new InvalidArgumentException( 'Conversation date range is reversed.' );
		}

		$this->page            = max( 1, $page );
		$this->page_size       = min( self::MAX_PAGE_SIZE, max( 1, $page_size ) );
		$this->bot_id          = $bot_id;
		$this->unassigned_only = $unassigned_only;
		$this->date_from       = $date_from;
		$this->date_to         = $date_to;
		$this->search          = null === $search ? null : mb_substr( $search, 0, self::MAX_SEARCH_LENGTH );
	}

	/** Normalize optional scalar filter text without broadening empty values into filters. */
	private static function normalizeOptionalString( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( $value );

		return '' === $value ? null : $value;
	}
}
