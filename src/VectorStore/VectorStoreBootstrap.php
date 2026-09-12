<?php
/**
 * Shared production vector-store runtime authority.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\VectorStore\Local\LocalVectorStore;
use WpRagAiChatbot\VectorStore\Local\LocalVectorStoreConfig;

/**
 * Owns the single process-local registry used by production vector consumers.
 *
 * Consumers such as indexing and Playground resolve stores through this shared
 * authority rather than constructing request-local registries.
 */
final class VectorStoreBootstrap {
	/**
	 * Shared process-local registry.
	 *
	 * @var VectorStoreRegistry|null
	 */
	private static ?VectorStoreRegistry $registry = null;

	/**
	 * Whether the local WordPress adapter has been registered.
	 *
	 * @var bool
	 */
	private static bool $local_registered = false;

	/** Return the shared production registry without performing network I/O. */
	public static function registry(): VectorStoreRegistry {
		self::$registry ??= new VectorStoreRegistry();

		return self::$registry;
	}

	/**
	 * Register the production local WordPress vector adapter exactly once.
	 *
	 * @param Connection             $connection Database connection.
	 * @param TableNames             $tables Plugin table names.
	 * @param LocalVectorStoreConfig $config Bounded local-search configuration.
	 */
	public static function register_local(
		Connection $connection,
		TableNames $tables,
		LocalVectorStoreConfig $config
	): void {
		if ( self::$local_registered ) {
			return;
		}

		self::registry()->register( new LocalVectorStore( $connection, $tables, $config ) );
		self::$local_registered = true;
	}
}
