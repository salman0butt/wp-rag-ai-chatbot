<?php
/**
 * M13 knowledge job route tests.
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

/** Verifies job inspection and lifecycle routes use the centralized admin capability. */
final class KnowledgeJobRoutesTest extends TestCase {
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

	/** Job collection supports protected bounded reads and enqueue. */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_protected_job_collection(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/knowledge/jobs',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( AdminRestBootstrap::class, 'list_knowledge_jobs' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'POST',
						'callback'            => array( AdminRestBootstrap::class, 'enqueue_knowledge_job' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
				)
			);
		Functions\expect( 'register_rest_route' )->times( 13 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}

	/** Job mutations are one protected route constrained to the supported actions. */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_protected_job_action_route(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/knowledge/jobs/(?P<job_key>[^/]+)/(?P<action>cancel|retry)',
				array(
					'methods'             => 'POST',
					'callback'            => array( AdminRestBootstrap::class, 'mutate_knowledge_job' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				)
			);
		Functions\expect( 'register_rest_route' )->times( 13 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}
}
