<?php
/**
 * Admin bootstrap tests.
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
 * Verifies the WordPress admin control-plane hook boundary.
 */
final class AdminBootstrapTest extends TestCase {
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
	 * Admin registration must wire only the admin control-plane hooks.
	 */
	public function test_register_wires_menu_assets_and_rest_hooks(): void {
		self::assertTrue( class_exists( AdminRestBootstrap::class ), 'AdminRestBootstrap must exist before REST hook wiring can pass.' );

		Functions\expect( 'add_action' )->once()->with( 'admin_menu', array( AdminBootstrap::class, 'register_menu' ) );
		Functions\expect( 'add_action' )->once()->with( 'admin_enqueue_scripts', array( AdminBootstrap::class, 'enqueue_assets' ) );
		Functions\expect( 'add_action' )->once()->with( 'rest_api_init', array( AdminRestBootstrap::class, 'register_routes' ) );

		AdminBootstrap::register();
	}

	/**
	 * The administration capability is centralized and delegated to WordPress.
	 */
	public function test_capability_policy_requires_manage_options(): void {
		self::assertTrue( class_exists( AdminCapability::class ), 'AdminCapability must exist before the admin permission boundary can pass.' );

		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );

		self::assertTrue( AdminCapability::can_manage() );
	}
}
