<?php
/**
 * Conversation administration detail projection tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Conversations;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Conversations\ConversationDetail;
use WpRagAiChatbot\Conversations\ConversationDetailMessage;

/** Specifies immutable public-safe administration detail projections. */
final class ConversationDetailTest extends TestCase {
	/** Detail projections expose canonical transcript facts without owner scope. */
	public function test_exposes_bounded_public_safe_detail_projection(): void {
		self::assertTrue( class_exists( ConversationDetail::class ), 'ConversationDetail is missing.' );
		self::assertTrue( class_exists( ConversationDetailMessage::class ), 'ConversationDetailMessage is missing.' );
		if ( ! class_exists( ConversationDetail::class ) || ! class_exists( ConversationDetailMessage::class ) ) {
			return;
		}

		$message = new ConversationDetailMessage(
			'user',
			'Hello there',
			'2026-09-14 03:00:01'
		);
		$detail  = new ConversationDetail(
			'conversation-1',
			'bot-1',
			'2026-09-14 03:00:00',
			array( $message )
		);

		self::assertSame( 'conversation-1', $detail->conversation_id );
		self::assertSame( 'bot-1', $detail->bot_id );
		self::assertSame( '2026-09-14 03:00:00', $detail->started_at );
		self::assertSame( array( $message ), $detail->messages );
		self::assertSame( 'user', $message->role );
		self::assertSame( 'Hello there', $message->content );
		self::assertSame( '2026-09-14 03:00:01', $message->created_at );
		self::assertFalse( property_exists( $detail, 'owner_scope' ) );
	}
}
