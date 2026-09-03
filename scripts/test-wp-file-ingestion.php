<?php
/**
 * Real WordPress file-ingestion smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Documents\Extraction\ExtractionException;
use WpRagAiChatbot\Knowledge\KnowledgeBootstrap;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

$attachment_ids = array();
$fixture_paths  = array();

$cleanup = static function () use ( &$attachment_ids, &$fixture_paths ): void {
	foreach ( array_reverse( $attachment_ids ) as $attachment_id ) {
		wp_delete_attachment( $attachment_id, true );
	}
	$attachment_ids = array();

	foreach ( array_reverse( $fixture_paths ) as $fixture_path ) {
		if ( is_string( $fixture_path ) && file_exists( $fixture_path ) ) {
			wp_delete_file( $fixture_path );
		}
	}
	$fixture_paths = array();
};

$fail = static function ( string $message ) use ( $cleanup ): void {
	$cleanup();
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

add_filter(
	'upload_mimes',
	static function ( array $mimes ): array {
		$mimes['txt']  = 'text/plain';
		$mimes['json'] = 'application/json';

		return $mimes;
	}
);

$upload_fixture = static function ( string $filename, string $contents ) use ( &$attachment_ids, &$fixture_paths, $fail ): array {
	$upload = wp_upload_bits( $filename, null, $contents );
	if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		$fail( 'Could not create WordPress file-ingestion fixture: ' . (string) ( $upload['error'] ?? 'unknown upload error' ) );
	}

	$path            = (string) $upload['file'];
	$fixture_paths[] = $path;
	$attachment_id   = wp_insert_attachment(
		array(
			'post_mime_type' => (string) ( $upload['type'] ?? 'application/octet-stream' ),
			'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
			'post_status'    => 'inherit',
		),
		$path
	);
	if ( is_wp_error( $attachment_id ) || $attachment_id < 1 ) {
		$fail( 'Could not persist WordPress file-ingestion attachment fixture.' );
	}

	$attachment_id    = (int) $attachment_id;
	$attachment_ids[] = $attachment_id;

	return array(
		'id'   => $attachment_id,
		'path' => $path,
		'url'  => (string) $upload['url'],
	);
};

$source_record = static function ( int $id, string $key, array $fixture, string $title, string $allowed_root ): KnowledgeSourceRecord {
	$now = new \DateTimeImmutable( '2026-09-03T00:00:00+00:00' );

	return new KnowledgeSourceRecord(
		$id,
		$key,
		'file',
		(string) $fixture['id'],
		$title,
		(string) $fixture['url'],
		'active',
		array(
			'path'         => (string) $fixture['path'],
			'allowed_root' => $allowed_root,
			'language'     => 'en',
			'visibility'   => 'public',
		),
		null,
		null,
		$now,
		$now
	);
};

$single_document = static function ( iterable $documents ) use ( $fail ) {
	$normalized = array_values( is_array( $documents ) ? $documents : iterator_to_array( $documents, false ) );
	if ( 1 !== count( $normalized ) ) {
		$fail( 'File source did not normalize exactly one document.' );
	}

	return $normalized[0];
};

try {
	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['error'] ) || empty( $upload_dir['basedir'] ) ) {
		$fail( 'WordPress upload directory is unavailable for file-ingestion smoke.' );
	}
	$allowed_root = (string) $upload_dir['basedir'];

	KnowledgeBootstrap::register();
	$registry = KnowledgeBootstrap::registry();
	if ( ! $registry->has( 'file' ) ) {
		$fail( 'Native file knowledge source is not registered in real WordPress.' );
	}
	$file_source = $registry->get( 'file' );

	$text_fixture = $upload_fixture( 'm05-smoke.txt', "M05 file ingestion smoke.\nSecond line.\n" );
	$text_source  = $source_record( 900601, 'm05-real-wp-text', $text_fixture, 'M05 text smoke', $allowed_root );
	$text_first   = $single_document( $file_source->documents( $text_source ) );
	$text_second  = $single_document( $file_source->documents( $text_source ) );

	if ( 'file:m05-real-wp-text' !== $text_first->documentKey ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved immutable domain record contract.
		$fail( 'Real WordPress text upload did not produce the stable file document key.' );
	}
	if ( ! str_contains( $text_first->content, 'M05 file ingestion smoke.' ) ) {
		$fail( 'Real WordPress text upload content was not extracted.' );
	}
	if ( $text_first->sourceVersion !== $text_second->sourceVersion || $text_first->contentHash !== $text_second->contentHash ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved immutable domain record contract.
		$fail( 'Equivalent real WordPress file ingestion did not produce stable version/hash values.' );
	}
	$expected_sha = hash_file( 'sha256', (string) $text_fixture['path'] );
	if ( false === $expected_sha || $expected_sha !== ( $text_first->metadata['file_sha256'] ?? null ) ) {
		$fail( 'Real WordPress file metadata did not preserve the validated SHA-256.' );
	}
	if ( basename( (string) $text_fixture['path'] ) !== ( $text_first->metadata['filename'] ?? null ) ) {
		$fail( 'Real WordPress file metadata did not preserve the uploaded filename.' );
	}

	$json_fixture = $upload_fixture( 'm05-smoke.json', "{\"name\":\"WordPress smoke\",\"count\":2}\n" );
	$json_source  = $source_record( 900602, 'm05-real-wp-json', $json_fixture, 'M05 JSON smoke', $allowed_root );
	$json_document = $single_document( $file_source->documents( $json_source ) );
	if ( ! str_contains( $json_document->content, 'WordPress smoke' ) || ! str_contains( $json_document->content, 'count' ) ) {
		$fail( 'Real WordPress JSON upload did not use structured extraction.' );
	}

	$malformed_fixture = $upload_fixture( 'm05-malformed.json', '{"broken":' );
	$malformed_source  = $source_record( 900603, 'm05-real-wp-malformed', $malformed_fixture, 'M05 malformed smoke', $allowed_root );
	$malformed_failed  = false;
	try {
		$single_document( $file_source->documents( $malformed_source ) );
	} catch ( ExtractionException $exception ) {
		$malformed_failed = true;
	}
	if ( ! $malformed_failed ) {
		$fail( 'Malformed real WordPress JSON fixture was not rejected.' );
	}

	$png_bytes       = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQMcAAAAASUVORK5CYII=', true );
	$spoofed_fixture = $upload_fixture( 'm05-spoofed.json', false === $png_bytes ? 'png' : $png_bytes );
	$spoofed_source  = $source_record( 900604, 'm05-real-wp-spoofed', $spoofed_fixture, 'M05 spoofed smoke', $allowed_root );
	$spoofed_failed  = false;
	try {
		$single_document( $file_source->documents( $spoofed_source ) );
	} catch ( ExtractionException $exception ) {
		$spoofed_failed = true;
	}
	if ( ! $spoofed_failed ) {
		$fail( 'Spoofed real WordPress JSON fixture bypassed server-side MIME validation.' );
	}

	$cleanup();
	fwrite( STDOUT, "WordPress file-ingestion smoke passed.\n" );
} catch ( \Throwable $exception ) {
	$fail( 'WordPress file-ingestion smoke threw: ' . $exception->getMessage() );
}
