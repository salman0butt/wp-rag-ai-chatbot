<?php
/**
 * Fake WooCommerce catalog gateway for source tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\WooCommerce;

use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceCatalogGateway;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceProduct;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Method parameters follow the approved application contract.
/**
 * Deterministic in-memory WooCommerce catalog gateway with observable calls.
 */
final class FakeWooCommerceCatalogGateway implements WooCommerceCatalogGateway {
	/**
	 * Product ID page calls.
	 *
	 * @var array<int, array{page:int,perPage:int}>
	 */
	public array $product_id_calls = array();

	/**
	 * Product lookup calls.
	 *
	 * @var array<int, int>
	 */
	public array $product_calls = array();

	/**
	 * Create the fake gateway.
	 *
	 * @param bool                               $available Whether the gateway is available.
	 * @param array<int, array<int, int>>        $pages Product IDs keyed by one-based page.
	 * @param array<int, WooCommerceProduct>     $products Product snapshots keyed by product ID.
	 */
	public function __construct(
		private bool $available,
		private array $pages = array(),
		private array $products = array()
	) {
	}

	/** Report configured availability. */
	public function isAvailable(): bool {
		return $this->available;
	}

	/**
	 * Return one configured product snapshot.
	 *
	 * @param int $productId Product ID.
	 */
	public function product( int $productId ): ?WooCommerceProduct {
		$this->product_calls[] = $productId;
		return $this->products[ $productId ] ?? null;
	}

	/**
	 * Return one configured deterministic page.
	 *
	 * @param int $page One-based page.
	 * @param int $perPage Requested page size.
	 * @return array<int, int>
	 */
	public function productIds( int $page, int $perPage ): array {
		$this->product_id_calls[] = array(
			'page'    => $page,
			'perPage' => $perPage,
		);

		return $this->pages[ $page ] ?? array();
	}
}
// phpcs:enable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
