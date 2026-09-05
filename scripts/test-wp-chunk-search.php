<?php
/**
 * Real WordPress chunk-search projection smoke assertions.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalSearchRequest;
use WpRagAiChatbot\Retrieval\Lexical\WpdbChunkSearchStore;

global $wpdb;
$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

foreach ( array( ChunkSearchRecord::class, LexicalFilter::class, LexicalSearchRequest::class, WpdbChunkSearchStore::class ) as $class ) {
	if ( ! class_exists( $class ) ) {
		$fail( 'Missing M10 chunk-search class: ' . $class );
	}
}

$store = new WpdbChunkSearchStore( new WpdbConnection( $wpdb ), new TableNames( $wpdb->prefix ) );
$record = static function (
	string $seed,
	string $document_key,
	int $source_id,
	string $content,
	?string $language = 'en',
	string $visibility = 'public',
	int $sequence = 0
): ChunkSearchRecord {
	return new ChunkSearchRecord(
		hash( 'sha256', $seed ),
		$document_key,
		$source_id,
		'post',
		'Document ' . $document_key,
		'https://example.test/' . rawurlencode( $document_key ),
		$content,
		hash( 'sha256', $content ),
		$language,
		$visibility,
		$sequence,
		array( 'origin' => 'wordpress', 'priority' => $sequence )
	);
};

$store->delete_document( 'knowledge', 'doc-a' );
$store->delete_document( 'knowledge', 'doc-b' );
$store->delete_document( 'other', 'doc-a' );

$first = $record( 'a-1', 'doc-a', 7, 'SKU-42/A installation guide', 'en', 'public', 0 );
$stale = $record( 'a-2', 'doc-a', 7, 'legacy needle content', 'en', 'public', 1 );
$store->replace_document_chunks( 'knowledge', 'doc-a', $first, $stale );

$replacement = $record( 'a-3', 'doc-a', 7, 'SKU-42/A replacement manual', 'en', 'public', 1 );
$store->replace_document_chunks( 'knowledge', 'doc-a', $first, $replacement );
$store->replace_document_chunks( 'knowledge', 'doc-a', $first, $replacement );

$restricted = $record( 'b-1', 'doc-b', 8, 'SKU-42/A private service note', 'de', 'private', 0 );
$store->replace_document_chunks( 'knowledge', 'doc-b', $restricted );
$other_collection = $record( 'c-1', 'doc-a', 9, 'SKU-42/A other collection', 'en', 'public', 0 );
$store->replace_document_chunks( 'other', 'doc-a', $other_collection );

$public_matches = $store->search(
	new LexicalSearchRequest( new LexicalFilter( 'knowledge', null, null, 'en', 'public' ), array( 'sku-42/a' ), 10 )
);
$public_ids = array_map( static fn ( $match ): string => $match->record->chunk_key, $public_matches );
if ( array( $replacement->chunk_key, $first->chunk_key ) !== $public_ids ) {
	$fail( 'Idempotent replacement or public collection/language/visibility filtering failed.' );
}

$stale_matches = $store->search(
	new LexicalSearchRequest( new LexicalFilter( 'knowledge', 'doc-a' ), array( 'legacy' ), 10 )
);
if ( array() !== $stale_matches ) {
	$fail( 'Replacing a document left stale chunk-search rows behind.' );
}

$source_matches = $store->search(
	new LexicalSearchRequest( new LexicalFilter( 'knowledge', null, 8, 'de', 'private' ), array( 'service' ), 10 )
);
if ( 1 !== count( $source_matches ) || $restricted->chunk_key !== $source_matches[0]->record->chunk_key ) {
	$fail( 'Trusted source/language/visibility scope did not isolate the expected chunk.' );
}

$store->delete_document( 'knowledge', 'doc-b' );
if ( array() !== $store->search( new LexicalSearchRequest( new LexicalFilter( 'knowledge', 'doc-b' ), array( 'service' ), 10 ) ) ) {
	$fail( 'Deleting a projected document left searchable rows behind.' );
}

$other_matches = $store->search(
	new LexicalSearchRequest( new LexicalFilter( 'other', 'doc-a' ), array( 'collection' ), 10 )
);
if ( 1 !== count( $other_matches ) || $other_collection->chunk_key !== $other_matches[0]->record->chunk_key ) {
	$fail( 'Deleting one collection/document scope affected another collection.' );
}

$bounded = $store->search(
	new LexicalSearchRequest( new LexicalFilter( 'knowledge', 'doc-a' ), array( 'sku-42/a' ), 1 )
);
if ( 1 !== count( $bounded ) ) {
	$fail( 'Lexical SQL candidate ceiling was not enforced.' );
}
