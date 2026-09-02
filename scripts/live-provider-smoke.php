<?php
/**
 * Opt-in live provider smoke executed inside WP-CLI.
 *
 * This file is intentionally never run by normal CI. The shell wrapper validates
 * explicit opt-in and credentials before invoking it.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\ProviderBootstrap;
use WpRagAiChatbot\Providers\ProviderIds;

$provider_arg = isset( $args[0] ) && is_string( $args[0] ) ? trim( $args[0] ) : '';
$model_id     = isset( $args[1] ) && is_string( $args[1] ) ? trim( $args[1] ) : '';

$provider_id = match ( $provider_arg ) {
	'openai' => ProviderIds::OPENAI_DIRECT,
	'openrouter' => ProviderIds::OPENROUTER_DIRECT,
	default => null,
};

if ( null === $provider_id ) {
	fwrite( STDERR, "Live provider must be openai or openrouter.\n" );
	exit( 2 );
}

ProviderBootstrap::register();
$registry = ProviderBootstrap::registry();
$catalog  = $registry->catalog( $provider_id );
if ( null === $catalog ) {
	fwrite( STDERR, "Live provider model catalog is unavailable.\n" );
	exit( 1 );
}

$models = $catalog->models();
fwrite( STDOUT, sprintf( "Live %s discovery returned %d models.\n", $provider_arg, count( $models ) ) );

if ( '' === $model_id ) {
	fwrite( STDOUT, "Live generation skipped because no explicit model was supplied.\n" );
	return;
}

$result = $registry->generation( $provider_id )->generate(
	new GenerationRequest(
		$model_id,
		'Reply with exactly the word OK.',
		'Keep this smoke-test response minimal.',
		8
	)
);

if ( '' === trim( $result->text ) ) {
	fwrite( STDERR, "Live generation returned empty text.\n" );
	exit( 1 );
}

fwrite( STDOUT, sprintf( "Live %s generation completed with a non-empty response.\n", $provider_arg ) );
