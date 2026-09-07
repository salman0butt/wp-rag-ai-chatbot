<?php
/**
 * Trusted chat access context.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

use InvalidArgumentException;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Carries trusted owner and retrieval scope without exposing it to provider input.
 */
final readonly class ChatAccessContext {
	private const MAX_OWNER_SCOPE_BYTES = 191;

	/**
	 * Create trusted access context.
	 *
	 * @param string                   $owner_scope Trusted owner scope.
	 * @param SemanticRetrievalContext $semantic_context Trusted semantic retrieval context.
	 * @param LexicalFilter            $lexical_filter Trusted lexical retrieval filter.
	 * @param bool                     $allow_single_channel_degradation Whether M10 may degrade to one healthy channel.
	 * @throws InvalidArgumentException When owner scope is invalid or unbounded.
	 */
	public function __construct(
		public string $owner_scope,
		public SemanticRetrievalContext $semantic_context,
		public LexicalFilter $lexical_filter,
		public bool $allow_single_channel_degradation = false
	) {
		if ( '' === trim( $owner_scope ) || strlen( $owner_scope ) > self::MAX_OWNER_SCOPE_BYTES ) {
			throw new InvalidArgumentException( 'Chat owner scope is invalid or exceeds the byte limit.' );
		}
	}
}
