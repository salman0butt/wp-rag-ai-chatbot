<?php
/**
 * Playground semantic-retriever composition boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Embeddings\EmbeddingBatchConfig;
use WpRagAiChatbot\Embeddings\EmbeddingService;
use WpRagAiChatbot\Retrieval\Filter\VectorFilterMapper;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetriever;

/**
 * Composes the existing production semantic retriever from persisted Playground runtime identity.
 */
final readonly class PlaygroundSemanticRetrieverResolver {
	/**
	 * Create the request-local semantic retriever composer.
	 *
	 * @param PlaygroundEmbeddingProviderResolver $embedding_provider_resolver Persisted embedding-provider resolver.
	 * @param PlaygroundVectorCollectionResolver  $collection_resolver Canonical vector-collection resolver.
	 * @param PlaygroundVectorStoreResolver       $vector_store_resolver Persisted vector-store resolver.
	 * @param EmbeddingBatchConfig                $embedding_batch_config Bounded production embedding batch configuration.
	 * @param VectorFilterMapper                  $filter_mapper Trusted retrieval-filter mapper.
	 * @param RetrievalConfig                     $retrieval_config Bounded production retrieval configuration.
	 */
	public function __construct(
		private PlaygroundEmbeddingProviderResolver $embedding_provider_resolver,
		private PlaygroundVectorCollectionResolver $collection_resolver,
		private PlaygroundVectorStoreResolver $vector_store_resolver,
		private EmbeddingBatchConfig $embedding_batch_config,
		private VectorFilterMapper $filter_mapper,
		private RetrievalConfig $retrieval_config
	) {
	}

	/**
	 * Compose one production semantic retriever without performing embedding or search work.
	 *
	 * @param PlaygroundRetrievalConfiguration $retrieval Persisted source and collection selection.
	 * @param PlaygroundSemanticConfiguration  $semantic Persisted embedding/vector-store identity.
	 */
	public function resolve(
		PlaygroundRetrievalConfiguration $retrieval,
		PlaygroundSemanticConfiguration $semantic
	): SemanticRetriever {
		$embedding_provider = $this->embedding_provider_resolver->resolve( $semantic );
		$embedding_service  = new EmbeddingService( $embedding_provider, $this->embedding_batch_config );
		$collection         = $this->collection_resolver->resolve( $retrieval, $semantic );
		$vector_store       = $this->vector_store_resolver->resolve( $semantic );

		return new SemanticRetriever(
			$embedding_service,
			$semantic->embedding_profile,
			$collection,
			$vector_store,
			$this->filter_mapper,
			$this->retrieval_config
		);
	}
}
