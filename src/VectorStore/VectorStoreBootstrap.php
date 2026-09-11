<?php
/**
 * Shared production vector-store runtime authority.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

/**
 * Owns the single process-local registry used by production vector consumers.
 *
 * Concrete adapter composition remains at WordPress runtime boundaries; consumers
 * such as indexing and Playground resolve stores through this shared authority
 * rather than constructing request-local registries.
 */
final class VectorStoreBootstrap {
	/**
	 * Shared process-local registry.
	 *
	 * @var VectorStoreRegistry|null
	 */
	private static ?VectorStoreRegistry $registry = null;

	/** Return the shared production registry without performing network I/O. */
	public static function registry(): VectorStoreRegistry {
		self::$registry ??= new VectorStoreRegistry();

		return self::$registry;
	}
}
