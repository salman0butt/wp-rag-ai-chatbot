<?php
/**
 * Model readiness route tests.
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
 * Verifies Task 5 routes share the centralized admin capability.
 */
final class ModelReadinessRoutesTest extends TestCase {
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
	 * Model and onboarding resources must be versioned and capability-protected.
	 */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_protected_model_and_readiness_resources(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/models',
				array(
					'methods'             => 'GET',
					'callback'            => array( AdminRestBootstrap::class, 'list_models' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				)
			);
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/onboarding/readiness',
				array(
					'methods'             => 'GET',
					'callback'            => array( AdminRestBootstrap::class, 'get_onboarding_readiness' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				)
			);
		Functions\expect( 'register_rest_route' )->times( 4 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}
}
