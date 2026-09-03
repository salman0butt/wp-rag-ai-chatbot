<?php
/**
 * WooCommerce variation snapshot.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\WooCommerce\Catalog;

use InvalidArgumentException;

// phpcs:disable WordPress.NamingConventions -- Public record property names follow the approved domain contract.
/**
 * Immutable stable descriptive variation facts.
 */
final readonly class WooCommerceVariation {
	/**
	 * Variation ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Stable variation SKU.
	 *
	 * @var string|null
	 */
	public ?string $sku;

	/**
	 * Descriptive variation choices.
	 *
	 * @var array<string,string>
	 */
	public array $attributes;

	/**
	 * Create a variation snapshot.
	 *
	 * @param int                  $id Variation ID.
	 * @param string|null          $sku Stable variation SKU.
	 * @param array<string,string> $attributes Descriptive variation choices.
	 * @throws InvalidArgumentException When stable variation data is invalid.
	 */
	public function __construct(
		int $id,
		?string $sku,
		array $attributes
	) {
		if ( $id < 1 ) {
			throw new InvalidArgumentException( 'WooCommerce variation ID must be positive.' );
		}

		$this->id         = $id;
		$this->sku        = self::normalizeOptionalString( $sku );
		$this->attributes = self::normalizeAttributes( $attributes );
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
	 * Normalize variation attributes into deterministic key order.
	 *
	 * @param array<string,string> $attributes Raw attributes.
	 * @return array<string,string>
	 * @throws InvalidArgumentException When an attribute name or value is blank.
	 */
	private static function normalizeAttributes( array $attributes ): array {
		$normalized = array();
		foreach ( $attributes as $name => $value ) {
			$name  = trim( $name );
			$value = trim( $value );
			if ( '' === $name || '' === $value ) {
				throw new InvalidArgumentException( 'WooCommerce variation attributes must contain non-empty names and values.' );
			}

			$normalized[ $name ] = $value;
		}

		ksort( $normalized, SORT_STRING );
		return $normalized;
	}
}
// phpcs:enable WordPress.NamingConventions
