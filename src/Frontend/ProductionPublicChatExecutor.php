<?php
/**
 * Production public-chat execution adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use InvalidArgumentException;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ChatRequest;
use WpRagAiChatbot\Chat\ChatResponder;
use WpRagAiChatbot\RAG\GroundingMode;

/**
 * Maps bounded public interaction data into one existing production M11 execution.
 */
final readonly class ProductionPublicChatExecutor {
	/**
	 * Create one request-local public executor.
	 *
	 * @param ChatResponder     $responder Existing production M11 responder boundary.
	 * @param ChatAccessContext $access Trusted persisted owner/retrieval scope.
	 * @param string            $model_id Persisted server-owned generation model identifier.
	 * @throws InvalidArgumentException When the persisted model identifier is blank.
	 */
	public function __construct(
		private ChatResponder $responder,
		private ChatAccessContext $access,
		private string $model_id
	) {
		if ( '' === trim( $model_id ) ) {
			throw new InvalidArgumentException( 'Public chat model identifier must not be blank.' );
		}
	}

	/**
	 * Execute the existing M11 chat pipeline exactly once and project public-safe output.
	 *
	 * @param PublicChatRequest $request Validated public interaction request.
	 */
	public function execute( PublicChatRequest $request ): PublicChatResponse {
		$result = $this->responder->respond(
			new ChatRequest(
				$request->question,
				$this->model_id,
				GroundingMode::STRICT,
				$request->conversation_id
			),
			$this->access
		);

		return PublicChatResponse::from_chat_result( $result );
	}
}
