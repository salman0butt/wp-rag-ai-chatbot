<?php
/**
 * WordPress production public-chat runtime composition root.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Admin\Rest\PlaygroundEmbeddingProviderResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundGenerationProviderResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundHybridRetrieverResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticConfigurationResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticRetrieverResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundVectorCollectionResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundVectorStoreResolver;
use WpRagAiChatbot\Chat\ChatRequestPolicy;
use WpRagAiChatbot\Chat\ProductionChatResponderFactory;
use WpRagAiChatbot\Citations\CitationValidator;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbKnowledgeSourceRepository;
use WpRagAiChatbot\Database\Repository\WpdbMessageRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Embeddings\EmbeddingBatchConfig;
use WpRagAiChatbot\Memory\MemoryAssembler;
use WpRagAiChatbot\Providers\ProviderBootstrap;
use WpRagAiChatbot\RAG\DeterministicGroundingPolicy;
use WpRagAiChatbot\RAG\PromptBuilder;
use WpRagAiChatbot\Retrieval\Access\DefaultCandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Filter\VectorFilterMapper;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalScorer;
use WpRagAiChatbot\Retrieval\Lexical\WpdbChunkSearchStore;
use WpRagAiChatbot\Retrieval\QueryPreprocessor;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\VectorStore\Local\LocalVectorStoreConfig;
use WpRagAiChatbot\VectorStore\VectorStoreBootstrap;

/**
 * Composes existing production M10/M11 authorities for anonymous public chat.
 */
final class PublicChatRuntimeBootstrap {
	/**
	 * Build one request-local executor resolver without executing retrieval or provider work.
	 *
	 * @param Connection $connection Existing WordPress database connection.
	 * @param TableNames $tables Existing per-site table-name authority.
	 */
	public static function executor_resolver( Connection $connection, TableNames $tables ): PublicChatProductionExecutorResolver {
		$retrieval = new RetrievalConfig();
		$chunks    = new WpdbChunkSearchStore( $connection, $tables );

		ProviderBootstrap::register();
		VectorStoreBootstrap::register_local(
			$connection,
			$tables,
			new LocalVectorStoreConfig( $retrieval->lexical_candidate_limit, $retrieval->semantic_top_k )
		);

		$providers  = ProviderBootstrap::registry();
		$semantic   = new PlaygroundSemanticRetrieverResolver(
			new PlaygroundEmbeddingProviderResolver( $providers ),
			new PlaygroundVectorCollectionResolver(),
			new PlaygroundVectorStoreResolver( VectorStoreBootstrap::registry() ),
			new EmbeddingBatchConfig( 1 ),
			new VectorFilterMapper(),
			$retrieval
		);
		$hybrid     = new PlaygroundHybridRetrieverResolver(
			new LexicalRetriever( $chunks, new LexicalScorer(), $retrieval ),
			new ReciprocalRankFusion( $retrieval ),
			new ConfidenceEstimator(),
			new DefaultCandidateAccessPolicy(),
			$retrieval
		);
		$responders = new ProductionChatResponderFactory(
			new ChatRequestPolicy( new QueryPreprocessor( $retrieval ) ),
			new MemoryAssembler( new WpdbMessageRepository( $connection, $tables ) ),
			new DeterministicGroundingPolicy(),
			new PromptBuilder(),
			new CitationValidator()
		);
		$resolver   = new PublicChatResponderResolver(
			new PublicChatKnowledgeSourceResolver( new WpdbKnowledgeSourceRepository( $connection, $tables ) ),
			new PlaygroundSemanticConfigurationResolver(),
			$semantic,
			$hybrid,
			new PlaygroundGenerationProviderResolver( $providers ),
			$responders,
			new WpdbMessageRepository( $connection, $tables )
		);

		return new PublicChatProductionExecutorResolver(
			static fn ( PublicChatRuntime $runtime ) => $resolver->resolve( $runtime ),
			new PublicChatAccessContextResolver( $chunks )
		);
	}
}
