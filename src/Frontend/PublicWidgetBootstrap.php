<?php
/**
 * Public widget WordPress bootstrap.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Database\Repository\WpdbBotAppearanceRepository;
use WpRagAiChatbot\Database\Repository\WpdbBotRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;

/**
 * Registers public widget surfaces and conditionally enqueues widget assets.
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

	/**
	 * Compose persisted production repositories and register the public surfaces.
	 */
	public static function register_default(): void {
		global $wpdb;

		$connection = new WpdbConnection( $wpdb );
		$tables     = new TableNames( $connection->prefix() );
		$bootstrap  = new self(
			new PublicWidgetMount(
				new WidgetConfigResolver(
					new WpdbBotRepository( $connection, $tables ),
					new WpdbBotAppearanceRepository( $connection, $tables )
				)
			),
			dirname( __DIR__, 2 ) . '/wp-rag-ai-chatbot.php'
		);

		$bootstrap->register();
	}

	/** Register the stable public widget adapters. */
	public function register(): void {
		add_shortcode( 'wp_rag_ai_chatbot', array( $this, 'render_shortcode' ) );
		add_shortcode( 'wp_rag_ai_chatbot_embed', array( $this, 'render_embed_shortcode' ) );
		add_shortcode( 'wp_rag_ai_chatbot_fullscreen', array( $this, 'render_fullscreen_shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/** Register the dynamic Gutenberg chatbot block from packaged metadata. */
	public function register_block(): void {
		register_block_type(
			dirname( $this->plugin_file ) . '/blocks/chatbot',
			array( 'render_callback' => array( $this, 'render_block' ) )
		);
	}

	/**
	 * Render the Gutenberg chatbot block through the embedded surface authority.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 */
	public function render_block( array $attributes = array() ): string {
		$bot = $attributes['bot'] ?? null;
		if ( ! is_string( $bot ) ) {
			return '';
		}

		return $this->render_embed_shortcode( array( 'bot' => $bot ) );
	}

	/**
	 * Resolve and render one floating public widget mount.
	 *
	 * @param array<string,mixed> $attributes Shortcode attributes.
	 */
	public function render_shortcode( array $attributes = array() ): string {
		return $this->render_surface( $attributes, 'floating' );
	}

	/**
	 * Resolve and render one embedded public widget mount.
	 *
	 * @param array<string,mixed> $attributes Shortcode attributes.
	 */
	public function render_embed_shortcode( array $attributes = array() ): string {
		return $this->render_surface( $attributes, 'embedded' );
	}

	/**
	 * Resolve and render one fullscreen public widget mount.
	 *
	 * @param array<string,mixed> $attributes Shortcode attributes.
	 */
	public function render_fullscreen_shortcode( array $attributes = array() ): string {
		return $this->render_surface( $attributes, 'fullscreen' );
	}

	/**
	 * Resolve and render one public widget surface through the shared mount authority.
	 *
	 * @param array<string,mixed> $attributes Shortcode attributes.
	 * @param string              $surface Finite browser presentation surface.
	 */
	private function render_surface( array $attributes, string $surface ): string {
		if ( ! in_array( $surface, array( 'floating', 'embedded', 'fullscreen' ), true ) ) {
			return '';
		}

		$config = $this->mount->resolve( $attributes );
		if ( null === $config ) {
			return '';
		}

		$browser_config = array(
			'botId'    => $config->bot_id,
			'restBase' => untrailingslashit( rest_url( 'wp-rag-ai-chatbot/v1/' ) ),
			'surface'  => $surface,
			'config'   => $config->to_array(),
		);
		$encoded_config = wp_json_encode( $browser_config );
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

		return '<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="' . esc_attr( $config->bot_id ) . '" data-wp-rag-ai-chatbot-surface="' . esc_attr( $surface ) . '"></div>';
	}
}
