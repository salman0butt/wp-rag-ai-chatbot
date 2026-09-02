<?php
/**
 * Real WordPress database migration smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Database\DatabaseBootstrap;
use WpRagAiChatbot\Database\MigrationStatus;

global $wpdb;
$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$prefix    = $wpdb->prefix;
$sources   = $prefix . 'rag_ai_sources';
$documents = $prefix . 'rag_ai_documents';

if ( 2 !== (int) get_option( 'wp_rag_ai_db_version', 0 ) ) {
	$fail( 'Schema version is not 2.' );
}
foreach ( array( $sources, $documents ) as $table ) {
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $found !== $table ) {
		$fail( 'Missing table: ' . $table );
	}
}

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers are derived from $wpdb->prefix only.
$source_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$sources}`", ARRAY_A );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers are derived from $wpdb->prefix only.
$doc_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$documents}`", ARRAY_A );
$index_names = static fn ( array $rows ): array => array_values( array_unique( array_column( $rows, 'Key_name' ) ) );
if ( ! in_array( 'source_key', $index_names( $source_indexes ), true ) ) {
	$fail( 'Missing source_key index.' );
}
if ( ! in_array( 'document_key', $index_names( $doc_indexes ), true ) || ! in_array( 'source_id', $index_names( $doc_indexes ), true ) ) {
	$fail( 'Missing document indexes.' );
}

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifier is derived from $wpdb->prefix only.
$visibility = $wpdb->get_var( "SHOW COLUMNS FROM `{$documents}` LIKE 'visibility'", 1 );
if ( 'varchar(32)' !== $visibility ) {
	$fail( 'Missing document visibility column.' );
}

if ( MigrationStatus::UP_TO_DATE !== DatabaseBootstrap::migrate() ) {
	$fail( 'Repeat migration was not idempotent.' );
}
foreach ( array( 'rag_ai_chunks', 'rag_ai_vectors', 'rag_ai_jobs', 'rag_ai_conversations' ) as $suffix ) {
	$table = $prefix . $suffix;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
		$fail( 'Unexpected future table: ' . $table );
	}
}
