<?php
/**
 * Native WooCommerce catalog gateway tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\WooCommerce\Catalog;

use Brain\Monkey;
use Brain\Monkey\Functions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\WooCommerce\Catalog\NativeWooCommerceCatalogGateway;

/**
 * Verifies optional-safe native WooCommerce catalog access.
 */
final class NativeWooCommerceCatalogGatewayTest extends TestCase {
	/**
	 * Start Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear Brain Monkey down after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * WooCommerce absence must remain a supported non-fatal state.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_unavailable_woocommerce_is_non_fatal_and_empty(): void {
		self::assertTrue( class_exists( NativeWooCommerceCatalogGateway::class ) );
		if ( ! class_exists( NativeWooCommerceCatalogGateway::class ) ) {
			return;
		}

		$gateway = new NativeWooCommerceCatalogGateway();

		self::assertFalse( $gateway->isAvailable() );
		self::assertSame( array(), $gateway->productIds( 1, 100 ) );
		self::assertNull( $gateway->product( 42 ) );
	}

	/**
	 * Page numbers are one-based even when WooCommerce is unavailable.
	 */
	public function test_product_ids_rejects_non_positive_page(): void {
		self::assertTrue( class_exists( NativeWooCommerceCatalogGateway::class ) );
		if ( ! class_exists( NativeWooCommerceCatalogGateway::class ) ) {
			return;
		}

		$this->expectException( InvalidArgumentException::class );
		( new NativeWooCommerceCatalogGateway() )->productIds( 0, 100 );
	}

	/**
	 * Page size must be positive.
	 */
	public function test_product_ids_rejects_non_positive_page_size(): void {
		self::assertTrue( class_exists( NativeWooCommerceCatalogGateway::class ) );
		if ( ! class_exists( NativeWooCommerceCatalogGateway::class ) ) {
			return;
		}

		$this->expectException( InvalidArgumentException::class );
		( new NativeWooCommerceCatalogGateway() )->productIds( 1, 0 );
	}

	/**
	 * Page size is hard-bounded to protect synchronous ingestion.
	 */
	public function test_product_ids_rejects_page_size_above_hard_limit(): void {
		self::assertTrue( class_exists( NativeWooCommerceCatalogGateway::class ) );
		if ( ! class_exists( NativeWooCommerceCatalogGateway::class ) ) {
			return;
		}

		$this->expectException( InvalidArgumentException::class );
		( new NativeWooCommerceCatalogGateway() )->productIds( 1, 251 );
	}

	/**
	 * Enumeration is bounded, deterministic, supported-type-only, and public-only.
	 */
	public function test_product_ids_returns_sorted_public_supported_products(): void {
		self::assertTrue( class_exists( NativeWooCommerceCatalogGateway::class ) );
		if ( ! class_exists( NativeWooCommerceCatalogGateway::class ) ) {
			return;
		}

		Functions\expect( 'wc_get_products' )
			->once()
			->with(
				array(
					'status'  => 'publish',
					'limit'   => 25,
					'page'    => 2,
					'orderby' => 'ID',
					'order'   => 'ASC',
					'return'  => 'ids',
				)
			)
			->andReturn( array( 9, 3, 7, 11, 13 ) );

		Functions\when( 'wc_get_product' )->alias(
			static function ( int $product_id ): NativeGatewayProductStub|false {
				return match ( $product_id ) {
					3       => new NativeGatewayProductStub( 'publish', 'simple', 'catalog' ),
					7       => new NativeGatewayProductStub( 'publish', 'simple', 'hidden' ),
					9       => new NativeGatewayProductStub( 'publish', 'variable', 'visible' ),
					11      => new NativeGatewayProductStub( 'publish', 'simple', 'search' ),
					13      => new NativeGatewayProductStub( 'publish', 'external', 'visible' ),
					default => false,
				};
			}
		);

		Functions\when( 'get_post_field' )->alias(
			static function ( string $field, int $product_id ): string {
				self::assertSame( 'post_password', $field );
				return 11 === $product_id ? 'protected' : '';
			}
		);

		$gateway = new NativeWooCommerceCatalogGateway();

		self::assertTrue( $gateway->isAvailable() );
		self::assertSame( array( 3, 9 ), $gateway->productIds( 2, 25 ) );
	}
}

/**
 * Minimal product object seam for enumeration eligibility tests.
 */
final class NativeGatewayProductStub {
	/**
	 * Create a native-product test seam.
	 *
	 * @param string $status Product status.
	 * @param string $type Product type.
	 * @param string $catalog_visibility Catalog visibility.
	 */
	public function __construct(
		private readonly string $status,
		private readonly string $type,
		private readonly string $catalog_visibility
	) {
	}

	/** Product status. */
	public function get_status(): string {
		return $this->status;
	}

	/** Product type. */
	public function get_type(): string {
		return $this->type;
	}

	/** Catalog visibility. */
	public function get_catalog_visibility(): string {
		return $this->catalog_visibility;
	}
}
