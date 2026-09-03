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
	/** Product ID. */
	public int $id;

	/** Supported product type. */
	public string $type;

	/** Public product status. */
	public string $status;

	/** Public catalog visibility. */
	public string $catalogVisibility;

	/** Product name. */
	public string $name;

	/** Stable short description. */
	public string $shortDescription;

	/** Stable full description. */
	public string $description;

	/** Product SKU. */
	public ?string $sku;

	/** Public product URL. */
	public string $canonicalUrl;

	/** @var array<int, string> Category labels. */
	public array $categories;

	/** @var array<int, string> Tag labels. */
	public array $tags;

	/** @var array<string, array<int, string>> Descriptive attributes. */
	public array $attributes;

	/** @var array<int, WooCommerceVariation> Stable variation descriptors. */
	public array $variations;

	/** Stable modified marker. */
	public string $modifiedGmt;

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
	 * @param array<int, string>                $categories Category labels.
	 * @param array<int, string>                $tags Tag labels.
	 * @param array<string, array<int, string>> $attributes Descriptive attributes.
	 * @param array<int, WooCommerceVariation>  $variations Stable variation descriptors.
	 * @param string                            $modifiedGmt Stable modified marker.
	 * @throws InvalidArgumentException When product data is invalid.
	 */
	public function __construct(
		int $id,
		string $type,
		string $status,
		string $catalogVisibility,
		string $name,
		string $shortDescription,
		string $description,
		?string $sku,
		string $canonicalUrl,
		array $categories,
		array $tags,
		array $attributes,
		array $variations,
		string $modifiedGmt
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

		$this->id                = $id;
		$this->type              = $type;
		$this->status            = $status;
		$this->catalogVisibility = $catalogVisibility;
		$this->name              = $name;
		$this->shortDescription  = trim( $shortDescription );
		$this->description       = trim( $description );
		$this->sku               = self::normalizeOptionalString( $sku );
		$this->canonicalUrl      = $canonicalUrl;
		$this->categories        = self::normalizeLabels( $categories );
		$this->tags              = self::normalizeLabels( $tags );
		$this->attributes        = self::normalizeAttributes( $attributes );
		$this->variations        = self::normalizeVariations( $variations );
		$this->modifiedGmt       = $modifiedGmt;
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
	 * Normalize labels deterministically.
	 *
	 * @param array<int, string> $labels Raw labels.
	 * @return array<int, string>
	 * @throws InvalidArgumentException When a label is blank.
	 */
	private static function normalizeLabels( array $labels ): array {
		$normalized = array();
		foreach ( $labels as $label ) {
			$label = trim( $label );
			if ( '' === $label ) {
				throw new InvalidArgumentException( 'WooCommerce product labels must not be blank.' );
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
	 * @param array<string, array<int, string>> $attributes Raw attributes.
	 * @return array<string, array<int, string>>
	 * @throws InvalidArgumentException When an attribute name or value is invalid.
	 */
	private static function normalizeAttributes( array $attributes ): array {
		$normalized = array();
		foreach ( $attributes as $name => $values ) {
			$name = trim( $name );
			if ( '' === $name ) {
				throw new InvalidArgumentException( 'WooCommerce product attribute names must not be blank.' );
			}

			$normalized_values = self::normalizeLabels( $values );
			if ( array() === $normalized_values ) {
				throw new InvalidArgumentException( 'WooCommerce product attributes must contain at least one value.' );
			}
			$normalized[ $name ] = $normalized_values;
		}

		ksort( $normalized, SORT_STRING );
		return $normalized;
	}

	/**
	 * Normalize variation order deterministically.
	 *
	 * @param array<int, WooCommerceVariation> $variations Raw variations.
	 * @return array<int, WooCommerceVariation>
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
