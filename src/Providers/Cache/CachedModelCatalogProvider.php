<?php
/**
 * Cached model catalog provider decorator.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Cache;

use WpRagAiChatbot\Providers\ModelCatalogProvider;

/**
 * Adds provider-scoped model catalog caching without changing upstream adapters.
 */
final class CachedModelCatalogProvider implements ModelCatalogProvider {
	/**
	 * Stable provider identifier captured from upstream.
	 */
	private readonly string $provider_id;

	/**
	 * Create a cached model catalog provider.
	 *
	 * @param ModelCatalogProvider $upstream Normalized upstream model catalog provider.
	 * @param ModelCatalogCache    $cache Provider-scoped model cache.
	 */
	public function __construct(
		private readonly ModelCatalogProvider $upstream,
		private readonly ModelCatalogCache $cache
	) {
		$this->provider_id = $upstream->provider_id();
	}

	/**
	 * Return the stable provider ID.
	 */
	public function provider_id(): string {
		return $this->provider_id;
	}

	/**
	 * Return the cached catalog or populate it on a miss.
	 *
	 * @return \WpRagAiChatbot\Providers\ModelInfo[]
	 */
	public function models(): array {
		$cached = $this->cache->get( $this->provider_id );
		if ( null !== $cached ) {
			return $cached;
		}

		return $this->refresh();
	}

	/**
	 * Force upstream discovery and replace cache only after success.
	 *
	 * @return \WpRagAiChatbot\Providers\ModelInfo[]
	 */
	public function refresh(): array {
		$models = $this->upstream->models();
		$this->cache->put( $this->provider_id, $models );
		return $models;
	}

	/**
	 * Remove the provider-scoped model catalog cache entry.
	 */
	public function invalidate(): void {
		$this->cache->invalidate( $this->provider_id );
	}
}
