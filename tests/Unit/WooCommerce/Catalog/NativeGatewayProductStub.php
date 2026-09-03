<?php
/**
 * Native WooCommerce catalog gateway product test seam.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\WooCommerce\Catalog;

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
