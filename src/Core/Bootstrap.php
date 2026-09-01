<?php
/**
 * WordPress hook bootstrap.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Core;

final class Bootstrap {
	/**
	 * Register the plugin's foundation hooks.
	 *
	 * @param string $plugin_file Absolute plugin entry-file path.
	 */
	public static function register( string $plugin_file ): void {
		register_activation_hook( $plugin_file, array( Lifecycle::class, 'activate' ) );
		register_deactivation_hook( $plugin_file, array( Lifecycle::class, 'deactivate' ) );
		add_action( 'plugins_loaded', array( self::class, 'load' ) );
	}

	/**
	 * Signal that the foundation bootstrap has loaded.
	 */
	public static function load(): void {
		do_action( 'wp_rag_ai_chatbot_loaded' );
	}
}
