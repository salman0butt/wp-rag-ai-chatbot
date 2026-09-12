<?php
/**
 * M14 administrator appearance route tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\AdminCapability;
use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;

/**
 * Verifies bot appearance REST transport stays administrator-only and bounded.
 */
final class AppearanceRoutesTest extends TestCase {
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
	 * Appearance read/save share one protected bot-scoped route.
	 */
	public function test_register_routes_adds_protected_appearance_contracts(): void {
		$routes = array();
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $namespace, string $route, array $args ) use ( &$routes ): bool {
				$routes[ $namespace . $route ] = $args;
				return true;
			}
		);

		AdminRestBootstrap::register_routes();

		$key = 'wp-rag-ai-chatbot/v1/admin/bots/(?P<id>[^/]+)/appearance';
		self::assertArrayHasKey( $key, $routes );
		self::assertCount( 2, $routes[ $key ] );
		self::assertSame( 'GET', $routes[ $key ][0]['methods'] );
		self::assertSame( array( AdminRestBootstrap::class, 'get_bot_appearance' ), $routes[ $key ][0]['callback'] );
		self::assertSame( array( AdminCapability::class, 'can_manage' ), $routes[ $key ][0]['permission_callback'] );
		self::assertSame( 'PUT', $routes[ $key ][1]['methods'] );
		self::assertSame( array( AdminRestBootstrap::class, 'put_bot_appearance' ), $routes[ $key ][1]['callback'] );
		self::assertSame( array( AdminCapability::class, 'can_manage' ), $routes[ $key ][1]['permission_callback'] );
	}
}
