<?php
/**
 * Native WooCommerce catalog gateway product test seam.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\WooCommerce\Catalog;

use DateTimeImmutable;

/**
 * Minimal product object seam for native gateway tests.
 */
final class NativeGatewayProductStub {
	/**
	 * Create a native-product test seam.
	 *
	 * @param string                     $status Product status.
	 * @param string                     $type Product type.
	 * @param string                     $catalog_visibility Catalog visibility.
	 * @param string                     $name Product name.
	 * @param string                     $short_description Short description.
	 * @param string                     $description Full description.
	 * @param string                     $sku Product SKU.
	 * @param string                     $permalink Product permalink.
	 * @param array<int, string>         $categories Category labels.
	 * @param array<int, string>         $tags Tag labels.
	 * @param array<string,list<string>> $attributes Descriptive attributes.
	 * @param array<int, int>            $children Variation IDs.
	 * @param string                     $modified_gmt Modified marker.
	 * @param array<string,string>       $variation_attributes Variation choices.
	 */
	public function __construct(
		private readonly string $status,
		private readonly string $type,
		private readonly string $catalog_visibility,
		private readonly string $name = 'Product',
		private readonly string $short_description = '',
		private readonly string $description = '',
		private readonly string $sku = '',
		private readonly string $permalink = 'https://example.test/product/product/',
		private readonly array $categories = array(),
		private readonly array $tags = array(),
		private readonly array $attributes = array(),
		private readonly array $children = array(),
		private readonly string $modified_gmt = '2026-09-03T00:00:00+00:00',
		private readonly array $variation_attributes = array()
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

	/** Product name. */
	public function get_name(): string {
		return $this->name;
	}

	/** Short description. */
	public function get_short_description(): string {
		return $this->short_description;
	}

	/** Full description. */
	public function get_description(): string {
		return $this->description;
	}

	/** Product SKU. */
	public function get_sku(): string {
		return $this->sku;
	}

	/** Product permalink. */
	public function get_permalink(): string {
		return $this->permalink;
	}

	/** Category IDs. @return array<int,int> */
	public function get_category_ids(): array {
		return array_keys( $this->categories );
	}

	/** Tag IDs. @return array<int,int> */
	public function get_tag_ids(): array {
		return array_keys( $this->tags );
	}

	/** Product attributes. @return array<string,list<string>> */
	public function get_attributes(): array {
		return $this->attributes;
	}

	/** Variation child IDs. @return array<int,int> */
	public function get_children(): array {
		return $this->children;
	}

	/** Variation choices. @return array<string,string> */
	public function get_variation_attributes(): array {
		return $this->variation_attributes;
	}

	/** Stable modified marker. */
	public function get_date_modified(): object {
		$value = $this->modified_gmt;
		return new class( $value ) {
			/**
			 * Create the date seam.
			 *
			 * @param string $value Stable marker.
			 */
			public function __construct( private readonly string $value ) {
			}

			/**
			 * Format the test marker.
			 *
			 * @param string $format Requested format.
			 */
			public function date( string $format ): string {
				unset( $format );
				return $this->value;
			}

			// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Mirrors the native DateTime API used by WooCommerce.
			/** Return the represented modification instant. */
			public function getTimestamp(): int {
				return ( new DateTimeImmutable( $this->value ) )->getTimestamp();
			}
			// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		};
	}

	/**
	 * Resolve one category label by test ID.
	 *
	 * @param int $id Term ID.
	 */
	public function category_name( int $id ): ?string {
		return $this->categories[ $id ] ?? null;
	}

	/**
	 * Resolve one tag label by test ID.
	 *
	 * @param int $id Term ID.
	 */
	public function tag_name( int $id ): ?string {
		return $this->tags[ $id ] ?? null;
	}
}
