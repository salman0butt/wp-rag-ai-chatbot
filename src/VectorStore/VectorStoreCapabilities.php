<?php
/**
 * Vector-store capability declaration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

/**
 * Immutable truthful operation capability flags.
 */
final class VectorStoreCapabilities {
	/**
	 * Create capability flags.
	 *
	 * @param bool $upsert Supports raw vector upsert.
	 * @param bool $delete Supports vector delete.
	 * @param bool $search Supports raw vector search.
	 */
	public function __construct(
		public readonly bool $upsert,
		public readonly bool $delete,
		public readonly bool $search
	) {
	}

	/**
	 * Return a store with no optional operations.
	 */
	public static function none(): self {
		return new self( false, false, false );
	}

	/**
	 * Return a store with all raw-vector operations.
	 */
	public static function all(): self {
		return new self( true, true, true );
	}
}
