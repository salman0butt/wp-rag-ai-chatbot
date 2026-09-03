<?php
/**
 * WooCommerce product knowledge source tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\Sources;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSource;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;
use WpRagAiChatbot\Tests\Support\WooCommerce\FakeWooCommerceCatalogGateway;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceProduct;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceVariation;

/**
 * Verifies deterministic WooCommerce product source normalization.
 */
final class WooCommerceProductSourceTest extends TestCase {
	/** Disabled WooCommerce must be non-fatal and emit no documents. */
	public function test_disabled_gateway_yields_no_documents(): void {
		$gateway = new FakeWooCommerceCatalogGateway( false );

		$normalizer = $this->normalizer( $gateway );

		$documents = iterator_to_array( $normalizer->documents( $this->source( array( 'product_ids' => array( 42 ) ) ) ) );

		self::assertSame( 'woocommerce_product', $normalizer->type() );
		self::assertSame( array(), $documents );
		self::assertSame( array(), $gateway->product_calls );
	}

	/** Explicit product selection must map stable public facts into one canonical document. */
	public function test_explicit_product_id_maps_to_canonical_document(): void {
		$product = $this->product(
			42,
			'Trail Shoe',
			'variable',
			array(
				'Color' => array( 'Red', 'Blue' ),
				'Size'  => array( '43', '42' ),
			),
			array(
				new WooCommerceVariation(
					4202,
					null,
					array(
						'Size'  => '43',
						'Color' => 'Red',
					)
				),
				new WooCommerceVariation(
					4201,
					'TRAIL-42-BLUE',
					array(
						'Size'  => '42',
						'Color' => 'Blue',
					)
				),
			)
		);

		$gateway = new FakeWooCommerceCatalogGateway( true, array(), array( 42 => $product ) );

		$source = $this->source( array( 'product_ids' => array( 42 ) ) );

		$documents = iterator_to_array( $this->normalizer( $gateway )->documents( $source ) );

		self::assertCount( 1, $documents );
		$document = $documents[0];
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain records use the approved camelCase contract.
		self::assertSame( 'woocommerce_product:42', $document->documentKey );
		self::assertSame( '42', $document->externalId );
		self::assertSame( 'woocommerce_product', $document->documentType );
		self::assertSame( 'Trail Shoe', $document->title );
		self::assertSame( 'https://example.test/product/42/', $document->canonicalUrl );
		self::assertSame( 'public', $document->visibility );
		self::assertSame(
			"Trail Shoe\n\nSKU: TRAIL-42\n\nLight trail shoe.\n\nStable descriptive copy.\n\nCategories: Shoes, Trail\nTags: Featured, Outdoor\nAttributes:\nColor: Blue, Red\nSize: 42, 43\nVariations:\n4201 | SKU: TRAIL-42-BLUE | Color: Blue | Size: 42\n4202 | Color: Red | Size: 43",
			$document->content
		);
		self::assertSame(
			array(
				'source_type'        => 'woocommerce_product',
				'product_id'         => 42,
				'product_type'       => 'variable',
				'product_status'     => 'publish',
				'catalog_visibility' => 'visible',
				'sku'                => 'TRAIL-42',
				'categories'         => array( 'Shoes', 'Trail' ),
				'tags'               => array( 'Featured', 'Outdoor' ),
				'attributes'         => array(
					'Color' => array( 'Blue', 'Red' ),
					'Size'  => array( '42', '43' ),
				),
				'variations'         => array(
					array(
						'id'         => 4201,
						'sku'        => 'TRAIL-42-BLUE',
						'attributes' => array(
							'Color' => 'Blue',
							'Size'  => '42',
						),
					),
					array(
						'id'         => 4202,
						'sku'        => null,
						'attributes' => array(
							'Color' => 'Red',
							'Size'  => '43',
						),
					),
				),
			),
			$document->metadata
		);
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $document->contentHash );
		self::assertSame( $source->updatedAt, $document->createdAt );
		self::assertSame( $source->updatedAt, $document->updatedAt );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/** Catalog mode must page deterministically with a bounded configured page size. */
	public function test_catalog_mode_pages_until_a_short_page(): void {
		$gateway = new FakeWooCommerceCatalogGateway(
			true,
			array(
				1 => array( 1, 2 ),
				2 => array( 3 ),
			),
			array(
				1 => $this->product( 1 ),
				2 => $this->product( 2 ),
				3 => $this->product( 3 ),
			)
		);

		$documents = iterator_to_array(
			$this->normalizer( $gateway )->documents(
				$this->source(
					array(
						'catalog'   => true,
						'page_size' => 2,
					)
				)
			)
		);

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain records use the approved camelCase contract.
		self::assertSame(
			array( 'woocommerce_product:1', 'woocommerce_product:2', 'woocommerce_product:3' ),
			array_map( static fn ( $document ): string => $document->documentKey, $documents )
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		self::assertSame(
			array(
				array(
					'page'    => 1,
					'perPage' => 2,
				),
				array(
					'page'    => 2,
					'perPage' => 2,
				),
			),
			$gateway->product_id_calls
		);
	}

	/** Ambiguous selection configuration must fail closed instead of broadening scope. */
	public function test_rejects_ambiguous_selection_configuration(): void {
		$gateway = new FakeWooCommerceCatalogGateway( true );
		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array(
			$this->normalizer( $gateway )->documents(
				$this->source(
					array(
						'product_ids' => array( 42 ),
						'catalog'     => true,
					)
				)
			)
		);
	}

	/** Invalid explicit IDs must be rejected rather than ignored. */
	public function test_rejects_invalid_explicit_product_ids(): void {
		$gateway = new FakeWooCommerceCatalogGateway( true );
		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array(
			$this->normalizer( $gateway )->documents(
				$this->source( array( 'product_ids' => array( 42, 0 ) ) )
			)
		);
	}

	/** Catalog page size must remain within the approved hard maximum. */
	public function test_rejects_catalog_page_size_above_250(): void {
		$gateway = new FakeWooCommerceCatalogGateway( true );
		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array(
			$this->normalizer( $gateway )->documents(
				$this->source(
					array(
						'catalog'   => true,
						'page_size' => 251,
					)
				)
			)
		);
	}

	/** Missing products selected explicitly must disappear from source output. */
	public function test_missing_explicit_product_is_omitted(): void {
		$gateway = new FakeWooCommerceCatalogGateway( true, array(), array() );

		$documents = iterator_to_array( $this->normalizer( $gateway )->documents( $this->source( array( 'product_ids' => array( 99 ) ) ) ) );

		self::assertSame( array(), $documents );
		self::assertSame( array( 99 ), $gateway->product_calls );
	}

	/**
	 * Construct the source implementation dynamically so the test-only RED stays static-analysis clean.
	 *
	 * @param FakeWooCommerceCatalogGateway $gateway Test gateway.
	 */
	private function normalizer( FakeWooCommerceCatalogGateway $gateway ): KnowledgeSource {
		$class_name = 'WpRagAiChatbot\\Knowledge\\Sources\\WooCommerceProductSource';
		self::assertTrue( class_exists( $class_name ), 'WooCommerceProductSource must exist.' );

		$normalizer = new $class_name( $gateway );
		self::assertInstanceOf( KnowledgeSource::class, $normalizer );
		return $normalizer;
	}

	/**
	 * Create a persisted WooCommerce source record.
	 *
	 * @param array<string, mixed> $config Source configuration.
	 */
	private function source( array $config = array( 'product_ids' => array( 42 ) ) ): KnowledgeSourceRecord {
		$time = new DateTimeImmutable( '2026-09-03 09:30:00', new DateTimeZone( 'UTC' ) );
		return new KnowledgeSourceRecord( 23, 'shop-products', 'woocommerce_product', null, 'Shop products', null, 'active', $config, null, null, $time, $time );
	}

	/**
	 * Create one stable product snapshot.
	 *
	 * @param int                               $id Product ID.
	 * @param string                            $name Product name.
	 * @param string                            $type Product type.
	 * @param array<string, array<int, string>> $attributes Product attributes.
	 * @param array<int, WooCommerceVariation>  $variations Product variations.
	 */
	private function product(
		int $id,
		string $name = 'Product',
		string $type = 'simple',
		array $attributes = array(),
		array $variations = array()
	): WooCommerceProduct {
		return new WooCommerceProduct(
			$id,
			$type,
			'publish',
			'visible',
			$name,
			'Light trail shoe.',
			'Stable descriptive copy.',
			'TRAIL-' . $id,
			'https://example.test/product/' . $id . '/',
			array( 'Trail', 'Shoes' ),
			array( 'Outdoor', 'Featured' ),
			$attributes,
			$variations,
			'2026-09-03T09:00:00+00:00'
		);
	}
}
