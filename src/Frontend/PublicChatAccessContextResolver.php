<?php
/**
 * Public chat access-context composition.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Lexical\ChunkLookupStore;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Resolves trusted M11 access scope exclusively from persisted public runtime authority.
 */
final readonly class PublicChatAccessContextResolver {
	/**
	 * Create the resolver.
	 *
	 * @param ChunkLookupStore $chunks Canonical persisted chunk lookup.
	 */
	public function __construct( private ChunkLookupStore $chunks ) {
	}

	/**
	 * Resolve the exact persisted source and collection scope for public chat.
	 *
	 * @param PublicChatRuntime $runtime Trusted persisted public runtime authority.
	 */
	public function resolve( PublicChatRuntime $runtime ): ChatAccessContext {
		$source_id     = $runtime->retrieval->source_id;
		$collection_id = $runtime->retrieval->collection_id;

		return new ChatAccessContext(
			'public:' . $runtime->bot->id->value,
			new SemanticRetrievalContext(
				new RetrievalFilter( null, null, array( $source_id ) ),
				function ( string $chunk_id ) use ( $collection_id, $source_id ): ?ChunkSearchRecord {
					$chunk = $this->chunks->find_chunk( $collection_id, $chunk_id );

					return null !== $chunk && $source_id === $chunk->source_id ? $chunk : null;
				}
			),
			new LexicalFilter( $collection_id, null, $source_id ),
			false
		);
	}
}
