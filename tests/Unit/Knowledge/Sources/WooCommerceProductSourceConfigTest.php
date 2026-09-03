<?php
/**
 * WooCommerce product source configuration regression tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\Sources;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;
use WpRagAiChatbot\Knowledge\Sources\WooCommerceProductSource;
use WpRagAiChatbot\Tests\Support\WooCommerce\FakeWooCommerceCatalogGateway;

/**
 * Verifies unsupported source configuration fails closed.
 */
final class WooCommerceProductSourceConfigTest extends TestCase {
	/** Unsupported configuration keys must not be silently ignored. */
	public function test_rejects_unsupported_configuration_key(): void {
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
				'product_ids' => array( 42 ),
				'unexpected'  => true,
			),
			null,
			null,
			$time,
			$time
		);

		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array(
			( new WooCommerceProductSource( new FakeWooCommerceCatalogGateway( true ) ) )->documents( $source )
		);
	}
}
