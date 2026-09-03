<?php
/**
 * Real WordPress/WooCommerce knowledge-ingestion smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Knowledge\KnowledgeBootstrap;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

$product_ids = array();
$term_ids    = array();

$cleanup = static function () use ( &$product_ids, &$term_ids ): void {
	foreach ( array_reverse( $product_ids ) as $product_id ) {
		wp_delete_post( $product_id, true );
	}
	$product_ids = array();

	foreach ( array_reverse( $term_ids ) as $term ) {
		if ( isset( $term['id'], $term['taxonomy'] ) ) {
			wp_delete_term( (int) $term['id'], (string) $term['taxonomy'] );
		}
	}
	$term_ids = array();
};

$fail = static function ( string $message ) use ( $cleanup ): void {
	$cleanup();
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$single_document = static function ( iterable $documents ) use ( $fail ) {
	$normalized = array_values( is_array( $documents ) ? $documents : iterator_to_array( $documents, false ) );
	if ( 1 !== count( $normalized ) ) {
		$fail( 'WooCommerce source did not normalize exactly one document.' );
	}

	return $normalized[0];
};

$source_record = static function ( int $id, string $key, int $product_id ): KnowledgeSourceRecord {
	$now = new \DateTimeImmutable( '2026-09-03T00:00:00+00:00' );

	return new KnowledgeSourceRecord(
		$id,
		$key,
		'woocommerce_product',
		(string) $product_id,
		'M06 WooCommerce smoke',
		null,
		'active',
		array( 'product_ids' => array( $product_id ) ),
		null,
		null,
		$now,
		$now
	);
};

try {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_get_products' ) ) {
		$fail( 'WooCommerce public APIs are unavailable in enabled smoke.' );
	}

	KnowledgeBootstrap::register();
	$registry = KnowledgeBootstrap::registry();
	if ( ! $registry->has( 'woocommerce_product' ) ) {
		$fail( 'WooCommerce knowledge source is not registered in real WordPress.' );
	}
	$source = $registry->get( 'woocommerce_product' );

	$category = wp_insert_term( 'M06 Smoke Category', 'product_cat' );
	$tag      = wp_insert_term( 'M06 Smoke Tag', 'product_tag' );
	if ( is_wp_error( $category ) || is_wp_error( $tag ) ) {
		$fail( 'Could not create WooCommerce taxonomy fixtures.' );
	}
	$category_id = (int) $category['term_id'];
	$tag_id      = (int) $tag['term_id'];
	$term_ids[]  = array( 'id' => $category_id, 'taxonomy' => 'product_cat' );
	$term_ids[]  = array( 'id' => $tag_id, 'taxonomy' => 'product_tag' );

	$simple = new \WC_Product_Simple();
	$simple->set_name( 'M06 Simple Smoke Product' );
	$simple->set_status( 'publish' );
	$simple->set_catalog_visibility( 'visible' );
	$simple->set_sku( 'M06-SIMPLE' );
	$simple->set_short_description( 'Stable short description.' );
	$simple->set_description( 'Stable simple product description.' );
	$simple->set_category_ids( array( $category_id ) );
	$simple->set_tag_ids( array( $tag_id ) );
	$simple->set_regular_price( '123.45' );
	$simple->set_price( '123.45' );
	$simple->set_manage_stock( true );
	$simple->set_stock_quantity( 7 );
	$simple_id    = (int) $simple->save();
	$product_ids[] = $simple_id;
	update_post_meta( $simple_id, '_m06_private_secret', 'customer-private-value' );

	$variable = new \WC_Product_Variable();
	$variable->set_name( 'M06 Variable Smoke Product' );
	$variable->set_status( 'publish' );
	$variable->set_catalog_visibility( 'visible' );
	$variable->set_sku( 'M06-VARIABLE' );
	$variable->set_short_description( 'Variable short description.' );
	$variable->set_description( 'Stable variable product description.' );
	$attribute = new \WC_Product_Attribute();
	$attribute->set_name( 'Color' );
	$attribute->set_options( array( 'Blue', 'Red' ) );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$variable->set_attributes( array( $attribute ) );
	$variable_id    = (int) $variable->save();
	$product_ids[]  = $variable_id;

	$variation = new \WC_Product_Variation();
	$variation->set_parent_id( $variable_id );
	$variation->set_status( 'publish' );
	$variation->set_sku( 'M06-VARIATION-BLUE' );
	$variation->set_attributes( array( 'color' => 'Blue' ) );
	$variation->set_regular_price( '88.00' );
	$variation->set_price( '88.00' );
	$variation_id   = (int) $variation->save();
	$product_ids[]  = $variation_id;

	$simple_source = $source_record( 906001, 'm06-simple-smoke', $simple_id );
	$simple_first  = $single_document( $source->documents( $simple_source ) );
	$simple_second = $single_document( $source->documents( $simple_source ) );

	if ( 'woocommerce_product:' . $simple_id !== $simple_first->documentKey ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved domain record contract.
		$fail( 'Simple WooCommerce product did not produce the canonical document key.' );
	}
	if ( ! str_contains( $simple_first->content, 'M06 Simple Smoke Product' ) || ! str_contains( $simple_first->content, 'M06-SIMPLE' ) ) {
		$fail( 'Simple WooCommerce stable catalog content was not normalized.' );
	}
	if ( ! in_array( 'M06 Smoke Category', $simple_first->metadata['categories'] ?? array(), true ) || ! in_array( 'M06 Smoke Tag', $simple_first->metadata['tags'] ?? array(), true ) ) {
		$fail( 'Simple WooCommerce category/tag metadata was not normalized.' );
	}
	if ( $simple_first->sourceVersion !== $simple_second->sourceVersion || $simple_first->contentHash !== $simple_second->contentHash ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved domain record contract.
		$fail( 'Equivalent WooCommerce product reads did not produce stable version/hash values.' );
	}
	foreach ( array( 'price', 'regular_price', 'stock', 'stock_status', 'customer', 'private' ) as $forbidden ) {
		if ( array_key_exists( $forbidden, $simple_first->metadata ) || str_contains( strtolower( $simple_first->content ), $forbidden ) ) {
			$fail( 'Live/private WooCommerce data leaked into canonical document output: ' . $forbidden );
		}
	}
	if ( str_contains( $simple_first->content, '123.45' ) || str_contains( $simple_first->content, 'customer-private-value' ) ) {
		$fail( 'WooCommerce price/private metadata leaked into canonical content.' );
	}

	$variable_source   = $source_record( 906002, 'm06-variable-smoke', $variable_id );
	$variable_document = $single_document( $source->documents( $variable_source ) );
	if ( ! str_contains( $variable_document->content, 'M06 Variable Smoke Product' ) || ! str_contains( $variable_document->content, 'M06-VARIATION-BLUE' ) ) {
		$fail( 'Variable product/variation stable facts were not normalized.' );
	}
	$variations = $variable_document->metadata['variations'] ?? array();
	if ( 1 !== count( $variations ) || $variation_id !== (int) ( $variations[0]['id'] ?? 0 ) ) {
		$fail( 'Variable product variation identity was not preserved.' );
	}

	$simple->set_description( 'Stable simple product description updated.' );
	$simple->save();
	$descriptive_document = $single_document( $source->documents( $simple_source ) );
	if ( $descriptive_document->sourceVersion === $simple_first->sourceVersion || $descriptive_document->contentHash === $simple_first->contentHash ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved domain record contract.
		$fail( 'Descriptive WooCommerce changes did not alter version/hash.' );
	}

	$simple->set_regular_price( '999.99' );
	$simple->set_price( '999.99' );
	$simple->set_stock_quantity( 2 );
	$simple->save();
	$live_only_document = $single_document( $source->documents( $simple_source ) );
	if ( $live_only_document->sourceVersion !== $descriptive_document->sourceVersion || $live_only_document->contentHash !== $descriptive_document->contentHash ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved domain record contract.
		$fail( 'Price/stock-only WooCommerce changes altered stable version/hash.' );
	}

	$simple->set_status( 'draft' );
	$simple->save();
	$after_unpublish = array_values( iterator_to_array( $source->documents( $simple_source ), false ) );
	if ( array() !== $after_unpublish ) {
		$fail( 'Unpublished WooCommerce product remained eligible for knowledge output.' );
	}

	$cleanup();
	fwrite( STDOUT, "WordPress/WooCommerce enabled knowledge smoke passed.\n" );
} catch ( \Throwable $exception ) {
	$fail( 'WordPress/WooCommerce knowledge smoke threw: ' . $exception->getMessage() );
}
