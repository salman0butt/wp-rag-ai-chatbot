<?php
/**
 * M13 Playground route tests.
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

/**
 * Verifies the Playground route uses the centralized administrator capability.
 */
final class PlaygroundRoutesTest extends TestCase {
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

	/** Playground execution must be versioned, POST-only and capability protected. */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_protected_playground_execution(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/debug/playground',
				array(
					'methods'             => 'POST',
					'callback'            => array( AdminRestBootstrap::class, 'run_playground' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				)
			);
		Functions\expect( 'register_rest_route' )->times( 12 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}
}
