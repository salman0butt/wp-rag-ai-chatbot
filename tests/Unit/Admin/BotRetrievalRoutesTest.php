<?php
/**
 * Bot retrieval administration route tests.
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

/** Verifies the bot retrieval binding route is administrator-only. */
final class BotRetrievalRoutesTest extends TestCase {
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

	/** Retrieval read/save/clear share one protected bot-scoped route. */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_protected_retrieval_contract(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/bots/(?P<id>[^/]+)/retrieval',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( AdminRestBootstrap::class, 'get_bot_retrieval' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'PUT',
						'callback'            => array( AdminRestBootstrap::class, 'put_bot_retrieval' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'DELETE',
						'callback'            => array( AdminRestBootstrap::class, 'delete_bot_retrieval' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
				)
			);
		Functions\expect( 'register_rest_route' )->times( 15 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}
}
