<?php
/**
 * M15 administrator display-rules route tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\AdminCapability;
use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;

/** Verifies bot display-rules REST transport stays administrator-only and bounded. */
final class DisplayRulesRoutesTest extends TestCase {
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

	/** Display-rule read/save share one protected bot-scoped route. */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_protected_display_rules_contracts(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/bots/(?P<id>[^/]+)/display-rules',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( AdminRestBootstrap::class, 'get_bot_display_rules' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'PUT',
						'callback'            => array( AdminRestBootstrap::class, 'put_bot_display_rules' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
				)
			);
		Functions\expect( 'register_rest_route' )->times( 16 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}
}
