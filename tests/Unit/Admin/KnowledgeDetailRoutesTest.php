<?php
/**
 * M13 knowledge detail route tests.
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

/** Verifies protected source/document/chunk inspection routes. */
final class KnowledgeDetailRoutesTest extends TestCase {
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

	/** Detail routes are read-only and capability protected. */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_protected_knowledge_detail_routes(): void {
		$routes = array(
			'/admin/knowledge/sources/(?P<id>\\d+)' => 'get_knowledge_source',
			'/admin/knowledge/sources/(?P<id>\\d+)/documents' => 'list_knowledge_documents',
			'/admin/knowledge/sources/(?P<id>\\d+)/documents/(?P<document_key>[^/]+)/chunks' => 'list_knowledge_chunks',
		);

		foreach ( $routes as $route => $callback ) {
			Functions\expect( 'register_rest_route' )
				->once()
				->with(
					'wp-rag-ai-chatbot/v1',
					$route,
					array(
						'methods'             => 'GET',
						'callback'            => array( AdminRestBootstrap::class, $callback ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					)
				);
		}
		Functions\expect( 'register_rest_route' )->times( 7 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}
}
