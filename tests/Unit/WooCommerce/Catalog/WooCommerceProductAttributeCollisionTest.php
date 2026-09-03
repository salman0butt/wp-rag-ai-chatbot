<?php
/**
 * WooCommerce product attribute collision tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\WooCommerce\Catalog;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceProduct;

/**
 * Verifies normalized attribute names cannot silently overwrite catalog facts.
 */
final class WooCommerceProductAttributeCollisionTest extends TestCase {
	/**
	 * Attribute names that collide after trimming must fail closed.
	 */
	public function test_product_snapshot_rejects_normalized_attribute_name_collision(): void {
		$this->expectException( InvalidArgumentException::class );

		new WooCommerceProduct(
			id: 42,
			type: 'simple',
			status: 'publish',
			catalogVisibility: 'visible',
			name: 'Trail Shoe',
			shortDescription: 'Light trail shoe.',
			description: 'Stable descriptive copy.',
			sku: 'TRAIL-42',
			canonicalUrl: 'https://example.test/product/trail-shoe/',
			categories: array( 'Shoes' ),
			tags: array( 'Trail' ),
			attributes: array(
				' Size' => array( '41' ),
				'Size'  => array( '42' ),
			),
			variations: array(),
			modifiedGmt: '2026-09-03T00:00:00+00:00'
		);
	}
}
