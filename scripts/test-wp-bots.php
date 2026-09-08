<?php
/**
 * Real WordPress bot persistence and REST smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use RuntimeException;
use WpRagAiChatbot\Database\Repository\WpdbBotRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;

global $wpdb;
$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$connection = new WpdbConnection( $wpdb );
$tables     = new TableNames( $wpdb->prefix );
$repository = new WpdbBotRepository( $connection, $tables );

// Keep the smoke deterministic across repeated execution.
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $tables->bots() ) );

$bot_a = $repository->create( 'Support Bot', true, 'openai', 'gpt-5-mini' );
$bot_b = $repository->create( 'Sales Bot', true, 'openrouter', 'anthropic/claude-sonnet-4.5' );

if ( $bot_a->id->value === $bot_b->id->value ) {
	$fail( 'Bot identifiers were not isolated.' );
}
if ( 32 !== strlen( $bot_a->id->value ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $bot_a->id->value ) ) {
	$fail( 'Bot A identifier is not canonical.' );
}

$page = $repository->list( 1, 10 );
if ( 2 !== $page['total'] || 2 !== count( $page['items'] ) || 1 !== $page['page'] || 10 !== $page['per_page'] ) {
	$fail( 'Bot pagination returned unexpected results.' );
}

$updated_a = $repository->update(
	$bot_a->id,
	$bot_a->version,
	'Support Bot Updated',
	false,
	'openai',
	'gpt-5-mini'
);
if ( 2 !== $updated_a->version || $updated_a->enabled || 'Support Bot Updated' !== $updated_a->name ) {
	$fail( 'Bot A update was not persisted.' );
}

$found_b = $repository->find( $bot_b->id );
if ( null === $found_b || 'Sales Bot' !== $found_b->name || ! $found_b->enabled || 1 !== $found_b->version ) {
	$fail( 'Updating bot A mutated bot B.' );
}

$stale_rejected = false;
try {
	$repository->update(
		$bot_a->id,
		$bot_a->version,
		'Stale overwrite',
		true,
		'openai',
		'gpt-5-mini'
	);
} catch ( RuntimeException $exception ) {
	$stale_rejected = true;
}
if ( ! $stale_rejected ) {
	$fail( 'Stale bot update was not rejected.' );
}

if ( ! $repository->delete( $bot_a->id ) || null !== $repository->find( $bot_a->id ) ) {
	$fail( 'Bot A delete did not target exactly one bot.' );
}
if ( null === $repository->find( $bot_b->id ) ) {
	$fail( 'Deleting bot A affected bot B.' );
}

// Verify the same isolation and validation contracts through the real WordPress REST server.
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $tables->bots() ) );
wp_set_current_user( 0 );

$unauthorized = rest_do_request( new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/bots' ) );
if ( $unauthorized->get_status() < 400 ) {
	$fail( 'Unauthenticated bot REST access was not rejected.' );
}

$administrator_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);
if ( empty( $administrator_ids ) ) {
	$fail( 'WordPress REST smoke requires one administrator user.' );
}
wp_set_current_user( (int) $administrator_ids[0] );

$create_request_a = new WP_REST_Request( 'POST', '/wp-rag-ai-chatbot/v1/admin/bots' );
$create_request_a->set_header( 'content-type', 'application/json' );
$create_request_a->set_body(
	wp_json_encode(
		array(
			'name'        => 'REST Support Bot',
			'enabled'     => true,
			'provider_id' => 'openai',
			'model_id'    => 'gpt-5-mini',
		)
	)
);
$create_response_a = rest_do_request( $create_request_a );
$create_data_a     = $create_response_a->get_data();
if ( $create_response_a->get_status() >= 400 || ! is_array( $create_data_a ) || empty( $create_data_a['bot']['id'] ) ) {
	$fail( 'Administrator could not create bot A through REST.' );
}

$create_request_b = new WP_REST_Request( 'POST', '/wp-rag-ai-chatbot/v1/admin/bots' );
$create_request_b->set_header( 'content-type', 'application/json' );
$create_request_b->set_body(
	wp_json_encode(
		array(
			'name'        => 'REST Sales Bot',
			'enabled'     => true,
			'provider_id' => 'openrouter',
			'model_id'    => 'anthropic/claude-sonnet-4.5',
		)
	)
);
$create_response_b = rest_do_request( $create_request_b );
$create_data_b     = $create_response_b->get_data();
if ( $create_response_b->get_status() >= 400 || ! is_array( $create_data_b ) || empty( $create_data_b['bot']['id'] ) ) {
	$fail( 'Administrator could not create bot B through REST.' );
}

$rest_bot_a = $create_data_a['bot'];
$rest_bot_b = $create_data_b['bot'];
if ( $rest_bot_a['id'] === $rest_bot_b['id'] ) {
	$fail( 'REST-created bot identifiers were not isolated.' );
}

$list_request = new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/bots' );
$list_request->set_query_params(
	array(
		'page'     => 1,
		'per_page' => 1,
	)
);
$list_response = rest_do_request( $list_request );
$list_data     = $list_response->get_data();
if (
	$list_response->get_status() >= 400
	|| ! is_array( $list_data )
	|| 2 !== $list_data['total']
	|| 1 !== $list_data['page']
	|| 1 !== $list_data['per_page']
	|| 1 !== count( $list_data['items'] )
) {
	$fail( 'Bot REST pagination returned unexpected results.' );
}

$invalid_page_request = new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/bots' );
$invalid_page_request->set_query_params( array( 'per_page' => 101 ) );
$invalid_page_data = rest_do_request( $invalid_page_request )->get_data();
if ( ! is_array( $invalid_page_data ) || 'invalid_request' !== ( $invalid_page_data['error']['code'] ?? null ) ) {
	$fail( 'Bot REST invalid pagination was not normalized.' );
}

$update_request = new WP_REST_Request( 'PUT', '/wp-rag-ai-chatbot/v1/admin/bots/' . $rest_bot_a['id'] );
$update_request->set_header( 'content-type', 'application/json' );
$update_request->set_body(
	wp_json_encode(
		array(
			'version'     => $rest_bot_a['version'],
			'name'        => 'REST Support Bot Updated',
			'enabled'     => false,
			'provider_id' => 'openai',
			'model_id'    => 'gpt-5-mini',
		)
	)
);
$update_response = rest_do_request( $update_request );
$update_data     = $update_response->get_data();
if (
	$update_response->get_status() >= 400
	|| ! is_array( $update_data )
	|| 'REST Support Bot Updated' !== ( $update_data['bot']['name'] ?? null )
	|| false !== ( $update_data['bot']['enabled'] ?? null )
	|| 2 !== ( $update_data['bot']['version'] ?? null )
) {
	$fail( 'Bot A REST update did not persist the expected state.' );
}

$read_b_response = rest_do_request(
	new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/bots/' . $rest_bot_b['id'] )
);
$read_b_data = $read_b_response->get_data();
if (
	$read_b_response->get_status() >= 400
	|| ! is_array( $read_b_data )
	|| 'REST Sales Bot' !== ( $read_b_data['bot']['name'] ?? null )
	|| 1 !== ( $read_b_data['bot']['version'] ?? null )
) {
	$fail( 'Updating bot A through REST mutated bot B.' );
}

$stale_request = new WP_REST_Request( 'PUT', '/wp-rag-ai-chatbot/v1/admin/bots/' . $rest_bot_a['id'] );
$stale_request->set_header( 'content-type', 'application/json' );
$stale_request->set_body(
	wp_json_encode(
		array(
			'version'     => $rest_bot_a['version'],
			'name'        => 'REST stale overwrite',
			'enabled'     => true,
			'provider_id' => 'openai',
			'model_id'    => 'gpt-5-mini',
		)
	)
);
$stale_data = rest_do_request( $stale_request )->get_data();
if ( ! is_array( $stale_data ) || 'stale_or_missing_bot' !== ( $stale_data['error']['code'] ?? null ) ) {
	$fail( 'Stale REST update was not rejected deterministically.' );
}

$invalid_id_data = rest_do_request(
	new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/bots/not-a-valid-id' )
)->get_data();
if ( ! is_array( $invalid_id_data ) || 'invalid_bot_id' !== ( $invalid_id_data['error']['code'] ?? null ) ) {
	$fail( 'Invalid REST bot identifier was not normalized.' );
}

$delete_response = rest_do_request(
	new WP_REST_Request( 'DELETE', '/wp-rag-ai-chatbot/v1/admin/bots/' . $rest_bot_a['id'] )
);
$delete_data = $delete_response->get_data();
if ( $delete_response->get_status() >= 400 || ! is_array( $delete_data ) || true !== ( $delete_data['deleted'] ?? null ) ) {
	$fail( 'Bot A REST delete failed.' );
}

$read_deleted_data = rest_do_request(
	new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/bots/' . $rest_bot_a['id'] )
)->get_data();
if ( ! is_array( $read_deleted_data ) || 'bot_not_found' !== ( $read_deleted_data['error']['code'] ?? null ) ) {
	$fail( 'Deleted bot remained readable through REST.' );
}

$read_survivor_data = rest_do_request(
	new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/bots/' . $rest_bot_b['id'] )
)->get_data();
if ( ! is_array( $read_survivor_data ) || 'REST Sales Bot' !== ( $read_survivor_data['bot']['name'] ?? null ) ) {
	$fail( 'Deleting bot A through REST affected bot B.' );
}
