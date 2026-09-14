<?php
/**
 * M16 conversation list request tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Conversations\ConversationListQuery;

/** Specifies bounded fail-closed parsing for administrator conversation list requests. */
final class ConversationListRequestTest extends TestCase {
	/** Valid scalar request values map into the existing normalized query authority. */
	public function test_maps_valid_request_values_to_conversation_query(): void {
		$class = 'WpRagAiChatbot\\Admin\\Rest\\ConversationListRequest';

		self::assertTrue( class_exists( $class ), 'ConversationListRequest is missing.' );
		if ( ! class_exists( $class ) ) {
			return;
		}

		$reflection = new ReflectionClass( $class );
		$query      = $reflection->getMethod( 'from_array' )->invoke(
			null,
			array(
				'page'            => '2',
				'per_page'        => '50',
				'bot_id'          => ' bot-1 ',
				'unassigned_only' => '0',
				'date_from'       => '2026-09-01 00:00:00',
				'date_to'         => '2026-09-14 23:59:59',
				'search'          => '  ' . str_repeat( 'x', 250 ) . '  ',
			)
		);

		self::assertInstanceOf( ConversationListQuery::class, $query );
		self::assertSame( 2, $query->page );
		self::assertSame( 50, $query->page_size );
		self::assertSame( 'bot-1', $query->bot_id );
		self::assertFalse( $query->unassigned_only );
		self::assertSame( '2026-09-01 00:00:00', $query->date_from );
		self::assertSame( '2026-09-14 23:59:59', $query->date_to );
		self::assertSame( str_repeat( 'x', 200 ), $query->search );
	}

	/** Query booleans accept only explicit REST-safe representations. */
	public function test_rejects_ambiguous_unassigned_filter(): void {
		$query = $this->from_array( array( 'unassigned_only' => 'sometimes' ) );

		self::assertNull( $query );
	}

	/** Pagination and filter scalar types fail closed instead of being loosely coerced. */
	public function test_rejects_invalid_scalar_inputs(): void {
		self::assertNull( $this->from_array( array( 'page' => '2.5' ) ) );
		self::assertNull( $this->from_array( array( 'per_page' => '101' ) ) );
		self::assertNull( $this->from_array( array( 'bot_id' => array( 'bot-1' ) ) ) );
		self::assertNull( $this->from_array( array( 'search' => array( 'hello' ) ) ) );
	}

	/** Date boundaries must be valid database timestamps and chronological. */
	public function test_rejects_invalid_or_reversed_dates(): void {
		self::assertNull( $this->from_array( array( 'date_from' => '2026-02-31 00:00:00' ) ) );
		self::assertNull(
			$this->from_array(
				array(
					'date_from' => '2026-09-15 00:00:00',
					'date_to'   => '2026-09-14 00:00:00',
				)
			)
		);
	}

	/** Conflicting concrete-bot and unassigned filters fail closed at the request boundary. */
	public function test_rejects_conflicting_bot_filters(): void {
		self::assertNull(
			$this->from_array(
				array(
					'bot_id'          => 'bot-1',
					'unassigned_only' => '1',
				)
			)
		);
	}

	/**
	 * Invoke the request parser without making the test suite fatally depend on the RED class.
	 *
	 * @param array<string,mixed> $input Raw request parameters.
	 */
	private function from_array( array $input ): mixed {
		$class = 'WpRagAiChatbot\\Admin\\Rest\\ConversationListRequest';
		if ( ! class_exists( $class ) ) {
			return null;
		}

		$reflection = new ReflectionClass( $class );

		return $reflection->getMethod( 'from_array' )->invoke( null, $input );
	}
}
