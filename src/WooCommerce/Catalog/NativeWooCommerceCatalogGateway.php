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

	/** Report whether the required public WooCommerce APIs are available. */
	public function isAvailable(): bool {
		return function_exists( 'wc_get_products' ) && function_exists( 'wc_get_product' );
	}

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Interface parameter name follows the approved application contract.
	/**
	 * Load one eligible stable product snapshot.
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

		$get_product = $this->resolveCallable( 'wc_get_product' );
		if ( null === $get_product ) {
			return null;
		}

		$product = $get_product( $productId );
		if ( ! $this->isEligibleProduct( $product, $productId ) || ! is_object( $product ) ) {
			return null;
		}

		$required_methods = array(
			'get_status',
			'get_type',
			'get_catalog_visibility',
			'get_name',
			'get_short_description',
			'get_description',
			'get_sku',
			'get_permalink',
			'get_category_ids',
			'get_tag_ids',
			'get_attributes',
			'get_date_modified',
		);
		foreach ( $required_methods as $method ) {
			if ( ! method_exists( $product, $method ) ) {
				return null;
			}
		}

		$modified = call_user_func( array( $product, 'get_date_modified' ) );
		if ( ! is_object( $modified ) || ! method_exists( $modified, 'date' ) ) {
			return null;
		}
		$modified_gmt = trim( (string) call_user_func( array( $modified, 'date' ), 'c' ) );
		if ( '' === $modified_gmt ) {
			return null;
		}

		$categories = $this->termNames( call_user_func( array( $product, 'get_category_ids' ) ), 'product_cat' );
		$tags       = $this->termNames( call_user_func( array( $product, 'get_tag_ids' ) ), 'product_tag' );
		$attributes = $this->normalizeAttributes( call_user_func( array( $product, 'get_attributes' ) ), $productId );
		if ( null === $categories || null === $tags || null === $attributes ) {
			return null;
		}

		$type       = (string) call_user_func( array( $product, 'get_type' ) );
		$variations = array();
		if ( 'variable' === $type ) {
			if ( ! method_exists( $product, 'get_children' ) ) {
				return null;
			}

			$variations = $this->normalizeVariations( call_user_func( array( $product, 'get_children' ) ), $get_product );
			if ( null === $variations ) {
				return null;
			}
		}

		return new WooCommerceProduct(
			$productId,
			$type,
			(string) call_user_func( array( $product, 'get_status' ) ),
			(string) call_user_func( array( $product, 'get_catalog_visibility' ) ),
			(string) call_user_func( array( $product, 'get_name' ) ),
			(string) call_user_func( array( $product, 'get_short_description' ) ),
			(string) call_user_func( array( $product, 'get_description' ) ),
			(string) call_user_func( array( $product, 'get_sku' ) ),
			(string) call_user_func( array( $product, 'get_permalink' ) ),
			$categories,
			$tags,
			$attributes,
			$variations,
			$modified_gmt
		);
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

		$get_products = $this->resolveCallable( 'wc_get_products' );
		$get_product  = $this->resolveCallable( 'wc_get_product' );
		if ( null === $get_products || null === $get_product ) {
			return array();
		}

		$ids = $get_products(
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

			$product = $get_product( $product_id );
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
	 * Resolve taxonomy term names through public WordPress APIs.
	 *
	 * @param mixed  $raw_ids Raw term IDs.
	 * @param string $taxonomy Taxonomy name.
	 * @return list<string>|null
	 */
	private function termNames( mixed $raw_ids, string $taxonomy ): ?array {
		if ( ! is_array( $raw_ids ) ) {
			return null;
		}

		$names = array();
		foreach ( $raw_ids as $raw_id ) {
			$id = $this->normalizeProductId( $raw_id );
			if ( null === $id ) {
				return null;
			}

			$term = get_term( $id, $taxonomy );
			if ( ! is_object( $term ) || ! property_exists( $term, 'name' ) ) {
				return null;
			}

			$name = trim( (string) $term->name );
			if ( '' === $name ) {
				return null;
			}
			$names[] = $name;
		}

		return $names;
	}

	/**
	 * Normalize public WooCommerce product attributes without reading arbitrary meta.
	 *
	 * @param mixed $raw_attributes Raw product attributes.
	 * @param int   $product_id Product ID.
	 * @return array<string,list<string>>|null
	 */
	private function normalizeAttributes( mixed $raw_attributes, int $product_id ): ?array {
		if ( ! is_array( $raw_attributes ) ) {
			return null;
		}

		$normalized = array();
		foreach ( $raw_attributes as $name => $attribute ) {
			if ( is_array( $attribute ) && is_string( $name ) ) {
				$values = array();
				foreach ( $attribute as $value ) {
					if ( ! is_scalar( $value ) ) {
						return null;
					}
					$values[] = (string) $value;
				}
				$normalized[ $name ] = $values;
				continue;
			}

			if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) || ! method_exists( $attribute, 'get_options' ) ) {
				return null;
			}

			$attribute_name = trim( (string) call_user_func( array( $attribute, 'get_name' ) ) );
			$options        = call_user_func( array( $attribute, 'get_options' ) );
			if ( '' === $attribute_name || ! is_array( $options ) ) {
				return null;
			}

			$values = array();
			foreach ( $options as $option ) {
				if ( is_scalar( $option ) ) {
					$values[] = (string) $option;
				} else {
					return null;
				}
			}

			if ( method_exists( $attribute, 'is_taxonomy' ) && (bool) call_user_func( array( $attribute, 'is_taxonomy' ) ) ) {
				$get_terms = $this->resolveCallable( 'wc_get_product_terms' );
				if ( null === $get_terms ) {
					return null;
				}
				$term_names = $get_terms( $product_id, $attribute_name, array( 'fields' => 'names' ) );
				if ( ! is_array( $term_names ) ) {
					return null;
				}
				$values = array_values( array_map( 'strval', $term_names ) );
			}

			$normalized[ $attribute_name ] = $values;
		}

		return $normalized;
	}

	/**
	 * Normalize stable variation identity, SKU, and selected options.
	 *
	 * @param mixed    $raw_ids Raw variation IDs.
	 * @param callable $get_product Native WooCommerce product resolver.
	 * @return list<WooCommerceVariation>|null
	 */
	private function normalizeVariations( mixed $raw_ids, callable $get_product ): ?array {
		if ( ! is_array( $raw_ids ) ) {
			return null;
		}

		$variations = array();
		foreach ( $raw_ids as $raw_id ) {
			$variation_id = $this->normalizeProductId( $raw_id );
			if ( null === $variation_id ) {
				return null;
			}

			$variation = $get_product( $variation_id );
			if ( false === $variation || null === $variation ) {
				continue;
			}
			if ( ! is_object( $variation ) || ! method_exists( $variation, 'get_sku' ) || ! method_exists( $variation, 'get_variation_attributes' ) ) {
				return null;
			}

			$raw_attributes = call_user_func( array( $variation, 'get_variation_attributes' ) );
			if ( ! is_array( $raw_attributes ) ) {
				return null;
			}

			$attributes = array();
			foreach ( $raw_attributes as $name => $value ) {
				if ( ! is_string( $name ) || ! is_scalar( $value ) ) {
					return null;
				}
				$attributes[ $name ] = (string) $value;
			}

			$variations[] = new WooCommerceVariation(
				$variation_id,
				(string) call_user_func( array( $variation, 'get_sku' ) ),
				$attributes
			);
		}

		return $variations;
	}

	/**
	 * Resolve an optional runtime function without making it a static dependency.
	 *
	 * @param string $function_name Function name.
	 * @return callable|null Runtime callable when available.
	 */
	private function resolveCallable( string $function_name ): ?callable {
		return is_callable( $function_name ) ? $function_name : null;
	}

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
