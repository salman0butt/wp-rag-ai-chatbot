<?php
/**
 * WooCommerce product snapshot.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\WooCommerce\Catalog;

use InvalidArgumentException;

// phpcs:disable WordPress.NamingConventions -- Public record property names follow the approved domain contract.
/**
 * Immutable allowlisted stable public catalog facts for one product.
 */
final readonly class WooCommerceProduct {
	/**
	 * Create a product snapshot.
	 *
	 * @param int                               $id Product ID.
	 * @param string                            $type Supported product type.
	 * @param string                            $status Public product status.
	 * @param string                            $catalogVisibility Public catalog visibility.
	 * @param string                            $name Product name.
	 * @param string                            $shortDescription Stable short description.
	 * @param string                            $description Stable full description.
	 * @param string|null                       $sku Product SKU.
	 * @param string                            $canonicalUrl Public product URL.
	 * @param list<string>                      $categories Category labels.
	 * @param list<string>                      $tags Tag labels.
	 * @param array<string,list<string>>        $attributes Descriptive attributes.
	 * @param list<WooCommerceVariation>        $variations Stable variation descriptors.
	 * @param string                            $modifiedGmt Stable modified marker.
	 * @throws InvalidArgumentException When product data is invalid.
	 */
	public function __construct(
		public int $id,
		public string $type,
		public string $status,
		public string $catalogVisibility,
		public string $name,
		public string $shortDescription,
		public string $description,
		public ?string $sku,
		public string $canonicalUrl,
		public array $categories,
		public array $tags,
		public array $attributes,
		public array $variations,
		public string $modifiedGmt
	) {
		if ( $id < 1 ) {
			throw new InvalidArgumentException( 'WooCommerce product ID must be positive.' );
		}

		$type = trim( $type );
		if ( ! in_array( $type, array( 'simple', 'variable' ), true ) ) {
			throw new InvalidArgumentException( 'WooCommerce product type is not supported for catalog ingestion.' );
		}

		$status = trim( $status );
		if ( 'publish' !== $status ) {
			throw new InvalidArgumentException( 'WooCommerce product snapshot must represent a published product.' );
		}

		$catalogVisibility = trim( $catalogVisibility );
		if ( ! in_array( $catalogVisibility, array( 'visible', 'catalog', 'search' ), true ) ) {
			throw new InvalidArgumentException( 'WooCommerce product snapshot must represent public catalog visibility.' );
		}

		$name = trim( $name );
		if ( '' === $name ) {
			throw new InvalidArgumentException( 'WooCommerce product name must not be blank.' );
		}

		$canonicalUrl = trim( $canonicalUrl );
		if ( '' === $canonicalUrl ) {
			throw new InvalidArgumentException( 'WooCommerce product canonical URL must not be blank.' );
		}

		$modifiedGmt = trim( $modifiedGmt );
		if ( '' === $modifiedGmt ) {
			throw new InvalidArgumentException( 'WooCommerce product modified marker must not be blank.' );
		}

		$this->type             = $type;
		$this->status           = $status;
		$this->catalogVisibility = $catalogVisibility;
		$this->name             = $name;
		$this->shortDescription = trim( $shortDescription );
		$this->description      = trim( $description );
		$this->sku              = self::normalizeOptionalString( $sku );
		$this->canonicalUrl     = $canonicalUrl;
		$this->categories       = self::normalizeLabels( $categories, 'category' );
		$this->tags             = self::normalizeLabels( $tags, 'tag' );
		$this->attributes       = self::normalizeAttributes( $attributes );
		$this->variations       = self::normalizeVariations( $variations );
		$this->modifiedGmt      = $modifiedGmt;
	}

	/**
	 * Normalize an optional stable string.
	 *
	 * @param string|null $value Raw value.
	 */
	private static function normalizeOptionalString( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( $value );
		return '' === $value ? null : $value;
	}

	/**
	 * Normalize taxonomy labels deterministically.
	 *
	 * @param list<string> $labels Raw labels.
	 * @param string       $kind Label kind for errors.
	 * @return list<string>
	 */
	private static function normalizeLabels( array $labels, string $kind ): array {
		$normalized = array();
		foreach ( $labels as $label ) {
			$label = trim( $label );
			if ( '' === $label ) {
				throw new InvalidArgumentException( 'WooCommerce product ' . $kind . ' labels must not be blank.' );
			}
			$normalized[] = $label;
		}

		$normalized = array_values( array_unique( $normalized ) );
		sort( $normalized, SORT_STRING );
		return $normalized;
	}

	/**
	 * Normalize descriptive attributes deterministically.
	 *
	 * @param array<string,list<string>> $attributes Raw attributes.
	 * @return array<string,list<string>>
	 */
	private static function normalizeAttributes( array $attributes ): array {
		$normalized = array();
		foreach ( $attributes as $name => $values ) {
			$name = trim( $name );
			if ( '' === $name ) {
				throw new InvalidArgumentException( 'WooCommerce product attribute names must not be blank.' );
			}

			$normalizedValues = self::normalizeLabels( $values, 'attribute' );
			if ( array() === $normalizedValues ) {
				throw new InvalidArgumentException( 'WooCommerce product attributes must contain at least one value.' );
			}
			$normalized[ $name ] = $normalizedValues;
		}

		ksort( $normalized, SORT_STRING );
		return $normalized;
	}

	/**
	 * Normalize variation order deterministically.
	 *
	 * @param list<WooCommerceVariation> $variations Raw variations.
	 * @return list<WooCommerceVariation>
	 */
	private static function normalizeVariations( array $variations ): array {
		usort(
			$variations,
			static fn ( WooCommerceVariation $left, WooCommerceVariation $right ): int => $left->id <=> $right->id
		);
		return $variations;
	}
}
// phpcs:enable WordPress.NamingConventions
