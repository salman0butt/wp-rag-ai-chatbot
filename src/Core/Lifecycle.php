<?php
/**
 * Plugin lifecycle boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Core;

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
	 * Run plugin deactivation work.
	 *
	 * M02 intentionally has no deactivation side effects.
	 */
	public static function deactivate(): void {
	}
}
