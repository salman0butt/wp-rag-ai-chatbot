<?php
/**
 * M16 administrator conversation route tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WpRagAiChatbot\Admin\AdminCapability;
use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;

/** Verifies conversation administration routes stay capability protected. */
final class ConversationRoutesTest extends TestCase {
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

	/** Conversation list/detail/delete use only the established administrator capability gate. */
	#[DoesNotPerformAssertions]
	public function test_register_routes_adds_protected_conversation_contracts(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/conversations',
				array(
					'methods'             => 'GET',
					'callback'            => array( AdminRestBootstrap::class, 'list_conversations' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				)
			);

		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-rag-ai-chatbot/v1',
				'/admin/conversations/(?P<id>[^/]+)',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( AdminRestBootstrap::class, 'get_conversation' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
					array(
						'methods'             => 'DELETE',
						'callback'            => array( AdminRestBootstrap::class, 'delete_conversation' ),
						'permission_callback' => array( AdminCapability::class, 'can_manage' ),
					),
				)
			);

		Functions\expect( 'register_rest_route' )->times( 15 )->withAnyArgs();

		AdminRestBootstrap::register_routes();
	}

	/** The collection callback must consume the bounded request parser rather than rebuilding query rules. */
	public function test_list_callback_uses_bounded_conversation_request_parser(): void {
		$method   = new ReflectionMethod( AdminRestBootstrap::class, 'list_conversations' );
		$filename = $method->getFileName();

		self::assertIsString( $filename );
		$lines = file( $filename );
		self::assertIsArray( $lines );

		$source = implode(
			'',
			array_slice(
				$lines,
				$method->getStartLine() - 1,
				$method->getEndLine() - $method->getStartLine() + 1
			)
		);

		self::assertStringContainsString(
			'ConversationListRequest::from_array( $request->get_query_params() )',
			$source
		);
	}
}
