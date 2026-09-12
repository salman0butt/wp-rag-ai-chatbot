<?php
/**
 * Real WordPress administrator M13 and M14 public REST/widget smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use Throwable;
use WP_REST_Request;
use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Database\Repository\WpdbBotRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;
use WpRagAiChatbot\Frontend\PublicChatRestBootstrap;

$user_id              = 0;
$disabled_bot_id       = null;
$widget_bot_id         = null;
$original_remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;
$widget_asset_handle  = 'wp-rag-ai-chatbot-widget';
$widget_shortcode     = 'wp_rag_ai_chatbot';

$cleanup = static function () use ( &$user_id, &$disabled_bot_id, &$widget_bot_id, $original_remote_addr ): void {
	wp_set_current_user( 0 );
	if ( $user_id > 0 ) {
		wp_delete_user( $user_id );
		$user_id = 0;
	}
	if ( null !== $disabled_bot_id || null !== $widget_bot_id ) {
		global $wpdb;
		$connection = new WpdbConnection( $wpdb );
		$repository = new WpdbBotRepository( $connection, new TableNames( $connection->prefix() ) );
		if ( null !== $disabled_bot_id ) {
			$repository->delete( new BotId( $disabled_bot_id ) );
			$disabled_bot_id = null;
		}
		if ( null !== $widget_bot_id ) {
			$repository->delete( new BotId( $widget_bot_id ) );
			$widget_bot_id = null;
		}
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
	if ( ! shortcode_exists( $widget_shortcode ) ) {
		$fail( 'Public widget shortcode is not registered after plugin activation.' );
	}
	if ( wp_script_is( $widget_asset_handle, 'enqueued' ) || wp_style_is( $widget_asset_handle, 'enqueued' ) ) {
		$fail( 'Public widget assets were enqueued before a valid widget mount was rendered.' );
	}
	if ( '' !== do_shortcode( '[wp_rag_ai_chatbot]' ) ) {
		$fail( 'Public widget shortcode without a bot identifier did not fail closed.' );
	}
	if ( wp_script_is( $widget_asset_handle, 'enqueued' ) || wp_style_is( $widget_asset_handle, 'enqueued' ) ) {
		$fail( 'Invalid public widget mount enqueued public assets.' );
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

	$malformed_response = rest_do_request(
		$public_chat_request(
			array(
				'bot_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
			)
		)
	);
	$malformed_data = $malformed_response->get_data();
	if ( 200 !== $malformed_response->get_status() || 'invalid_request' !== ( $malformed_data['error']['code'] ?? null ) ) {
		$fail( 'Public chat route did not reject malformed input before runtime work.' );
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

	global $wpdb;
	$connection      = new WpdbConnection( $wpdb );
	$bot_repository  = new WpdbBotRepository( $connection, new TableNames( $connection->prefix() ) );
	$disabled_bot    = $bot_repository->create( 'M14 disabled smoke bot', false, 'openai', 'smoke-model' );
	$disabled_bot_id = $disabled_bot->id->value;

	$_SERVER['REMOTE_ADDR'] = '203.0.113.201';
	$disabled_response = rest_do_request(
		$public_chat_request(
			array(
				'bot_id'   => $disabled_bot_id,
				'question' => 'Disabled bot must stay unavailable.',
			)
		)
	);
	$disabled_data = $disabled_response->get_data();
	if ( 200 !== $disabled_response->get_status() || 'chat_unavailable' !== ( $disabled_data['error']['code'] ?? null ) ) {
		$fail( 'Disabled public bot did not fail closed as unavailable.' );
	}
	$disabled_widget = do_shortcode( '[wp_rag_ai_chatbot bot="' . esc_attr( $disabled_bot_id ) . '"]' );
	if ( '' !== $disabled_widget ) {
		$fail( 'Disabled bot shortcode did not fail closed.' );
	}
	if ( wp_script_is( $widget_asset_handle, 'enqueued' ) || wp_style_is( $widget_asset_handle, 'enqueued' ) ) {
		$fail( 'Disabled bot shortcode enqueued public widget assets.' );
	}

	$widget_bot    = $bot_repository->create( 'M14 widget smoke bot', true, 'smoke-secret-provider', 'smoke-secret-model' );
	$widget_bot_id = $widget_bot->id->value;
	$widget_html   = do_shortcode( '[wp_rag_ai_chatbot bot="' . esc_attr( $widget_bot_id ) . '"]' );
	if ( ! str_contains( $widget_html, 'class="wp-rag-ai-chatbot-widget"' ) || ! str_contains( $widget_html, $widget_bot_id ) ) {
		$fail( 'Enabled public bot shortcode did not render the deterministic widget mount.' );
	}
	if ( ! wp_script_is( $widget_asset_handle, 'enqueued' ) || ! wp_style_is( $widget_asset_handle, 'enqueued' ) ) {
		$fail( 'Enabled public bot shortcode did not conditionally enqueue widget assets.' );
	}

	$inline_before = wp_scripts()->get_data( $widget_asset_handle, 'before' );
	$inline_script = is_array( $inline_before ) ? implode( "\n", $inline_before ) : (string) $inline_before;
	if ( ! str_contains( $inline_script, $widget_bot_id ) || ! str_contains( $inline_script, 'wp-rag-ai-chatbot/v1' ) ) {
		$fail( 'Public widget bootstrap data is missing the public bot identity or REST base.' );
	}
	foreach ( array( 'smoke-secret-provider', 'smoke-secret-model', 'retrieval_limit', 'vector_store', 'credential' ) as $forbidden ) {
		if ( str_contains( $inline_script, $forbidden ) ) {
			$fail( 'Public widget bootstrap leaked forbidden runtime authority: ' . $forbidden );
		}
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

	$knowledge      = rest_do_request( $knowledge_request() );
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

	$invalid      = rest_do_request( $playground_request( array( 'question' => 'Missing persisted selectors.' ) ) );
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
	fwrite( STDOUT, "WordPress M13 administrator and M14 public REST/widget smoke passed.\n" );
} catch ( Throwable $exception ) {
	$fail( 'WordPress REST/widget smoke threw: ' . $exception->getMessage() );
}
