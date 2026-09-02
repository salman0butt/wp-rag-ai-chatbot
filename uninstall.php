<?php
/**
 * WP RAG AI Chatbot uninstall entry point.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$autoload = __DIR__ . '/vendor/autoload.php';
if ( ! is_readable( $autoload ) ) {
	return;
}

require $autoload;

\WpRagAiChatbot\Database\DatabaseUninstaller::run();
