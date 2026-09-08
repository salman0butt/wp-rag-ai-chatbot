<?php
/**
 * Real WordPress M12 administration surface smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Admin\AdminBootstrap;

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$administrator_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);
if ( empty( $administrator_ids ) ) {
	$fail( 'M12 admin smoke requires one administrator user.' );
}

wp_set_current_user( 0 );
$unauthorized_bootstrap = rest_do_request(
	new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/bootstrap' )
);
if ( $unauthorized_bootstrap->get_status() < 400 ) {
	$fail( 'Unauthenticated admin bootstrap REST access was not rejected.' );
}

wp_set_current_user( (int) $administrator_ids[0] );

AdminBootstrap::register_menu();
global $menu;
$registered_menu = false;
foreach ( is_array( $menu ) ? $menu : array() as $item ) {
	if ( is_array( $item ) && AdminBootstrap::PAGE_SLUG === ( $item[2] ?? null ) ) {
		$registered_menu = true;
		break;
	}
}
if ( ! $registered_menu ) {
	$fail( 'M12 administration menu was not registered for an administrator.' );
}

ob_start();
AdminBootstrap::render_page();
$mount_html = ob_get_clean();
if ( '<div id="wp-rag-ai-chatbot-admin"></div>' !== $mount_html ) {
	$fail( 'M12 administration page did not render the deterministic React mount boundary.' );
}

$handle = 'wp-rag-ai-chatbot-admin';
wp_dequeue_script( $handle );
wp_dequeue_style( $handle );
AdminBootstrap::enqueue_assets( 'dashboard_page_unrelated' );
if ( wp_script_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'enqueued' ) ) {
	$fail( 'M12 administration assets loaded on an unrelated admin screen.' );
}

AdminBootstrap::enqueue_assets( AdminBootstrap::SCREEN_HOOK );
if ( ! wp_script_is( $handle, 'enqueued' ) || ! wp_style_is( $handle, 'enqueued' ) ) {
	$fail( 'M12 administration assets did not load on the plugin admin screen.' );
}

$before = wp_scripts()->get_data( $handle, 'before' );
$inline = is_array( $before ) ? implode( "\n", $before ) : (string) $before;
if (
	'' === $inline
	|| ! str_contains( $inline, 'wpRagAiChatbotAdminConfig' )
	|| ! str_contains( $inline, 'restBase' )
	|| ! str_contains( $inline, 'nonce' )
) {
	$fail( 'M12 administration boot configuration was not localized before the bundle.' );
}
foreach ( array( 'api_key', 'ciphertext', 'authorization', 'credential":"' ) as $forbidden ) {
	if ( str_contains( strtolower( $inline ), strtolower( $forbidden ) ) ) {
		$fail( 'M12 administration boot configuration contains secret-bearing data.' );
	}
}

$bootstrap_response = rest_do_request(
	new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/bootstrap' )
);
$bootstrap_data = $bootstrap_response->get_data();
if (
	$bootstrap_response->get_status() >= 400
	|| ! is_array( $bootstrap_data )
	|| 'wp-rag-ai-chatbot' !== ( $bootstrap_data['plugin'] ?? null )
	|| 'v1' !== ( $bootstrap_data['api_version'] ?? null )
) {
	$fail( 'Administrator bootstrap REST response was not the expected non-secret contract.' );
}

$credential_response = rest_do_request(
	new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/providers/openai/credential' )
);
$credential_data = $credential_response->get_data();
if (
	$credential_response->get_status() >= 400
	|| ! is_array( $credential_data )
	|| ! is_bool( $credential_data['configured'] ?? null )
	|| ! is_string( $credential_data['source'] ?? null )
) {
	$fail( 'Administrator credential state REST response was not normalized.' );
}
$encoded_credential = wp_json_encode( $credential_data );
if ( ! is_string( $encoded_credential ) ) {
	$fail( 'Administrator credential state could not be serialized.' );
}
foreach ( array( 'ciphertext', 'api_key', 'authorization', 'credential' ) as $forbidden ) {
	if ( str_contains( strtolower( $encoded_credential ), strtolower( $forbidden ) ) ) {
		$fail( 'Administrator credential state exposed secret-bearing data.' );
	}
}

fwrite( STDOUT, "M12 administration WordPress smoke passed.\n" );
