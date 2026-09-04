<?php
/**
 * Real WordPress database migration and repository smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Database\DatabaseBootstrap;
use WpRagAiChatbot\Database\MigrationStatus;
use WpRagAiChatbot\Database\Repository\WpdbDocumentRepository;
use WpRagAiChatbot\Database\Repository\WpdbKnowledgeSourceRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

global $wpdb;
$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$prefix             = $wpdb->prefix;
$sources            = $prefix . 'rag_ai_sources';
$documents          = $prefix . 'rag_ai_documents';
$vector_collections = $prefix . 'rag_ai_vector_collections';
$vectors            = $prefix . 'rag_ai_vectors';

if ( 4 !== (int) get_option( 'wp_rag_ai_db_version', 0 ) ) {
	$fail( 'Schema version is not 4.' );
}
foreach ( array( $sources, $documents, $vector_collections, $vectors ) as $table ) {
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $found !== $table ) {
		$fail( 'Missing table: ' . $table );
	}
}

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers are derived from $wpdb->prefix only.
$source_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$sources}`", ARRAY_A );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers are derived from $wpdb->prefix only.
$doc_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$documents}`", ARRAY_A );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers are derived from $wpdb->prefix only.
$vector_collection_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$vector_collections}`", ARRAY_A );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers are derived from $wpdb->prefix only.
$vector_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$vectors}`", ARRAY_A );
$index_names = static fn ( array $rows ): array => array_values( array_unique( array_column( $rows, 'Key_name' ) ) );
if ( ! in_array( 'source_key', $index_names( $source_indexes ), true ) ) {
	$fail( 'Missing source_key index.' );
}
if ( ! in_array( 'document_key', $index_names( $doc_indexes ), true ) || ! in_array( 'source_id', $index_names( $doc_indexes ), true ) ) {
	$fail( 'Missing document indexes.' );
}
if ( ! in_array( 'collection_key', $index_names( $vector_collection_indexes ), true ) ) {
	$fail( 'Missing vector collection_key index.' );
}
if ( ! in_array( 'collection_vector', $index_names( $vector_indexes ), true ) || ! in_array( 'collection_fingerprint', $index_names( $vector_indexes ), true ) ) {
	$fail( 'Missing vector indexes.' );
}

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifier is derived from $wpdb->prefix only.
$visibility = $wpdb->get_var( "SHOW COLUMNS FROM `{$documents}` LIKE 'visibility'", 1 );
if ( 'varchar(32)' !== $visibility ) {
	$fail( 'Missing document visibility column.' );
}

if ( MigrationStatus::UP_TO_DATE !== DatabaseBootstrap::migrate() ) {
	$fail( 'Repeat migration was not idempotent.' );
}
foreach ( array( 'rag_ai_chunks', 'rag_ai_jobs', 'rag_ai_conversations' ) as $suffix ) {
	$table = $prefix . $suffix;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
		$fail( 'Unexpected future table: ' . $table );
	}
}

// Keep repository assertions deterministic when this script is run again after an upgrade simulation.
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifier is derived from $wpdb->prefix only.
$wpdb->query( "DELETE FROM `{$documents}`" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifier is derived from $wpdb->prefix only.
$wpdb->query( "DELETE FROM `{$sources}`" );

$connection          = new WpdbConnection( $wpdb );
$tables              = new TableNames( $wpdb->prefix );
$source_repository   = new WpdbKnowledgeSourceRepository( $connection, $tables );
$document_repository = new WpdbDocumentRepository( $connection, $tables );
$timestamp           = new DateTimeImmutable( '2026-09-02 00:00:00', new DateTimeZone( 'UTC' ) );
$malicious_source_key = "source-' OR 1=1 --";
$source_records       = array();

for ( $i = 1; $i <= 25; $i++ ) {
	$source_key = 13 === $i ? $malicious_source_key : sprintf( 'source-%02d', $i );
	$record     = new KnowledgeSourceRecord(
		null,
		$source_key,
		'manual',
		'ext-' . $i,
		'Source ' . $i,
		'https://example.test/source/' . $i,
		'active',
		array( 'position' => $i, 'literal' => "O'Reilly" ),
		hash( 'sha256', $source_key ),
		null,
		$timestamp,
		$timestamp
	);
	$saved      = $source_repository->save( $record );
	if ( null === $saved->id ) {
		$fail( 'Saved source did not receive an ID.' );
	}
	$source_records[] = $saved;
}

$source_a = $source_records[12];
$source_b = $source_records[13];
$found_source = $source_repository->findByKey( $malicious_source_key );
if ( null === $found_source || $found_source->sourceKey !== $malicious_source_key || $found_source->id !== $source_a->id ) {
	$fail( 'Malicious-looking source key did not round trip exactly.' );
}

$page_1 = $source_repository->paginate( 1, 10 );
$page_2 = $source_repository->paginate( 2, 10 );
$page_3 = $source_repository->paginate( 3, 10 );
if ( 25 !== $page_1->total || 10 !== count( $page_1->items ) || 10 !== count( $page_2->items ) || 5 !== count( $page_3->items ) ) {
	$fail( 'Source pagination returned unexpected totals.' );
}
if ( 100 !== $source_repository->paginate( 1, 1000 )->perPage ) {
	$fail( 'Source pagination did not clamp per-page to 100.' );
}

if ( null === $source_a->id || null === $source_b->id ) {
	$fail( 'Repository source IDs were unexpectedly missing.' );
}

$malicious_document_key = "document-' OR 1=1 --";
$malicious_metadata = array(
	'quote'   => "O'Reilly",
	'script'  => '<script>literal test data</script>',
	'unicode' => 'مرحبا',
	'sql'     => '" OR 1=1 --',
);
$malicious_content = "O'Reilly <script>literal test data</script> مرحبا \" OR 1=1 --";

for ( $i = 1; $i <= 23; $i++ ) {
	$source_id    = $i <= 12 ? $source_a->id : $source_b->id;
	$document_key = 7 === $i ? $malicious_document_key : sprintf( 'document-%02d', $i );
	$content      = $malicious_content . ' #' . $i;
	$metadata     = $malicious_metadata + array( 'position' => $i );
	$record       = new DocumentRecord(
		null,
		$document_key,
		$source_id,
		'doc-ext-' . $i,
		'page',
		'Document ' . $i,
		'https://example.test/document/' . $i,
		$content,
		$metadata,
		'v1',
		hash( 'sha256', $content ),
		'ar',
		'public',
		$timestamp,
		$timestamp
	);
	$document_repository->save( $record );
}

$found_document = $document_repository->findByKey( $malicious_document_key );
if ( null === $found_document || $found_document->documentKey !== $malicious_document_key ) {
	$fail( 'Malicious-looking document key did not round trip exactly.' );
}
if ( $malicious_metadata + array( 'position' => 7 ) !== $found_document->metadata ) {
	$fail( 'Document metadata did not round trip through JSON storage.' );
}
if ( $malicious_content . ' #7' !== $found_document->content ) {
	$fail( 'Document literal content did not round trip exactly.' );
}

$documents_a_page_1 = $document_repository->paginateBySource( $source_a->id, 1, 10 );
$documents_a_page_2 = $document_repository->paginateBySource( $source_a->id, 2, 10 );
if ( 12 !== $documents_a_page_1->total || 10 !== count( $documents_a_page_1->items ) || 2 !== count( $documents_a_page_2->items ) ) {
	$fail( 'Source-scoped document pagination returned unexpected totals.' );
}
foreach ( array_merge( $documents_a_page_1->items, $documents_a_page_2->items ) as $document ) {
	if ( $source_a->id !== $document->sourceId ) {
		$fail( 'Source-scoped document pagination leaked another source.' );
	}
}

$deleted = $document_repository->deleteBySource( $source_a->id );
if ( 12 !== $deleted ) {
	$fail( 'Deleting source A documents returned an unexpected affected count.' );
}
if ( 0 !== $document_repository->paginateBySource( $source_a->id, 1, 10 )->total ) {
	$fail( 'Source A documents were not deleted.' );
}
if ( 11 !== $document_repository->paginateBySource( $source_b->id, 1, 100 )->total ) {
	$fail( 'Deleting source A documents affected source B.' );
}
