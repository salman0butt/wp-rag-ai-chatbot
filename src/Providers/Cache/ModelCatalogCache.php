<?php
/**
 * Model catalog cache contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Cache;

use WpRagAiChatbot\Providers\ModelInfo;

/**
 * Provider-scoped cache for normalized model catalogs.
 */
interface ModelCatalogCache {
	/**
	 * Return a cached normalized model catalog when present.
	 *
	 * @param string $provider_id Stable provider identifier.
	 * @return ModelInfo[]|null
	 */
	public function get( string $provider_id ): ?array;

	/**
	 * Persist a normalized model catalog.
	 *
	 * @param string      $provider_id Stable provider identifier.
	 * @param ModelInfo[] $models Normalized model catalog.
	 */
	public function put( string $provider_id, array $models ): void;

	/**
	 * Remove a provider model catalog from cache.
	 *
	 * @param string $provider_id Stable provider identifier.
	 */
	public function invalidate( string $provider_id ): void;
}
