<?php
/**
 * Real WordPress knowledge source smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use DateTimeImmutable;
use Throwable;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\WordPressPostSource;
use WpRagAiChatbot\Knowledge\WordPress\NativeWordPressContentGateway;

$post_ids = array();

$cleanup = static function () use ( &$post_ids ): void {
	foreach ( array_reverse( $post_ids ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	$post_ids = array();
};

$fail = static function ( string $message ) use ( $cleanup ): void {
	$cleanup();
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$insert = static function ( array $postarr ) use ( &$post_ids, $fail ): int {
	$post_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $post_id ) ) {
		$fail( 'Could not create WordPress knowledge smoke fixture: ' . $post_id->get_error_message() );
	}
	$post_id    = (int) $post_id;
	$post_ids[] = $post_id;

	return $post_id;
};

$documents_by_key = static function ( iterable $documents ): array {
	$result = array();
	foreach ( $documents as $document ) {
		$result[ $document->documentKey ] = $document; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved immutable domain record contract.
	}

	return $result;
};

try {
	$published_page_id = $insert(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'M04 Smoke Page',
			'post_excerpt' => 'Page smoke excerpt.',
			'post_content' => '<p>Page smoke <strong>body</strong>.</p>',
		)
	);
	$published_post_id = $insert(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'M04 Smoke Post',
			'post_content' => '<p>Published smoke post body.</p>',
		)
	);
	$private_post_id = $insert(
		array(
			'post_type'    => 'post',
			'post_status'  => 'private',
			'post_title'   => 'M04 Private Smoke Post',
			'post_content' => 'Private smoke content.',
		)
	);
	$draft_post_id = $insert(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => 'M04 Draft Smoke Post',
			'post_content' => 'Draft smoke content.',
		)
	);
	$password_post_id = $insert(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => 'M04 Password Smoke Post',
			'post_content'  => 'Password smoke content.',
			'post_password' => 'm04-smoke-password',
		)
	);

	$now     = new DateTimeImmutable( '2026-09-03T00:00:00+00:00' );
	$gateway = new NativeWordPressContentGateway();
	$source  = new WordPressPostSource( $gateway );

	$public_source = new KnowledgeSourceRecord(
		900401,
		'm04-real-wp-public',
		'wordpress_posts',
		null,
		'M04 real WordPress public smoke',
		null,
		'active',
		array(),
		null,
		null,
		$now,
		$now
	);
	$private_source = new KnowledgeSourceRecord(
		900402,
		'm04-real-wp-private',
		'wordpress_posts',
		null,
		'M04 real WordPress private smoke',
		null,
		'active',
		array( 'include_private' => true ),
		null,
		null,
		$now,
		$now
	);

	$public_documents = $documents_by_key( $source->documents( $public_source ) );
	$page_key         = 'wp-post:page:' . $published_page_id;
	$post_key         = 'wp-post:post:' . $published_post_id;
	$private_key      = 'wp-post:post:' . $private_post_id;
	$draft_key        = 'wp-post:post:' . $draft_post_id;
	$password_key     = 'wp-post:post:' . $password_post_id;

	foreach ( array( $page_key, $post_key ) as $required_key ) {
		if ( ! isset( $public_documents[ $required_key ] ) ) {
			$fail( 'Published WordPress fixture was not normalized: ' . $required_key );
		}
	}
	foreach ( array( $private_key, $draft_key, $password_key ) as $forbidden_key ) {
		if ( isset( $public_documents[ $forbidden_key ] ) ) {
			$fail( 'Non-public WordPress fixture leaked into default normalization: ' . $forbidden_key );
		}
	}

	$page_document = $public_documents[ $page_key ];
	if ( 'public' !== $page_document->visibility ) {
		$fail( 'Published page did not normalize with public visibility.' );
	}
	if ( 'M04 Smoke Page' !== $page_document->title ) {
		$fail( 'Published page title was not normalized deterministically.' );
	}
	if ( str_contains( $page_document->content, '<' ) || ! str_contains( $page_document->content, 'Page smoke body.' ) ) {
		$fail( 'Published page HTML was not converted to normalized text.' );
	}
	if ( get_permalink( $published_page_id ) !== $page_document->canonicalUrl ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved immutable domain record contract.
		$fail( 'Published page canonical URL does not match WordPress permalink.' );
	}
	if ( (string) $published_page_id !== $page_document->externalId ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved immutable domain record contract.
		$fail( 'Published page external identifier is not traceable to the WordPress post ID.' );
	}

	$second_public_pass = $documents_by_key( $source->documents( $public_source ) );
	if ( ! isset( $second_public_pass[ $page_key ] ) || $page_document->contentHash !== $second_public_pass[ $page_key ]->contentHash ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved immutable domain record contract.
		$fail( 'Equivalent WordPress content did not produce a stable document hash.' );
	}
	if ( $page_document->documentKey !== $second_public_pass[ $page_key ]->documentKey ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved immutable domain record contract.
		$fail( 'Equivalent WordPress content did not produce a stable document key.' );
	}

	$private_documents = $documents_by_key( $source->documents( $private_source ) );
	if ( ! isset( $private_documents[ $private_key ] ) || 'private' !== $private_documents[ $private_key ]->visibility ) {
		$fail( 'Explicit private opt-in did not normalize the private WordPress fixture as private.' );
	}
	foreach ( array( $draft_key, $password_key ) as $forbidden_key ) {
		if ( isset( $private_documents[ $forbidden_key ] ) ) {
			$fail( 'Draft/password-protected WordPress fixture leaked with private opt-in: ' . $forbidden_key );
		}
	}

	$cleanup();
	fwrite( STDOUT, "WordPress knowledge smoke passed.\n" );
} catch ( Throwable $exception ) {
	$fail( 'WordPress knowledge smoke threw: ' . $exception->getMessage() );
}
