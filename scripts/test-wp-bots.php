<?php
/**
 * Real WordPress bot persistence smoke assertions.
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
