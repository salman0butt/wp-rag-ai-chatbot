<?php
/**
 * Native WooCommerce catalog gateway modified-time regression.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\WooCommerce\Catalog;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\WooCommerce\Catalog\NativeWooCommerceCatalogGateway;

/**
 * Verifies the stable modification marker is normalized to GMT.
 */
final class NativeWooCommerceCatalogGatewayModifiedGmtTest extends TestCase {
	/** Start Brain Monkey before each test. */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/** Tear Brain Monkey down after each test. */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** A site-timezone modification instant must be represented as UTC. */
	public function test_product_normalizes_modified_marker_to_gmt(): void {
		$product = new NativeGatewayProductStub(
			'publish',
			'simple',
			'visible',
			'UTC Product',
			'',
			'Stable copy.',
			'UTC-42',
			'https://example.test/product/utc-product/',
			array(),
			array(),
			array(),
			array(),
			'2026-09-03T14:00:00+05:00'
		);

		Functions\when( 'wc_get_products' )->justReturn( array() );
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_field' )->justReturn( '' );

		$snapshot = ( new NativeWooCommerceCatalogGateway() )->product( 42 );

		self::assertNotNull( $snapshot );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain record property follows the approved contract.
		self::assertSame( '2026-09-03T09:00:00+00:00', $snapshot->modifiedGmt );
	}
}
