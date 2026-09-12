<?php
/**
 * Public widget WordPress bootstrap.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

/**
 * Registers the public shortcode and conditionally enqueues widget assets.
 */
final readonly class PublicWidgetBootstrap {
	private const ASSET_HANDLE = 'wp-rag-ai-chatbot-widget';
	private const VERSION      = '0.1.0-dev';

	/**
	 * Create the public widget bootstrap.
	 *
	 * @param PublicWidgetMount $mount Existing closed public widget mount authority.
	 * @param string            $plugin_file Absolute plugin entry-file path.
	 */
	public function __construct(
		private PublicWidgetMount $mount,
		private string $plugin_file
	) {
	}

	/** Register the stable public shortcode. */
	public function register(): void {
		add_shortcode( 'wp_rag_ai_chatbot', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Resolve and render one public widget mount.
	 *
	 * @param array<string,mixed> $attributes Shortcode attributes.
	 */
	public function render_shortcode( array $attributes = array() ): string {
		$config = $this->mount->resolve( $attributes );
		if ( null === $config ) {
			return '';
		}

		$browser_config             = $config->to_array();
		$browser_config['restBase'] = untrailingslashit( rest_url( 'wp-rag-ai-chatbot/v1/' ) );
		$encoded_config             = wp_json_encode( $browser_config );
		if ( false === $encoded_config ) {
			return '';
		}

		wp_enqueue_style(
			self::ASSET_HANDLE,
			plugins_url( 'assets/widget.css', $this->plugin_file ),
			array(),
			self::VERSION
		);
		wp_enqueue_script(
			self::ASSET_HANDLE,
			plugins_url( 'build/widget.js', $this->plugin_file ),
			array( 'wp-element' ),
			self::VERSION,
			true
		);
		wp_add_inline_script(
			self::ASSET_HANDLE,
			'window.wpRagAiChatbotWidgetConfigs = window.wpRagAiChatbotWidgetConfigs || []; window.wpRagAiChatbotWidgetConfigs.push(' . $encoded_config . ');',
			'before'
		);

		return '<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="' . esc_attr( $config->bot_id ) . '"></div>';
	}
}
