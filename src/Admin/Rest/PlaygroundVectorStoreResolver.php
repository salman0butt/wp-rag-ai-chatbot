<?php
/**
 * Playground vector-store composition boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\VectorStore\VectorSearchStore;
use WpRagAiChatbot\VectorStore\VectorStoreRegistry;

/**
 * Resolves the persisted Playground vector-store selector through the production registry authority.
 */
final readonly class PlaygroundVectorStoreResolver {
	/**
	 * Create the resolver.
	 *
	 * @param VectorStoreRegistry $registry Production vector-store registry.
	 */
	public function __construct( private VectorStoreRegistry $registry ) {
	}

	/**
	 * Resolve the persisted vector-store selection as a raw-vector search adapter.
	 *
	 * @param PlaygroundSemanticConfiguration $semantic Persisted semantic runtime identity.
	 */
	public function resolve( PlaygroundSemanticConfiguration $semantic ): VectorSearchStore {
		return $this->registry->search( $semantic->vector_store_id );
	}
}
