<?php
/**
 * WooCommerce catalog visibility tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\WooCommerce\Catalog;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceProduct;

/**
 * Verifies hidden catalog products fail closed at the stable snapshot boundary.
 */
final class WooCommerceProductVisibilityTest extends TestCase {
	/** Hidden catalog visibility is not eligible for indexed product knowledge. */
	public function test_product_snapshot_rejects_hidden_catalog_visibility(): void {
		$this->expectException( InvalidArgumentException::class );

		new WooCommerceProduct(
			id: 42,
			type: 'simple',
			status: 'publish',
			catalogVisibility: 'hidden',
			name: 'Hidden Product',
			shortDescription: 'Not public catalog knowledge.',
			description: 'Not public catalog knowledge.',
			sku: 'HIDDEN-42',
			canonicalUrl: 'https://example.test/product/hidden-product/',
			categories: array(),
			tags: array(),
			attributes: array(),
			variations: array(),
			modifiedGmt: '2026-09-03T00:00:00+00:00'
		);
	}
}
