<?php
/**
 * WordPress admin capability policy.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin;

/**
 * Centralizes authorization for the plugin administration control plane.
 */
final class AdminCapability {
	/**
	 * WordPress capability required to administer the plugin.
	 */
	public const MANAGE = 'manage_options';

	/**
	 * Determine whether the current user may administer the plugin.
	 */
	public static function can_manage(): bool {
		return current_user_can( self::MANAGE );
	}
}
