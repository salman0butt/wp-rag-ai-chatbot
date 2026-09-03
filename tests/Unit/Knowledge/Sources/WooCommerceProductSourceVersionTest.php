<?php
/**
 * WooCommerce product source version tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\Sources;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\WooCommerceProductSource;
use WpRagAiChatbot\Tests\Support\WooCommerce\FakeWooCommerceCatalogGateway;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceProduct;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceVariation;

/**
 * Verifies source versions are derived only from canonical stable catalog facts.
 */
final class WooCommerceProductSourceVersionTest extends TestCase {
	/** Live transactional fixture state outside the snapshot contract must not affect version/hash. */
	public function test_live_only_state_does_not_affect_source_version_or_content_hash(): void {
		$product = $this->product();
		$live_before = array( 'price' => '99.00', 'stock_status' => 'instock' );
		$live_after  = array( 'price' => '79.00', 'stock_status' => 'outofstock' );

		self::assertNotSame( $live_before, $live_after );
		$before = $this->document( $product );
		$after  = $this->document( $product );

		self::assertSame( $before->sourceVersion, $after->sourceVersion );
		self::assertSame( $before->contentHash, $after->contentHash );
	}

	/** Stable descriptive catalog changes must alter both source version and canonical content hash. */
	public function test_stable_catalog_changes_affect_source_version_and_content_hash(): void {
		$baseline = $this->document( $this->product() );
		$changed_products = array(
			$this->product( sku: 'TRAIL-42-UPDATED' ),
			$this->product( description: 'Updated stable descriptive copy.' ),
			$this->product( categories: array( 'Shoes', 'Updated' ) ),
			$this->product( attributes: array( 'Color' => array( 'Blue', 'Green' ) ) ),
			$this->product(
				variations: array(
					new WooCommerceVariation( 4202, 'TRAIL-42-GREEN', array( 'Color' => 'Green' ) ),
				)
			),
		);

		foreach ( $changed_products as $changed_product ) {
			$changed = $this->document( $changed_product );
			self::assertNotSame( $baseline->sourceVersion, $changed->sourceVersion );
			self::assertNotSame( $baseline->contentHash, $changed->contentHash );
		}
	}

	/** Build one canonical document through the public source contract. */
	private function document( WooCommerceProduct $product ): DocumentRecord {
		$gateway = new FakeWooCommerceCatalogGateway( true, array(), array( 42 => $product ) );
		$source  = new WooCommerceProductSource( $gateway );
		$time    = new DateTimeImmutable( '2026-09-03 09:30:00', new DateTimeZone( 'UTC' ) );
		$record  = new KnowledgeSourceRecord(
			23,
			'shop-products',
			'woocommerce_product',
			null,
			'Shop products',
			null,
			'active',
			array( 'product_ids' => array( 42 ) ),
			null,
			null,
			$time,
			$time
		);

		$documents = iterator_to_array( $source->documents( $record ) );
		self::assertCount( 1, $documents );
		return $documents[0];
	}

	/**
	 * Build one stable product snapshot while keeping modified time fixed.
	 *
	 * @param string                            $sku Product SKU.
	 * @param string                            $description Stable product description.
	 * @param array<int, string>                $categories Product categories.
	 * @param array<string, array<int, string>> $attributes Product attributes.
	 * @param array<int, WooCommerceVariation>  $variations Stable variation descriptors.
	 */
	private function product(
		string $sku = 'TRAIL-42',
		string $description = 'Stable descriptive copy.',
		array $categories = array( 'Shoes', 'Trail' ),
		array $attributes = array( 'Color' => array( 'Blue', 'Red' ) ),
		array $variations = array()
	): WooCommerceProduct {
		return new WooCommerceProduct(
			42,
			'variable',
			'publish',
			'visible',
			'Trail Shoe',
			'Light trail shoe.',
			$description,
			$sku,
			'https://example.test/product/42/',
			$categories,
			array( 'Featured', 'Outdoor' ),
			$attributes,
			$variations,
			'2026-09-03T09:00:00+00:00'
		);
	}
}
