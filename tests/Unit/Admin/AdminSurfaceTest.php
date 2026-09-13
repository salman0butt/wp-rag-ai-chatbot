<?php
/**
 * Admin surface tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\AdminBootstrap;
use WpRagAiChatbot\Admin\AdminCapability;
use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;

/**
 * Verifies the minimal protected WordPress administration surface.
 */
final class AdminSurfaceTest extends TestCase {
	/**
	 * Start Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear Brain Monkey down after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The plugin page must be registered behind the centralized capability.
	 */
	#[DoesNotPerformAssertions]
	public function test_register_menu_adds_capability_protected_plugin_page(): void {
		Functions\expect( 'add_menu_page' )
			->once()
			->with(
				'WP RAG AI Chatbot',
				'WP RAG AI',
				AdminCapability::MANAGE,
				'wp-rag-ai-chatbot',
				array( AdminBootstrap::class, 'render_page' ),
				'dashicons-format-chat'
			);

		AdminBootstrap::register_menu();
	}

	/**
	 * The PHP page is only a deterministic mount boundary for the later React app.
	 */
	public function test_render_page_outputs_only_the_admin_mount_root(): void {
		ob_start();
		AdminBootstrap::render_page();
		$output = (string) ob_get_clean();

		self::assertSame( '<div id="wp-rag-ai-chatbot-admin"></div>', $output );
	}

	/**
	 * Admin assets must be scoped to the plugin screen and never public/global admin pages.
	 */
	public function test_plugin_screen_detection_is_strict(): void {
		self::assertTrue( AdminBootstrap::is_plugin_screen( 'toplevel_page_wp-rag-ai-chatbot' ) );
		self::assertFalse( AdminBootstrap::is_plugin_screen( 'dashboard' ) );
		self::assertFalse( AdminBootstrap::is_plugin_screen( 'settings_page_wp-rag-ai-chatbot' ) );
	}

	/**
	 * Non-plugin admin screens must not load the React administration assets.
	 */
	#[DoesNotPerformAssertions]
	public function test_enqueue_assets_skips_non_plugin_admin_screens(): void {
		Functions\expect( 'wp_enqueue_script' )->never();
		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_add_inline_script' )->never();

		AdminBootstrap::enqueue_assets( 'dashboard' );
	}

	/**
	 * The plugin screen gets the bundle, responsive stylesheet, and minimal nonce-bearing boot configuration.
	 */
	#[DoesNotPerformAssertions]
	public function test_enqueue_assets_loads_bundle_with_safe_boot_configuration(): void {
		Functions\when( 'plugins_url' )->alias(
			static fn ( string $path ): string => 'https://example.test/wp-content/plugins/wp-rag-ai-chatbot/' . $path
		);
		Functions\when( 'rest_url' )->justReturn( 'https://example.test/wp-json/wp-rag-ai-chatbot/v1/' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'rest-nonce' );
		Functions\expect( 'wp_json_encode' )
			->once()
			->with(
				array(
					'plugin'   => 'wp-rag-ai-chatbot',
					'restBase' => 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
					'nonce'    => 'rest-nonce',
				)
			)
			->andReturn( '{"plugin":"wp-rag-ai-chatbot","restBase":"https:\/\/example.test\/wp-json\/wp-rag-ai-chatbot\/v1","nonce":"rest-nonce"}' );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'wp-rag-ai-chatbot-admin',
				'https://example.test/wp-content/plugins/wp-rag-ai-chatbot/assets/admin.css',
				array(),
				'0.1.0-dev'
			);
		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'wp-rag-ai-chatbot-admin',
				'https://example.test/wp-content/plugins/wp-rag-ai-chatbot/build/index.js',
				array( 'wp-element' ),
				'0.1.0-dev',
				true
			);
		Functions\expect( 'wp_add_inline_script' )
			->once()
			->with(
				'wp-rag-ai-chatbot-admin',
				'window.wpRagAiChatbotAdminConfig = {"plugin":"wp-rag-ai-chatbot","restBase":"https:\/\/example.test\/wp-json\/wp-rag-ai-chatbot\/v1","nonce":"rest-nonce"};',
				'before'
			);

		AdminBootstrap::enqueue_assets( 'toplevel_page_wp-rag-ai-chatbot' );
	}

	/**
	 * The foundational REST route must be versioned and capability-protected.
	 */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_safe_admin_bootstrap_resource(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/bootstrap',
				array(
					'methods'             => 'GET',
					'callback'            => array( AdminRestBootstrap::class, 'get_bootstrap' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				)
			);

		AdminRestBootstrap::register_routes();
	}

	/**
	 * Bot CRUD routes share the centralized capability and keep invalid IDs in the controller boundary.
	 */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_capability_protected_bot_crud_resources(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/bots',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( AdminRestBootstrap::class, 'list_bots' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'POST',
						'callback'            => array( AdminRestBootstrap::class, 'create_bot' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
				)
			);
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/bots/(?P<id>[^/]+)',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( AdminRestBootstrap::class, 'get_bot' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'PUT',
						'callback'            => array( AdminRestBootstrap::class, 'update_bot' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'DELETE',
						'callback'            => array( AdminRestBootstrap::class, 'delete_bot' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
				)
			);
		Functions\expect( 'register_rest_route' )->times( 12 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}

	/**
	 * Provider credentials are exposed only through one capability-protected write-only-secret route.
	 */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_capability_protected_provider_credential_resource(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/providers/(?P<provider_id>[^/]+)/credential',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( AdminRestBootstrap::class, 'get_provider_credential' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'PUT',
						'callback'            => array( AdminRestBootstrap::class, 'put_provider_credential' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'DELETE',
						'callback'            => array( AdminRestBootstrap::class, 'delete_provider_credential' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
				)
			);
		Functions\expect( 'register_rest_route' )->times( 13 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}

	/**
	 * The foundational response contains identifiers only and no credential material.
	 */
	public function test_admin_bootstrap_response_is_non_secret(): void {
		self::assertSame(
			array(
				'plugin'      => 'wp-rag-ai-chatbot',
				'api_version' => 'v1',
			),
			AdminRestBootstrap::get_bootstrap()
		);
	}
}
