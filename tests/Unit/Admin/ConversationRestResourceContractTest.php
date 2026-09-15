<?php
/**
 * M16 conversation administration REST resource contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use WpRagAiChatbot\Admin\Rest\ConversationRestResource;
use WpRagAiChatbot\Conversations\ConversationAdminRepository;
use WpRagAiChatbot\Conversations\ConversationDetail;
use WpRagAiChatbot\Conversations\ConversationDetailMessage;
use WpRagAiChatbot\Conversations\ConversationListQuery;
use WpRagAiChatbot\Conversations\ConversationReadRepository;
use WpRagAiChatbot\Conversations\ConversationSummary;

/** Specifies the dedicated conversation administration REST projection boundary. */
final class ConversationRestResourceContractTest extends TestCase {
	/** List projections expose only bounded public-safe conversation summary fields. */
	public function test_list_projects_repository_summaries(): void {
		$read = $this->createMock( ConversationReadRepository::class );
		$read->expects( self::once() )
			->method( 'list' )
			->with( self::isInstanceOf( ConversationListQuery::class ) )
			->willReturn(
				array(
					new ConversationSummary(
						'conversation-1',
						'bot-1',
						'2026-09-14 05:00:00',
						'2026-09-14 05:05:00',
						4
					),
				)
			);
		$admin    = $this->createMock( ConversationAdminRepository::class );
		$resource = $this->resource( $read, $admin );
		$query    = new ConversationListQuery( 2, 25, 'bot-1' );

		$response = $this->invoke( $resource, 'list', $query );

		self::assertIsArray( $response );
		self::assertSame( 2, $response['page'] );
		self::assertSame( 25, $response['per_page'] );
		self::assertSame( 'conversation-1', $response['items'][0]['conversation_id'] );
		self::assertSame( 'bot-1', $response['items'][0]['bot_id'] );
		self::assertSame( '2026-09-14 05:00:00', $response['items'][0]['started_at'] );
		self::assertSame( '2026-09-14 05:05:00', $response['items'][0]['latest_message_at'] );
		self::assertSame( 4, $response['items'][0]['message_count'] );
		self::assertArrayNotHasKey( 'owner_scope', $response['items'][0] );
	}

	/** Detail projections expose the bounded canonical transcript and normalize missing state. */
	public function test_read_projects_detail_and_missing_state(): void {
		$read = $this->createMock( ConversationReadRepository::class );
		$read->expects( self::exactly( 2 ) )
			->method( 'find' )
			->with( 'conversation-1', 100 )
			->willReturnOnConsecutiveCalls(
				new ConversationDetail(
					'conversation-1',
					null,
					'2026-09-14 05:00:00',
					array(
						new ConversationDetailMessage( 'user', 'Hello', '2026-09-14 05:00:01' ),
						new ConversationDetailMessage( 'assistant', 'Hi', '2026-09-14 05:00:02' ),
					)
				),
				null
			);
		$resource = $this->resource( $read, $this->createMock( ConversationAdminRepository::class ) );

		$found   = $this->invoke( $resource, 'read', 'conversation-1', 1000 );
		$missing = $this->invoke( $resource, 'read', 'conversation-1', 1000 );

		self::assertIsArray( $found );
		self::assertSame( 'conversation-1', $found['conversation']['conversation_id'] );
		self::assertNull( $found['conversation']['bot_id'] );
		self::assertSame( 'user', $found['conversation']['messages'][0]['role'] );
		self::assertSame( 'Hello', $found['conversation']['messages'][0]['content'] );
		self::assertArrayNotHasKey( 'owner_scope', $found['conversation'] );
		self::assertIsArray( $missing );
		self::assertSame( 'conversation_not_found', $missing['error']['code'] );
	}

	/** Delete delegates only to the explicit administrator mutation boundary. */
	public function test_delete_maps_success_and_missing_state(): void {
		$admin = $this->createMock( ConversationAdminRepository::class );
		$admin->expects( self::exactly( 2 ) )
			->method( 'delete' )
			->with( 'conversation-1' )
			->willReturnOnConsecutiveCalls( true, false );
		$resource = $this->resource( $this->createMock( ConversationReadRepository::class ), $admin );

		$deleted = $this->invoke( $resource, 'delete', 'conversation-1' );
		$missing = $this->invoke( $resource, 'delete', 'conversation-1' );

		self::assertSame( array( 'deleted' => true ), $deleted );
		self::assertIsArray( $missing );
		self::assertSame( 'conversation_not_found', $missing['error']['code'] );
	}

	/** Invalid delete identifiers fail closed without exposing repository exception details. */
	public function test_delete_maps_invalid_identifier_to_bounded_error(): void {
		$admin = $this->createMock( ConversationAdminRepository::class );
		$admin->expects( self::once() )
			->method( 'delete' )
			->with( '' )
			->willThrowException( new \InvalidArgumentException( 'Sensitive repository validation detail.' ) );
		$resource = $this->resource( $this->createMock( ConversationReadRepository::class ), $admin );

		$response = $this->invoke( $resource, 'delete', '' );

		self::assertIsArray( $response );
		self::assertSame( 'invalid_conversation_id', $response['error']['code'] );
		self::assertSame( 'Conversation identifier is invalid.', $response['error']['message'] );
		self::assertStringNotContainsString( 'Sensitive', $response['error']['message'] );
	}

	/**
	 * Instantiate the evolving production resource without making the test-only checkpoint fail static analysis.
	 *
	 * @param ConversationReadRepository  $read Read authority.
	 * @param ConversationAdminRepository $admin Mutation authority.
	 */
	private function resource( ConversationReadRepository $read, ConversationAdminRepository $admin ): object {
		$reflection = new ReflectionClass( ConversationRestResource::class );

		return $reflection->newInstance( $read, $admin );
	}

	/**
	 * Invoke one evolving resource method through reflection so behavioral RED reaches PHPUnit.
	 *
	 * @param object $subject Evolving REST resource instance.
	 * @param string $method Resource method name.
	 * @param mixed  ...$arguments Resource method arguments.
	 */
	private function invoke( object $subject, string $method, mixed ...$arguments ): mixed {
		return ( new ReflectionMethod( $subject, $method ) )->invoke( $subject, ...$arguments );
	}
}
