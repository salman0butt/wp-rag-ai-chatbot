<?php
/**
 * Stateless Playground conversation-history sentinel tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use LogicException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\StatelessPlaygroundConversationHistory;
use WpRagAiChatbot\Memory\ConversationHistory;

/**
 * Keeps Task 6 stateless without inventing unfinished M11 persistence behavior.
 */
final class StatelessPlaygroundConversationHistoryTest extends TestCase {
	/** The stateless sentinel must satisfy the existing production memory contract. */
	public function test_implements_conversation_history_contract(): void {
		self::assertTrue( class_exists( StatelessPlaygroundConversationHistory::class ) );

		$history = new StatelessPlaygroundConversationHistory();
		self::assertInstanceOf( ConversationHistory::class, $history );
	}

	/** Accidental stateful Playground reads must fail closed rather than return fabricated history. */
	public function test_recent_history_access_fails_closed(): void {
		$history = new StatelessPlaygroundConversationHistory();

		$this->expectException( LogicException::class );
		$history->recent_for_owner( 'conversation-1', 'owner-1', 12 );
	}

	/** Accidental summary reads must fail closed rather than pretend persistence exists. */
	public function test_summary_access_fails_closed(): void {
		$history = new StatelessPlaygroundConversationHistory();

		$this->expectException( LogicException::class );
		$history->summary_for_owner( 'conversation-1', 'owner-1' );
	}
}
