<?php
/**
 * Playground vector collection resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\VectorStore\VectorCollection;

/**
 * Reconstructs the canonical vector collection selected for Playground retrieval.
 */
final class PlaygroundVectorCollectionResolver {
	/**
	 * Resolve the canonical vector collection without performing I/O.
	 *
	 * @param PlaygroundRetrievalConfiguration $retrieval Persisted retrieval selection.
	 * @param PlaygroundSemanticConfiguration  $semantic Persisted semantic identity.
	 */
	public function resolve(
		PlaygroundRetrievalConfiguration $retrieval,
		PlaygroundSemanticConfiguration $semantic
	): VectorCollection {
		return new VectorCollection(
			$retrieval->collection_id,
			new VectorIndexProfile( $semantic->embedding_profile, $semantic->distance )
		);
	}
}
