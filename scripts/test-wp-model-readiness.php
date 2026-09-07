<?php
/**
 * Real WordPress model/readiness REST smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$model_request = static function (): WP_REST_Request {
	$request = new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/models' );
	$request->set_query_params(
		array(
			'provider_id' => 'missing-provider',
			'purpose'     => 'generation',
		)
	);
	return $request;
};

wp_set_current_user( 0 );
$unauthorized = rest_do_request( $model_request() );
if ( $unauthorized->get_status() < 400 ) {
	$fail( 'Unauthenticated model REST access was not rejected.' );
}

$administrator_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);
if ( empty( $administrator_ids ) ) {
	$fail( 'Model REST smoke requires one administrator user.' );
}
wp_set_current_user( (int) $administrator_ids[0] );

$model_response = rest_do_request( $model_request() );
$model_data     = $model_response->get_data();
if (
	$model_response->get_status() >= 400
	|| ! is_array( $model_data )
	|| 'provider_unavailable' !== ( $model_data['error']['code'] ?? null )
) {
	$fail( 'Administrator model REST access did not return the normalized unavailable-provider state.' );
}

$encoded_model_data = wp_json_encode( $model_data );
if ( ! is_string( $encoded_model_data ) || str_contains( strtolower( $encoded_model_data ), 'api_key' ) ) {
	$fail( 'Model REST response exposed credential-shaped metadata.' );
}

$readiness_response = rest_do_request(
	new WP_REST_Request( 'GET', '/wp-rag-ai-chatbot/v1/admin/onboarding/readiness' )
);
$readiness_data = $readiness_response->get_data();
if (
	$readiness_response->get_status() >= 400
	|| ! is_array( $readiness_data )
	|| ! is_bool( $readiness_data['ready'] ?? null )
	|| ! is_string( $readiness_data['next_step'] ?? null )
	|| ! in_array( $readiness_data['next_step'], array( 'provider', 'model', 'first_bot', 'complete' ), true )
) {
	$fail( 'Onboarding readiness REST resource did not return normalized server-derived state.' );
}
