<?php
/**
 * Conversation admin summary DTO tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Conversations;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Specifies the immutable M16 conversation-list projection.
 */
final class ConversationSummaryTest extends TestCase {
	/** Summary rows expose only bounded administrative list facts and preserve unassigned history. */
	public function test_exposes_immutable_list_projection(): void {
		$class = 'WpRagAiChatbot\\Conversations\\ConversationSummary';

		self::assertTrue( class_exists( $class ), 'ConversationSummary is missing.' );
		if ( ! class_exists( $class ) ) {
			return;
		}

		$reflection = new ReflectionClass( $class );
		self::assertTrue( $reflection->isReadOnly() );

		$summary = $reflection->newInstance(
			'conversation-1',
			'bot-1',
			'2026-09-14 01:00:00',
			'2026-09-14 01:05:00',
			4
		);

		self::assertSame( 'conversation-1', $reflection->getProperty( 'conversation_id' )->getValue( $summary ) );
		self::assertSame( 'bot-1', $reflection->getProperty( 'bot_id' )->getValue( $summary ) );
		self::assertSame( '2026-09-14 01:00:00', $reflection->getProperty( 'started_at' )->getValue( $summary ) );
		self::assertSame( '2026-09-14 01:05:00', $reflection->getProperty( 'latest_message_at' )->getValue( $summary ) );
		self::assertSame( 4, $reflection->getProperty( 'message_count' )->getValue( $summary ) );

		$historical = $reflection->newInstance(
			'conversation-legacy',
			null,
			'2026-09-13 20:00:00',
			null,
			0
		);

		self::assertNull( $reflection->getProperty( 'bot_id' )->getValue( $historical ) );
		self::assertNull( $reflection->getProperty( 'latest_message_at' )->getValue( $historical ) );
		self::assertSame( 0, $reflection->getProperty( 'message_count' )->getValue( $historical ) );
	}
}
