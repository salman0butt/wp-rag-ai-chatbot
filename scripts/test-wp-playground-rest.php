<?php
/**
 * Real WordPress administrator Playground REST smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use Throwable;
use WP_REST_Request;
use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;

$user_id = 0;

$cleanup = static function () use ( &$user_id ): void {
	wp_set_current_user( 0 );
	if ( $user_id > 0 ) {
		wp_delete_user( $user_id );
		$user_id = 0;
	}
};

$fail = static function ( string $message ) use ( $cleanup ): void {
	$cleanup();
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$request = static function ( array $body ): WP_REST_Request {
	$request = new WP_REST_Request( 'POST', '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/debug/playground' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( $body ) );

	return $request;
};

try {
	$server = rest_get_server();
	do_action( 'rest_api_init', $server );

	$route = '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/debug/playground';
	if ( ! isset( $server->get_routes()[ $route ] ) ) {
		$fail( 'Administrator Playground REST route is not registered.' );
	}

	wp_set_current_user( 0 );
	$anonymous = rest_do_request(
		$request(
			array(
				'bot_id'        => 'smoke-bot',
				'source_id'     => 1,
				'collection_id' => 'smoke-collection',
				'question'      => 'Smoke question',
			)
		)
	);
	if ( $anonymous->get_status() < 400 ) {
		$fail( 'Anonymous request was allowed to execute the administrator Playground route.' );
	}

	$user_id = wp_create_user( 'm13_playground_smoke_admin', wp_generate_password( 32, true, true ), 'm13-playground-smoke@example.test' );
	if ( is_wp_error( $user_id ) ) {
		$fail( 'Could not create administrator Playground smoke user: ' . $user_id->get_error_message() );
	}
	$user_id = (int) $user_id;
	$user    = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		$fail( 'Could not load administrator Playground smoke user.' );
	}
	$user->set_role( 'administrator' );
	wp_set_current_user( $user_id );

	$invalid = rest_do_request( $request( array( 'question' => 'Missing persisted selectors.' ) ) );
	$invalid_data = $invalid->get_data();
	if ( 200 !== $invalid->get_status() || 'invalid_request' !== ( $invalid_data['error']['code'] ?? null ) ) {
		$fail( 'Administrator invalid Playground request did not reach the bounded request parser.' );
	}

	$override = rest_do_request(
		$request(
			array(
				'bot_id'        => 'smoke-bot',
				'source_id'     => 1,
				'collection_id' => 'smoke-collection',
				'question'      => 'Reject runtime overrides.',
				'provider'      => 'openai',
			)
		)
	);
	$override_data = $override->get_data();
	if ( 200 !== $override->get_status() || 'invalid_request' !== ( $override_data['error']['code'] ?? null ) ) {
		$fail( 'Playground route accepted an arbitrary request-level runtime override.' );
	}

	$cleanup();
	fwrite( STDOUT, "WordPress Playground REST smoke passed.\n" );
} catch ( Throwable $exception ) {
	$fail( 'WordPress Playground REST smoke threw: ' . $exception->getMessage() );
}
