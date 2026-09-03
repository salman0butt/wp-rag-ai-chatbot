<?php
/**
 * WooCommerce variation attribute collision tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\WooCommerce\Catalog;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceVariation;

/**
 * Verifies normalized variation option names cannot silently overwrite facts.
 */
final class WooCommerceVariationAttributeCollisionTest extends TestCase {
	/**
	 * Attribute names that collide after trimming must fail closed.
	 */
	public function test_variation_snapshot_rejects_normalized_attribute_name_collision(): void {
		$this->expectException( InvalidArgumentException::class );

		new WooCommerceVariation(
			81,
			'TRAIL-42-BLUE',
			array(
				' Color' => 'Red',
				'Color'  => 'Blue',
			)
		);
	}
}
