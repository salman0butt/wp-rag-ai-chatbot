<?php
/**
 * Native WooCommerce catalog gateway.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\WooCommerce\Catalog;

use InvalidArgumentException;

/**
 * Optional-safe boundary around public WooCommerce catalog APIs.
 */
final class NativeWooCommerceCatalogGateway implements WooCommerceCatalogGateway {
	private const MAX_PAGE_SIZE = 250;

	/**
	 * Report whether the required public WooCommerce APIs are available.
	 */
	public function isAvailable(): bool {
		return function_exists( 'wc_get_products' ) && function_exists( 'wc_get_product' );
	}

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Interface parameter name follows the approved application contract.
	/**
	 * Load one eligible stable product snapshot.
	 *
	 * Product normalization is added by the next Task 2 TDD subcycle.
	 *
	 * @param int $productId Product ID.
	 * @throws InvalidArgumentException When the product ID is invalid.
	 */
	public function product( int $productId ): ?WooCommerceProduct {
		if ( $productId < 1 ) {
			throw new InvalidArgumentException( 'WooCommerce product ID must be positive.' );
		}

		if ( ! $this->isAvailable() ) {
			return null;
		}

		return null;
	}
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Interface parameter name follows the approved application contract.
	/**
	 * Return one deterministic page of eligible product IDs.
	 *
	 * @param int $page One-based page number.
	 * @param int $perPage Products per page.
	 * @return list<int>
	 * @throws InvalidArgumentException When paging values are invalid.
	 */
	public function productIds( int $page, int $perPage ): array {
		$this->validatePaging( $page, $perPage );

		if ( ! $this->isAvailable() ) {
			return array();
		}

		$ids = call_user_func(
			'wc_get_products',
			array(
				'status'  => 'publish',
				'limit'   => $perPage,
				'page'    => $page,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);

		if ( ! is_array( $ids ) ) {
			return array();
		}

		$eligible_ids = array();
		foreach ( $ids as $raw_id ) {
			$product_id = $this->normalizeProductId( $raw_id );
			if ( null === $product_id ) {
				continue;
			}

			$product = call_user_func( 'wc_get_product', $product_id );
			if ( ! $this->isEligibleProduct( $product, $product_id ) ) {
				continue;
			}

			$eligible_ids[] = $product_id;
		}

		$eligible_ids = array_values( array_unique( $eligible_ids ) );
		sort( $eligible_ids, SORT_NUMERIC );
		return $eligible_ids;
	}
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

	/**
	 * Validate bounded paging inputs.
	 *
	 * @param int $page One-based page number.
	 * @param int $per_page Products per page.
	 * @throws InvalidArgumentException When paging values are invalid.
	 */
	private function validatePaging( int $page, int $per_page ): void {
		if ( $page < 1 ) {
			throw new InvalidArgumentException( 'WooCommerce catalog page must be positive.' );
		}

		if ( $per_page < 1 || $per_page > self::MAX_PAGE_SIZE ) {
			throw new InvalidArgumentException( 'WooCommerce catalog page size must be between 1 and 250.' );
		}
	}

	/**
	 * Normalize one WooCommerce product ID result.
	 *
	 * @param mixed $raw_id Raw query result.
	 */
	private function normalizeProductId( mixed $raw_id ): ?int {
		if ( is_int( $raw_id ) ) {
			return $raw_id > 0 ? $raw_id : null;
		}

		if ( is_string( $raw_id ) && ctype_digit( $raw_id ) ) {
			$product_id = (int) $raw_id;
			return $product_id > 0 ? $product_id : null;
		}

		return null;
	}

	/**
	 * Verify current-page product eligibility using public product methods only.
	 *
	 * @param mixed $product Native WooCommerce product value.
	 * @param int   $product_id Product ID.
	 */
	private function isEligibleProduct( mixed $product, int $product_id ): bool {
		if ( ! is_object( $product ) ) {
			return false;
		}

		foreach ( array( 'get_status', 'get_type', 'get_catalog_visibility' ) as $method ) {
			if ( ! method_exists( $product, $method ) ) {
				return false;
			}
		}

		$status     = (string) call_user_func( array( $product, 'get_status' ) );
		$type       = (string) call_user_func( array( $product, 'get_type' ) );
		$visibility = (string) call_user_func( array( $product, 'get_catalog_visibility' ) );

		if ( 'publish' !== $status || ! in_array( $type, array( 'simple', 'variable' ), true ) ) {
			return false;
		}

		if ( ! in_array( $visibility, array( 'visible', 'catalog', 'search' ), true ) ) {
			return false;
		}

		if ( ! function_exists( 'get_post_field' ) ) {
			return false;
		}

		$password = (string) get_post_field( 'post_password', $product_id );
		return '' === trim( $password );
	}
}
