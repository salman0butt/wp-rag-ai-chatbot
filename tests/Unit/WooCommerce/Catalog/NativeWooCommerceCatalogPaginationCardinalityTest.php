<?php
/**
 * Native WooCommerce catalog pagination cardinality tests.
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
 * Verifies bounded raw candidate-page cardinality remains truthful.
 */
final class NativeWooCommerceCatalogPaginationCardinalityTest extends TestCase {
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

	/** Duplicate query candidates must not shorten a full page before source-level de-duplication. */
	public function test_product_ids_preserves_duplicate_candidate_cardinality(): void {
		Functions\when( 'wc_get_products' )->justReturn( array( 9, 3, 9 ) );
		Functions\when( 'wc_get_product' )->justReturn( false );

		self::assertSame(
			array( 3, 9, 9 ),
			( new NativeWooCommerceCatalogGateway() )->productIds( 1, 3 )
		);
	}
}
