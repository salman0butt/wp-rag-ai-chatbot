<?php
/**
 * WooCommerce catalog snapshot tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\WooCommerce\Catalog;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceCatalogGateway;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceProduct;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceVariation;

/**
 * Verifies the stable WooCommerce catalog contracts and invariants.
 */
final class WooCommerceProductTest extends TestCase {
	/** Task 1 must expose a WooCommerce-independent catalog gateway contract. */
	public function test_catalog_gateway_contract_exists(): void {
		self::assertTrue( interface_exists( WooCommerceCatalogGateway::class ) );
	}

	/** Product snapshots expose stable catalog facts in deterministic order only. */
	public function test_product_snapshot_normalizes_stable_catalog_facts(): void {
		self::assertTrue( class_exists( WooCommerceProduct::class ) );
		self::assertTrue( class_exists( WooCommerceVariation::class ) );

		if ( ! class_exists( WooCommerceProduct::class ) || ! class_exists( WooCommerceVariation::class ) ) {
			return;
		}

		$product = new WooCommerceProduct(
			id: 42,
			type: 'variable',
			status: 'publish',
			catalogVisibility: 'visible',
			name: 'Trail Shoe',
			shortDescription: 'Light trail shoe.',
			description: 'Stable descriptive copy.',
			sku: 'TRAIL-42',
			canonicalUrl: 'https://example.test/product/trail-shoe/',
			categories: array( 'Outdoor', 'Shoes', 'Outdoor' ),
			tags: array( 'Trail', 'Lightweight', 'Trail' ),
			attributes: array(
				'Size'  => array( '44', '42', '44' ),
				'Color' => array( 'Red', 'Blue' ),
			),
			variations: array(
				new WooCommerceVariation(
					84,
					'TRAIL-42-RED',
					array(
						'Color' => 'Red',
						'Size'  => '42',
					)
				),
				new WooCommerceVariation(
					81,
					'TRAIL-42-BLUE',
					array(
						'Size'  => '42',
						'Color' => 'Blue',
					)
				),
			),
			modifiedGmt: '2026-09-03T00:00:00+00:00'
		);

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain record API follows approved camelCase contract.
		self::assertSame( 'publish', $product->status );
		self::assertSame( 'visible', $product->catalogVisibility );
		self::assertSame( array( 'Outdoor', 'Shoes' ), $product->categories );
		self::assertSame( array( 'Lightweight', 'Trail' ), $product->tags );
		self::assertSame(
			array(
				'Color' => array( 'Blue', 'Red' ),
				'Size'  => array( '42', '44' ),
			),
			$product->attributes
		);
		self::assertSame( array( 81, 84 ), array_map( static fn ( WooCommerceVariation $variation ): int => $variation->id, $product->variations ) );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/** Live commerce state must not exist on the stable snapshot contract. */
	public function test_product_snapshot_has_no_live_or_customer_state_fields(): void {
		self::assertTrue( class_exists( WooCommerceProduct::class ) );
		if ( ! class_exists( WooCommerceProduct::class ) ) {
			return;
		}

		foreach ( array( 'price', 'regularPrice', 'salePrice', 'stockQuantity', 'stockStatus', 'purchasable', 'customerId', 'orderId', 'discounts' ) as $forbidden ) {
			self::assertFalse( property_exists( WooCommerceProduct::class, $forbidden ), 'Stable catalog snapshot leaked live/customer field: ' . $forbidden );
		}
	}

	/** Product identity must be positive. */
	public function test_product_snapshot_rejects_non_positive_product_id(): void {
		self::assertTrue( class_exists( WooCommerceProduct::class ) );
		if ( ! class_exists( WooCommerceProduct::class ) ) {
			return;
		}

		$this->expectException( InvalidArgumentException::class );
		$this->product( id: 0 );
	}

	/** Product names are required descriptive identity. */
	public function test_product_snapshot_rejects_blank_name(): void {
		self::assertTrue( class_exists( WooCommerceProduct::class ) );
		if ( ! class_exists( WooCommerceProduct::class ) ) {
			return;
		}

		$this->expectException( InvalidArgumentException::class );
		$this->product( name: '   ' );
	}

	/** M06 accepts only the explicitly supported product kinds. */
	public function test_product_snapshot_rejects_unsupported_product_type(): void {
		self::assertTrue( class_exists( WooCommerceProduct::class ) );
		if ( ! class_exists( WooCommerceProduct::class ) ) {
			return;
		}

		$this->expectException( InvalidArgumentException::class );
		$this->product( type: 'external' );
	}

	/** Product eligibility snapshot fields must stay within approved public states. */
	public function test_product_snapshot_rejects_non_public_status_or_catalog_visibility(): void {
		self::assertTrue( class_exists( WooCommerceProduct::class ) );
		if ( ! class_exists( WooCommerceProduct::class ) ) {
			return;
		}

		$this->expectException( InvalidArgumentException::class );
		$this->product( status: 'private' );
	}

	/** Duplicate variation IDs must fail closed rather than create ambiguous identity. */
	public function test_product_snapshot_rejects_duplicate_variation_ids(): void {
		$this->expectException( InvalidArgumentException::class );

		new WooCommerceProduct(
			id: 42,
			type: 'variable',
			status: 'publish',
			catalogVisibility: 'visible',
			name: 'Trail Shoe',
			shortDescription: 'Light trail shoe.',
			description: 'Stable descriptive copy.',
			sku: 'TRAIL-42',
			canonicalUrl: 'https://example.test/product/trail-shoe/',
			categories: array( 'Shoes' ),
			tags: array( 'Trail' ),
			attributes: array( 'Color' => array( 'Blue', 'Red' ) ),
			variations: array(
				new WooCommerceVariation( 81, 'TRAIL-42-BLUE', array( 'Color' => 'Blue' ) ),
				new WooCommerceVariation( 81, 'TRAIL-42-RED', array( 'Color' => 'Red' ) ),
			),
			modifiedGmt: '2026-09-03T00:00:00+00:00'
		);
	}

	/** Variation identity and options are deterministic stable facts. */
	public function test_variation_snapshot_validates_identity_and_normalizes_options(): void {
		self::assertTrue( class_exists( WooCommerceVariation::class ) );
		if ( ! class_exists( WooCommerceVariation::class ) ) {
			return;
		}

		$variation = new WooCommerceVariation(
			81,
			'TRAIL-42-BLUE',
			array(
				'Size'  => '42',
				'Color' => 'Blue',
			)
		);

		self::assertSame(
			array(
				'Color' => 'Blue',
				'Size'  => '42',
			),
			$variation->attributes
		);

		$this->expectException( InvalidArgumentException::class );
		new WooCommerceVariation( 0, null, array() );
	}

	/**
	 * Build a valid product snapshot with selected overrides.
	 *
	 * @param int    $id Product ID override.
	 * @param string $type Product type override.
	 * @param string $status Product status override.
	 * @param string $catalog_visibility Catalog visibility override.
	 * @param string $name Product name override.
	 */
	private function product(
		int $id = 42,
		string $type = 'simple',
		string $status = 'publish',
		string $catalog_visibility = 'visible',
		string $name = 'Trail Shoe'
	): WooCommerceProduct {
		return new WooCommerceProduct(
			id: $id,
			type: $type,
			status: $status,
			catalogVisibility: $catalog_visibility,
			name: $name,
			shortDescription: 'Light trail shoe.',
			description: 'Stable descriptive copy.',
			sku: 'TRAIL-42',
			canonicalUrl: 'https://example.test/product/trail-shoe/',
			categories: array( 'Shoes' ),
			tags: array( 'Trail' ),
			attributes: array( 'Color' => array( 'Blue' ) ),
			variations: array(),
			modifiedGmt: '2026-09-03T00:00:00+00:00'
		);
	}
}
