<?php
/**
 * Real WordPress administrator M13 and public-chat REST smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use Throwable;
use WP_REST_Request;
use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;
use WpRagAiChatbot\Frontend\PublicChatRestBootstrap;

$user_id             = 0;
$original_remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;

$cleanup = static function () use ( &$user_id, $original_remote_addr ): void {
	wp_set_current_user( 0 );
	if ( $user_id > 0 ) {
		wp_delete_user( $user_id );
		$user_id = 0;
	}
	if ( null === $original_remote_addr ) {
		unset( $_SERVER['REMOTE_ADDR'] );
	} else {
		$_SERVER['REMOTE_ADDR'] = $original_remote_addr;
	}
};

$fail = static function ( string $message ) use ( $cleanup ): void {
	$cleanup();
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$playground_request = static function ( array $body ): WP_REST_Request {
	$request = new WP_REST_Request( 'POST', '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/debug/playground' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( $body ) );

	return $request;
};

$public_chat_request = static function ( array $body ): WP_REST_Request {
	$request = new WP_REST_Request( 'POST', '/' . PublicChatRestBootstrap::REST_NAMESPACE . PublicChatRestBootstrap::REST_ROUTE );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( $body ) );

	return $request;
};

$knowledge_request = static function (): WP_REST_Request {
	$request = new WP_REST_Request( 'GET', '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/knowledge/sources' );
	$request->set_param( 'page', 1 );
	$request->set_param( 'per_page', 20 );

	return $request;
};

try {
	$server = rest_get_server();
	do_action( 'rest_api_init', $server );

	$routes            = $server->get_routes();
	$playground_route  = '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/debug/playground';
	$knowledge_route   = '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/knowledge/sources';
	$public_chat_route = '/' . PublicChatRestBootstrap::REST_NAMESPACE . PublicChatRestBootstrap::REST_ROUTE;

	if ( ! isset( $routes[ $playground_route ] ) ) {
		$fail( 'Administrator Playground REST route is not registered.' );
	}
	if ( ! isset( $routes[ $knowledge_route ] ) ) {
		$fail( 'Administrator knowledge source REST route is not registered.' );
	}
	if ( ! isset( $routes[ $public_chat_route ] ) ) {
		$fail( 'Public chat REST route is not registered.' );
	}

	wp_set_current_user( 0 );
	$anonymous_playground = rest_do_request(
		$playground_request(
			array(
				'bot_id'        => 'smoke-bot',
				'source_id'     => 1,
				'collection_id' => 'smoke-collection',
				'question'      => 'Smoke question',
			)
		)
	);
	if ( $anonymous_playground->get_status() < 400 ) {
		$fail( 'Anonymous request was allowed to execute the administrator Playground route.' );
	}

	$anonymous_knowledge = rest_do_request( $knowledge_request() );
	if ( $anonymous_knowledge->get_status() < 400 ) {
		$fail( 'Anonymous request was allowed to execute the administrator knowledge source route.' );
	}

	$_SERVER['REMOTE_ADDR'] = '203.0.113.200';
	$public_body = array(
		'bot_id'   => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
		'question' => 'Public smoke question',
	);
	for ( $attempt = 1; $attempt <= 20; $attempt++ ) {
		$public_response = rest_do_request( $public_chat_request( $public_body ) );
		$public_data     = $public_response->get_data();
		if ( 200 !== $public_response->get_status() || 'chat_unavailable' !== ( $public_data['error']['code'] ?? null ) ) {
			$fail( 'Public chat request did not fail closed after passing the abuse-control budget.' );
		}
	}

	$limited_response = rest_do_request( $public_chat_request( $public_body ) );
	$limited_data     = $limited_response->get_data();
	if ( 200 !== $limited_response->get_status() || 'rate_limited' !== ( $limited_data['error']['code'] ?? null ) ) {
		$fail( 'Public chat REST callback did not enforce the abuse limit before runtime work.' );
	}

	$override_response = rest_do_request(
		$public_chat_request(
			array(
				'bot_id'   => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
				'question' => 'Reject public runtime overrides.',
				'provider' => 'openai',
			)
		)
	);
	$override_data = $override_response->get_data();
	if ( 200 !== $override_response->get_status() || 'invalid_request' !== ( $override_data['error']['code'] ?? null ) ) {
		$fail( 'Public chat route accepted an arbitrary request-level runtime override.' );
	}

	$user_id = wp_create_user( 'm13_playground_smoke_admin', wp_generate_password( 32, true, true ), 'm13-playground-smoke@example.test' );
	if ( is_wp_error( $user_id ) ) {
		$fail( 'Could not create administrator M13 REST smoke user: ' . $user_id->get_error_message() );
	}
	$user_id = (int) $user_id;
	$user    = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		$fail( 'Could not load administrator M13 REST smoke user.' );
	}
	$user->set_role( 'administrator' );
	wp_set_current_user( $user_id );

	$knowledge = rest_do_request( $knowledge_request() );
	$knowledge_data = $knowledge->get_data();
	if ( 200 !== $knowledge->get_status() ) {
		$fail( 'Administrator knowledge source request did not reach the bounded M13 resource.' );
	}
	if (
		! is_array( $knowledge_data ) ||
		! isset( $knowledge_data['items'], $knowledge_data['total'], $knowledge_data['page'], $knowledge_data['per_page'] ) ||
		! is_array( $knowledge_data['items'] ) ||
		1 !== $knowledge_data['page'] ||
		20 !== $knowledge_data['per_page']
	) {
		$fail( 'Administrator knowledge source response did not preserve the bounded page projection.' );
	}

	$invalid = rest_do_request( $playground_request( array( 'question' => 'Missing persisted selectors.' ) ) );
	$invalid_data = $invalid->get_data();
	if ( 200 !== $invalid->get_status() || 'invalid_request' !== ( $invalid_data['error']['code'] ?? null ) ) {
		$fail( 'Administrator invalid Playground request did not reach the bounded request parser.' );
	}

	$override = rest_do_request(
		$playground_request(
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
	fwrite( STDOUT, "WordPress M13 administrator and M14 public REST smoke passed.\n" );
} catch ( Throwable $exception ) {
	$fail( 'WordPress REST smoke threw: ' . $exception->getMessage() );
}
