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
	 *
	 * M01 intentionally has no activation side effects.
	 */
	public static function activate(): void {
	}

	/**
	 * Run plugin deactivation work.
	 *
	 * M01 intentionally has no deactivation side effects.
	 */
	public static function deactivate(): void {
	}
}
