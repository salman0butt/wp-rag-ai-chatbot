<?php
/**
 * Lifecycle tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\Lifecycle;

/**
 * Verifies lifecycle event boundaries.
 */
final class LifecycleTest extends TestCase {
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
	 * Activation emits the stable database-migration action.
	 */
	public function test_activate_emits_activation_action(): void {
		self::assertTrue( is_callable( array( Lifecycle::class, 'activate' ) ) );
		Functions\expect( 'do_action' )->once()->with( 'wp_rag_ai_chatbot_activate' );

		Lifecycle::activate();
	}
}
