<?php
/**
 * Playground chat access-context composition.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use LogicException;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Lexical\ChunkLookupStore;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Resolves trusted request-local chat scope from persisted Playground configuration.
 */
final readonly class PlaygroundAccessContextResolver {
	/**
	 * Create the access-context resolver.
	 *
	 * @param ChunkLookupStore $chunks Canonical persisted chunk lookup.
	 */
	public function __construct( private ChunkLookupStore $chunks ) {
	}

	/**
	 * Resolve the exact persisted source and collection scope used by production retrieval.
	 *
	 * @param PlaygroundConfiguration $configuration Closed persisted Playground configuration.
	 * @throws LogicException When the selected source is not persisted.
	 */
	public function resolve( PlaygroundConfiguration $configuration ): ChatAccessContext {
		$source_id = $configuration->retrieval->source->id;
		if ( null === $source_id || $source_id < 1 ) {
			throw new LogicException( 'Playground source must be persisted before chat execution.' );
		}

		$collection_id = $configuration->retrieval->collection_id;

		return new ChatAccessContext(
			'playground:' . $configuration->bot->id->value,
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
