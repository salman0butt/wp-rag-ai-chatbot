<?php
/**
 * M13 knowledge source route tests.
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
 * Verifies the knowledge inventory route uses the centralized admin capability.
 */
final class KnowledgeSourceRoutesTest extends TestCase {
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

	/** Knowledge inventory must be versioned, read-only and capability protected. */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_protected_knowledge_inventory(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/knowledge/sources',
				array(
					'methods'             => 'GET',
					'callback'            => array( AdminRestBootstrap::class, 'list_knowledge_sources' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				)
			);
		Functions\expect( 'register_rest_route' )->times( 6 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}
}
