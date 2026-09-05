<?php
/**
 * Plugin lifecycle boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Core;

use WpRagAiChatbot\Jobs\WordPressJobCron;

/**
 * Owns activation and deactivation callbacks.
 */
final class Lifecycle {
	/**
	 * Run plugin activation work.
	 */
	public static function activate(): void {
		do_action( 'wp_rag_ai_chatbot_activate' );
	}

	/**
	 * Remove plugin-owned schedules on deactivation.
	 */
	public static function deactivate(): void {
		WordPressJobCron::unschedule();
	}
}
