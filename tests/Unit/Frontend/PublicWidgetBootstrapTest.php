<?php
/**
 * Public widget WordPress bootstrap tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Frontend\AppearanceConfig;
use WpRagAiChatbot\Frontend\BotAppearanceRepository;
use WpRagAiChatbot\Frontend\PublicWidgetBootstrap;
use WpRagAiChatbot\Frontend\PublicWidgetMount;
use WpRagAiChatbot\Frontend\WidgetConfigResolver;

/**
 * Verifies shortcode registration, fail-closed mounting, and conditional public assets.
 */
final class PublicWidgetBootstrapTest extends TestCase {
	private const BOT_ID      = '0123456789abcdef0123456789abcdef';
	private const PLUGIN_FILE = '/tmp/wp-rag-ai-chatbot/wp-rag-ai-chatbot.php';

	/** Start Brain Monkey before each test. */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/** Tear Brain Monkey down after each test. */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Register wires the stable public shortcode exactly once. */
	public function test_register_wires_stable_public_shortcode(): void {
		$bootstrap = $this->bootstrap_with_empty_repositories();

		Functions\expect( 'add_shortcode' )
			->once()
			->with( 'wp_rag_ai_chatbot', array( $bootstrap, 'render_shortcode' ) );

		$bootstrap->register();
	}

	/** Invalid or missing bot mounts fail closed without public assets. */
	public function test_invalid_mount_fails_closed_without_enqueuing_assets(): void {
		$bootstrap = $this->bootstrap_with_empty_repositories();

		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_enqueue_script' )->never();
		Functions\expect( 'wp_add_inline_script' )->never();

		self::assertSame( '', $bootstrap->render_shortcode( array() ) );
		self::assertSame( '', $bootstrap->render_shortcode( array( 'bot' => 'invalid' ) ) );
	}

	/** Enabled bots render one deterministic mount and enqueue public assets only then. */
	public function test_enabled_bot_mount_renders_public_projection_and_conditionally_enqueues_assets(): void {
		$bot_id      = new BotId( self::BOT_ID );
		$bots        = $this->createMock( BotRepository::class );
		$appearances = $this->createMock( BotAppearanceRepository::class );
		$bot         = new Bot(
			$bot_id,
			'Support',
			true,
			'openai',
			'gpt-5',
			1,
			'2026-09-12 00:00:00',
			'2026-09-12 00:00:00'
		);

		$bots->expects( self::once() )->method( 'find' )->willReturn( $bot );
		$appearances->expects( self::once() )->method( 'find' )->willReturn( AppearanceConfig::defaults() );

		Functions\when( 'plugins_url' )->alias(
			static fn ( string $path, string $plugin_file ): string => str_ends_with( $plugin_file, 'wp-rag-ai-chatbot.php' )
				? 'https://example.test/plugins/wp-rag-ai-chatbot/' . $path
				: ''
		);
		Functions\when( 'rest_url' )->justReturn( 'https://example.test/wp-json/wp-rag-ai-chatbot/v1/' );
		Functions\when( 'untrailingslashit' )->alias( static fn ( string $value ): string => rtrim( $value, '/' ) );
		Functions\when( 'wp_json_encode' )->alias(
			static fn ( array $value ): string => json_encode( $value, JSON_THROW_ON_ERROR )
		);
		Functions\when( 'esc_attr' )->alias(
			static fn ( string $value ): string => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' )
		);

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'wp-rag-ai-chatbot-widget',
				'https://example.test/plugins/wp-rag-ai-chatbot/assets/widget.css',
				array(),
				'0.1.0-dev'
			);
		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'wp-rag-ai-chatbot-widget',
				'https://example.test/plugins/wp-rag-ai-chatbot/build/widget.js',
				array( 'wp-element' ),
				'0.1.0-dev',
				true
			);
		Functions\expect( 'wp_add_inline_script' )
			->once()
			->with(
				'wp-rag-ai-chatbot-widget',
				self::callback(
					static function ( string $script ): bool {
						return str_contains( $script, '"botId":"' . self::BOT_ID . '"' )
							&& str_contains( $script, '"restBase":"https://example.test/wp-json/wp-rag-ai-chatbot/v1"' )
							&& str_contains( $script, '"config":{"bot_id":"' . self::BOT_ID . '","name":"Support"' )
							&& ! str_contains( $script, 'openai' )
							&& ! str_contains( $script, 'gpt-5' )
							&& ! str_contains( $script, 'retrieval_limit' );
					}
				),
				'before'
			);

		$bootstrap = new PublicWidgetBootstrap(
			new PublicWidgetMount( new WidgetConfigResolver( $bots, $appearances ) ),
			self::PLUGIN_FILE
		);

		self::assertSame(
			'<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="' . self::BOT_ID . '"></div>',
			$bootstrap->render_shortcode( array( 'bot' => self::BOT_ID ) )
		);
	}

	/**
	 * Build a bootstrap whose repositories must never be queried.
	 */
	private function bootstrap_with_empty_repositories(): PublicWidgetBootstrap {
		$bots        = $this->createMock( BotRepository::class );
		$appearances = $this->createMock( BotAppearanceRepository::class );
		$bots->expects( self::never() )->method( 'find' );
		$appearances->expects( self::never() )->method( 'find' );

		return new PublicWidgetBootstrap(
			new PublicWidgetMount( new WidgetConfigResolver( $bots, $appearances ) ),
			self::PLUGIN_FILE
		);
	}
}
