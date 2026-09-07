<?php
/**
 * WordPress admin bootstrap.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin;

use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;

/**
 * Registers the plugin's WordPress admin hooks.
 */
final class AdminBootstrap {
	/**
	 * Register the M12 admin hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( AdminRestBootstrap::class, 'register_routes' ) );
	}

	/**
	 * Register the administration menu page.
	 */
	public static function register_menu(): void {
		// Menu behavior is introduced behind the next Task 1 behavior test.
	}

	/**
	 * Enqueue admin assets only on the plugin administration screen.
	 *
	 * @param string $hook_suffix Current WordPress admin screen hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		unset( $hook_suffix );
		// Asset behavior is introduced behind the next Task 1 behavior test.
	}
}
