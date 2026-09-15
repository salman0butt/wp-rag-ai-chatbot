<?php
/**
 * Conversation admin list-query tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Conversations;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Specifies the immutable bounded query authority for M16 conversation administration.
 */
final class ConversationListQueryTest extends TestCase {
	/** Pagination is normalized at construction so repositories never receive unbounded page controls. */
	public function test_normalizes_pagination_bounds(): void {
		$class = 'WpRagAiChatbot\\Conversations\\ConversationListQuery';

		self::assertTrue( class_exists( $class ), 'ConversationListQuery is missing.' );
		if ( ! class_exists( $class ) ) {
			return;
		}

		$reflection = new ReflectionClass( $class );
		$query      = $reflection->newInstance( 0, 500 );

		self::assertSame( 1, $reflection->getProperty( 'page' )->getValue( $query ) );
		self::assertSame( 100, $reflection->getProperty( 'page_size' )->getValue( $query ) );

		$small = $reflection->newInstance( 3, -4 );
		self::assertSame( 3, $reflection->getProperty( 'page' )->getValue( $small ) );
		self::assertSame( 1, $reflection->getProperty( 'page_size' )->getValue( $small ) );
	}

	/** Bot/date/search filters are normalized and search text is bounded before repository use. */
	public function test_normalizes_bounded_admin_filters(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Conversations\\ConversationListQuery' );
		$query      = $reflection->newInstance(
			2,
			25,
			' bot-1 ',
			false,
			'2026-09-01 00:00:00',
			'2026-09-14 23:59:59',
			'  ' . str_repeat( 'x', 250 ) . '  '
		);

		self::assertSame( 'bot-1', $reflection->getProperty( 'bot_id' )->getValue( $query ) );
		self::assertFalse( $reflection->getProperty( 'unassigned_only' )->getValue( $query ) );
		self::assertSame( '2026-09-01 00:00:00', $reflection->getProperty( 'date_from' )->getValue( $query ) );
		self::assertSame( '2026-09-14 23:59:59', $reflection->getProperty( 'date_to' )->getValue( $query ) );
		self::assertSame( str_repeat( 'x', 200 ), $reflection->getProperty( 'search' )->getValue( $query ) );
	}

	/** Historical conversations can be selected explicitly without fabricating a bot association. */
	public function test_supports_explicit_unassigned_filter(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Conversations\\ConversationListQuery' );
		$query      = $reflection->newInstance( 1, 25, null, true );

		self::assertNull( $reflection->getProperty( 'bot_id' )->getValue( $query ) );
		self::assertTrue( $reflection->getProperty( 'unassigned_only' )->getValue( $query ) );
	}

	/** A concrete bot and the unassigned bucket cannot be selected at the same time. */
	public function test_rejects_conflicting_bot_filters(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Conversations\\ConversationListQuery' );
		$this->expectException( InvalidArgumentException::class );

		$reflection->newInstance( 1, 25, 'bot-1', true );
	}

	/** Reversed date ranges fail closed instead of broadening the administrative read. */
	public function test_rejects_reversed_date_range(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Conversations\\ConversationListQuery' );
		$this->expectException( InvalidArgumentException::class );

		$reflection->newInstance( 1, 25, null, false, '2026-09-15 00:00:00', '2026-09-14 00:00:00' );
	}
}
