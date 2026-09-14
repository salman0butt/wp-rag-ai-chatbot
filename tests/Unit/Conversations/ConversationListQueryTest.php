<?php
/**
 * Conversation admin list-query tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Conversations;

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
}
