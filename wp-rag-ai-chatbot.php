<?php
/**
 * Plugin Name: WP RAG AI Chatbot
 * Description: WordPress-native AI chatbot and RAG platform.
 * Version: 0.1.0-dev
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: Salman Butt
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-rag-ai-chatbot
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

use WpRagAiChatbot\Core\Bootstrap;

defined( 'ABSPATH' ) || exit;

$autoload = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	throw new RuntimeException( 'WP RAG AI Chatbot dependencies are missing. Run composer install or install a packaged release.' );
}

require $autoload;

Bootstrap::register( __FILE__ );
