<?php
/**
 * Paged result tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\PagedResult;

/**
 * Verifies pagination value-object invariants.
 */
final class PagedResultTest extends TestCase {
	/**
	 * Page numbers are one-based.
	 */
	public function test_rejects_page_below_one(): void {
		$this->expectException( InvalidArgumentException::class );

		new PagedResult( array(), 0, 0, 20 );
	}

	/**
	 * Page size must be positive.
	 */
	public function test_rejects_per_page_below_one(): void {
		$this->expectException( InvalidArgumentException::class );

		new PagedResult( array(), 0, 1, 0 );
	}

	/**
	 * Pagination data remains immutable and directly readable.
	 */
	public function test_exposes_pagination_data(): void {
		$result = new PagedResult( array( 'a', 'b' ), 25, 2, 10 );

		self::assertSame( array( 'a', 'b' ), $result->items );
		self::assertSame( 25, $result->total );
		self::assertSame( 2, $result->page );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PagedResult public API is camelCase by approved contract.
		self::assertSame( 10, $result->perPage );
	}
}
