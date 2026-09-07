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
	 * The foundational REST route must be versioned and capability-protected.
	 */
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
