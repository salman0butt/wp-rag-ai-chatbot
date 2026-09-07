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
	 * WordPress menu slug for the administration application.
	 */
	public const PAGE_SLUG = 'wp-rag-ai-chatbot';

	/**
	 * WordPress hook suffix for the top-level administration screen.
	 */
	public const SCREEN_HOOK = 'toplevel_page_wp-rag-ai-chatbot';

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
		add_menu_page(
			'WP RAG AI Chatbot',
			'WP RAG AI',
			AdminCapability::MANAGE,
			self::PAGE_SLUG,
			array( self::class, 'render_page' ),
			'dashicons-format-chat'
		);
	}

	/**
	 * Render the deterministic mount boundary for the admin application.
	 */
	public static function render_page(): void {
		echo '<div id="wp-rag-ai-chatbot-admin"></div>';
	}

	/**
	 * Determine whether a WordPress admin hook suffix belongs to this plugin.
	 *
	 * @param string $hook_suffix Current WordPress admin screen hook suffix.
	 */
	public static function is_plugin_screen( string $hook_suffix ): bool {
		return self::SCREEN_HOOK === $hook_suffix;
	}

	/**
	 * Enqueue admin assets only on the plugin administration screen.
	 *
	 * @param string $hook_suffix Current WordPress admin screen hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! self::is_plugin_screen( $hook_suffix ) ) {
			return;
		}

		// The React admin bundle is introduced in M12 Task 6.
	}
}
