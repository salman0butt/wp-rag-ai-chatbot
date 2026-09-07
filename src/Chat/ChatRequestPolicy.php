<?php
/**
 * Deterministic pre-generation chat request policy.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

use InvalidArgumentException;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Retrieval\QueryPreprocessor;
use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Applies cheap deterministic checks before memory, retrieval, and paid generation.
 */
final class ChatRequestPolicy {
	/**
	 * Create one request policy.
	 *
	 * @param QueryPreprocessor $query_preprocessor Existing M10 bounded query preprocessor.
	 */
	public function __construct( private readonly QueryPreprocessor $query_preprocessor ) {
	}

	/**
	 * Validate provider availability and build one bounded retrieval query.
	 *
	 * @param ChatRequest        $request Normalized chat request.
	 * @param GenerationProvider $provider Provider-neutral generation boundary.
	 * @throws ChatException When the provider is unavailable or query is invalid for retrieval.
	 */
	public function prepare( ChatRequest $request, GenerationProvider $provider ): RetrievalQuery {
		if ( ! $provider->available() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Application exception reason enum is not rendered output.
			throw new ChatException( ChatFailureReason::GENERATION_UNAVAILABLE, 'Generation is unavailable.' );
		}

		try {
			return $this->query_preprocessor->preprocess( $request->question );
		} catch ( InvalidArgumentException ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Application exception reason enum is not rendered output.
			throw new ChatException( ChatFailureReason::INVALID_REQUEST, 'Chat request is invalid.' );
		}
	}
}
