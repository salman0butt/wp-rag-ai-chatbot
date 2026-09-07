<?php
/**
 * M11 chat analytics contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;

/**
 * Proves the provider-neutral sanitized chat analytics boundary exists.
 */
final class ChatAnalyticsContractTest extends TestCase {
	/**
	 * Analytics must use dedicated application-owned contracts.
	 */
	public function test_chat_analytics_contracts_exist(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\ChatAnalyticsEvent' ) );
		self::assertTrue( interface_exists( 'WpRagAiChatbot\\Chat\\ChatAnalyticsHook' ) );
	}
}
