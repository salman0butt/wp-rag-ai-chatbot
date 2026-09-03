<?php
/**
 * WooCommerce catalog gateway contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\WooCommerce\Catalog;

/**
 * Application-facing boundary for optional WooCommerce catalog access.
 */
interface WooCommerceCatalogGateway {
	/**
	 * Report whether supported WooCommerce catalog APIs are available.
	 */
	public function isAvailable(): bool;

	/**
	 * Load one eligible stable product snapshot.
	 *
	 * @param int $productId Product ID.
	 */
	public function product( int $productId ): ?WooCommerceProduct;

	/**
	 * Return one deterministic page of eligible product IDs.
	 *
	 * @param int $page One-based page number.
	 * @param int $perPage Products per page.
	 * @return list<int>
	 */
	public function productIds( int $page, int $perPage ): array;
}
