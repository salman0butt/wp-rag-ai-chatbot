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
	 * @param bool $delete Supports raw vector delete.
	 * @param bool $search Supports raw vector search.
	 * @param bool $managed_ingestion Supports managed file ingestion/deletion.
	 * @param bool $managed_search Supports provider-managed text search.
	 */
	public function __construct(
		public readonly bool $upsert,
		public readonly bool $delete,
		public readonly bool $search,
		public readonly bool $managed_ingestion = false,
		public readonly bool $managed_search = false
	) {
	}

	/** Return a store with no optional operations. */
	public static function none(): self {
		return new self( false, false, false );
	}

	/** Return a store with all raw-vector operations. */
	public static function all(): self {
		return new self( true, true, true );
	}

	/** Return a managed store without falsely advertising raw-vector operations. */
	public static function managed(): self {
		return new self( false, false, false, true, true );
	}
}
