<?php
/**
 * Real WordPress M11 conversation repository assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Conversations\ConversationMessage;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\Repository\WpdbConversationRepository;
use WpRagAiChatbot\Database\Repository\WpdbMessageRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;

global $wpdb;
$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$tables                  = new TableNames( $wpdb->prefix );
$connection              = new WpdbConnection( $wpdb );
$conversation_repository = new WpdbConversationRepository( $connection, $tables );
$message_repository      = new WpdbMessageRepository( $connection, $tables );
$owner_scope             = "owner-' OR 1=1 --";
$message_content         = "O'Reilly <script>literal test data</script> مرحبا \" OR 1=1 --";

// Keep this integration assertion deterministic across the lifecycle smoke repetitions.
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $tables->message_citations() ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $tables->messages() ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $tables->conversations() ) );

$conversation = $conversation_repository->create_for_owner( '  ' . $owner_scope . '  ' );
if ( $owner_scope !== $conversation->owner_scope ) {
	$fail( 'Conversation owner scope was not normalized exactly.' );
}

$found = $conversation_repository->find_for_owner( $conversation->conversation_id, $owner_scope );
if ( null === $found || $conversation->conversation_id !== $found->conversation_id || $owner_scope !== $found->owner_scope ) {
	$fail( 'Owner-scoped conversation did not round trip exactly.' );
}
if ( null !== $conversation_repository->find_for_owner( $conversation->conversation_id, 'different-owner' ) ) {
	$fail( 'Cross-owner conversation read was not denied.' );
}

$message_repository->append_for_owner(
	$conversation->conversation_id,
	$owner_scope,
	new ConversationMessage( 'USER', $message_content )
);

$row = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT conversation_id, owner_scope, role, content FROM %i WHERE conversation_id = %s AND owner_scope = %s ORDER BY id ASC LIMIT 1',
		$tables->messages(),
		$conversation->conversation_id,
		$owner_scope
	),
	ARRAY_A
);
if ( ! is_array( $row ) || $conversation->conversation_id !== $row['conversation_id'] || $owner_scope !== $row['owner_scope'] ) {
	$fail( 'Owner-scoped message identifiers did not round trip exactly.' );
}
if ( 'user' !== $row['role'] || $message_content !== $row['content'] ) {
	$fail( 'Message role/content did not round trip exactly.' );
}

$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables->messages() ) );
try {
	$message_repository->append_for_owner(
		$conversation->conversation_id,
		'different-owner',
		new ConversationMessage( 'user', 'must not persist' )
	);
	$fail( 'Cross-owner message append unexpectedly succeeded.' );
} catch ( DatabaseException $exception ) {
	if ( 'Conversation is unavailable for message append.' !== $exception->getMessage() ) {
		$fail( 'Cross-owner append exposed an unexpected persistence diagnostic.' );
	}
}
$after = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables->messages() ) );
if ( $before !== $after ) {
	$fail( 'Cross-owner message append changed persisted state.' );
}
