<?php
/**
 * WooCommerce product source pagination regression tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\Sources;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\WooCommerceProductSource;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceCatalogGateway;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceProduct;

/**
 * Gateway fixture whose first eligible page is shorter than the raw candidate page.
 */
final class WooCommerceProductSourcePaginationGateway implements WooCommerceCatalogGateway {
	/** @var array<int, array{ids:list<int>,has_more:bool}> */
	private array $pages;

	/** @var array<int, WooCommerceProduct> */
	private array $products;

	/** @var list<array{page:int,perPage:int}> */
	public array $product_id_page_calls = array();

	/**
	 * @param array<int, array{ids:list<int>,has_more:bool}> $pages Catalog pages.
	 * @param array<int, WooCommerceProduct>                 $products Product snapshots.
	 */
	public function __construct( array $pages, array $products ) {
		$this->pages    = $pages;
		$this->products = $products;
	}

	/** WooCommerce is available for this fixture. */
	public function isAvailable(): bool {
		return true;
	}

	/** Return one configured product snapshot. */
	public function product( int $productId ): ?WooCommerceProduct {
		return $this->products[ $productId ] ?? null;
	}

	/**
	 * Preserve the old filtered-ID contract so the regression fails before the fix.
	 *
	 * @return list<int>
	 */
	public function productIds( int $page, int $perPage ): array {
		return $this->pages[ $page ]['ids'] ?? array();
	}

	/**
	 * Return filtered IDs together with truthful raw-page continuation state.
	 *
	 * @return array{ids:list<int>,has_more:bool}
	 */
	public function productIdPage( int $page, int $perPage ): array {
		$this->product_id_page_calls[] = array(
			'page'    => $page,
			'perPage' => $perPage,
		);

		return $this->pages[ $page ] ?? array(
			'ids'      => array(),
			'has_more' => false,
		);
	}
}

/**
 * Verifies whole-catalog traversal survives eligibility filtering.
 */
final class WooCommerceProductSourcePaginationTest extends TestCase {
	/** A short eligible page must not hide later eligible products when the raw page was full. */
	public function test_catalog_traversal_continues_after_filtered_short_page(): void {
		$gateway = new WooCommerceProductSourcePaginationGateway(
			array(
				1 => array(
					'ids'      => array( 1 ),
					'has_more' => true,
				),
				2 => array(
					'ids'      => array( 2 ),
					'has_more' => false,
				),
			),
			array(
				1 => $this->product( 1 ),
				2 => $this->product( 2 ),
			)
		);

		$time   = new DateTimeImmutable( '2026-09-03 09:30:00', new DateTimeZone( 'UTC' ) );
		$source = new KnowledgeSourceRecord(
			23,
			'shop-products',
			'woocommerce_product',
			null,
			'Shop products',
			null,
			'active',
			array(
				'catalog'   => true,
				'page_size' => 2,
			),
			null,
			null,
			$time,
			$time
		);

		$documents = iterator_to_array( ( new WooCommerceProductSource( $gateway ) )->documents( $source ) );

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain records use the approved camelCase contract.
		self::assertSame(
			array( 'woocommerce_product:1', 'woocommerce_product:2' ),
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
			$gateway->product_id_page_calls
		);
	}

	/** Create one stable public product snapshot. */
	private function product( int $id ): WooCommerceProduct {
		return new WooCommerceProduct(
			$id,
			'simple',
			'publish',
			'visible',
			'Product ' . $id,
			'',
			'Stable description.',
			'SKU-' . $id,
			'https://example.test/product/' . $id . '/',
			array(),
			array(),
			array(),
			array(),
			'2026-09-03T09:00:00+00:00'
		);
	}
}
