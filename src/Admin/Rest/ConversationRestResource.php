<?php
/**
 * Administrator conversation REST projection boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Conversations\ConversationAdminRepository;
use WpRagAiChatbot\Conversations\ConversationDetail;
use WpRagAiChatbot\Conversations\ConversationDetailMessage;
use WpRagAiChatbot\Conversations\ConversationListQuery;
use WpRagAiChatbot\Conversations\ConversationReadRepository;
use WpRagAiChatbot\Conversations\ConversationSummary;

/** Projects canonical conversation administration authorities into REST-safe responses. */
final class ConversationRestResource {
	private const MAX_DETAIL_MESSAGES = 100;

	/**
	 * Create the REST projection boundary.
	 *
	 * @param ConversationReadRepository  $read Read authority.
	 * @param ConversationAdminRepository $admin Explicit administrator mutation authority.
	 */
	public function __construct(
		private readonly ConversationReadRepository $read,
		private readonly ConversationAdminRepository $admin
	) {
	}

	/**
	 * Return one bounded conversation page.
	 *
	 * @param ConversationListQuery $query Normalized administrator query.
	 * @return array{items:list<array{conversation_id:string,bot_id:?string,started_at:string,latest_message_at:?string,message_count:int}>,page:int,per_page:int}
	 */
	public function list( ConversationListQuery $query ): array {
		return array(
			'items'    => array_map( self::summary_to_array( ... ), $this->read->list( $query ) ),
			'page'     => $query->page,
			'per_page' => $query->page_size,
		);
	}

	/**
	 * Return one bounded conversation detail projection.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param int    $message_limit Requested transcript limit.
	 * @return array{conversation:array{conversation_id:string,bot_id:?string,started_at:string,messages:list<array{role:string,content:string,created_at:string}>}}|array{error:array{code:string,message:string}}
	 */
	public function read( string $conversation_id, int $message_limit = self::MAX_DETAIL_MESSAGES ): array {
		$message_limit = min( max( 0, $message_limit ), self::MAX_DETAIL_MESSAGES );
		$detail        = $this->read->find( $conversation_id, $message_limit );
		if ( null === $detail ) {
			return self::not_found();
		}

		return array( 'conversation' => self::detail_to_array( $detail ) );
	}

	/**
	 * Delete one canonical conversation through the explicit administrator boundary.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @return array{deleted:true}|array{error:array{code:string,message:string}}
	 */
	public function delete( string $conversation_id ): array {
		if ( ! $this->admin->delete( $conversation_id ) ) {
			return self::not_found();
		}

		return array( 'deleted' => true );
	}

	/**
	 * Project one summary using only administrator-safe fields.
	 *
	 * @param ConversationSummary $summary Summary DTO.
	 * @return array{conversation_id:string,bot_id:?string,started_at:string,latest_message_at:?string,message_count:int}
	 */
	private static function summary_to_array( ConversationSummary $summary ): array {
		return array(
			'conversation_id'   => $summary->conversation_id,
			'bot_id'            => $summary->bot_id,
			'started_at'        => $summary->started_at,
			'latest_message_at' => $summary->latest_message_at,
			'message_count'     => $summary->message_count,
		);
	}

	/**
	 * Project one conversation detail using only administrator-safe fields.
	 *
	 * @param ConversationDetail $detail Detail DTO.
	 * @return array{conversation_id:string,bot_id:?string,started_at:string,messages:list<array{role:string,content:string,created_at:string}>}
	 */
	private static function detail_to_array( ConversationDetail $detail ): array {
		return array(
			'conversation_id' => $detail->conversation_id,
			'bot_id'          => $detail->bot_id,
			'started_at'      => $detail->started_at,
			'messages'        => array_map( self::message_to_array( ... ), $detail->messages ),
		);
	}

	/**
	 * Project one canonical message using only administrator-safe fields.
	 *
	 * @param ConversationDetailMessage $message Message DTO.
	 * @return array{role:string,content:string,created_at:string}
	 */
	private static function message_to_array( ConversationDetailMessage $message ): array {
		return array(
			'role'       => $message->role,
			'content'    => $message->content,
			'created_at' => $message->created_at,
		);
	}

	/**
	 * Return the stable missing-conversation public error.
	 *
	 * @return array{error:array{code:string,message:string}}
	 */
	private static function not_found(): array {
		return array(
			'error' => array(
				'code'    => 'conversation_not_found',
				'message' => 'Conversation was not found.',
			),
		);
	}
}
