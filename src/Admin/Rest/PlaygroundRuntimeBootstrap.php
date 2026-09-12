<?php
/**
 * WordPress production Playground runtime composition root.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Chat\ChatRequestPolicy;
use WpRagAiChatbot\Chat\ProductionChatResponderFactory;
use WpRagAiChatbot\Citations\CitationValidator;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbBotRepository;
use WpRagAiChatbot\Database\Repository\WpdbKnowledgeSourceRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Debug\DebugTraceProjector;
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
 * Composes the existing production M10/M11 authorities for the administrator Playground.
 */
final class PlaygroundRuntimeBootstrap {
	/**
	 * Build one production request handler without executing retrieval or provider work.
	 *
	 * @param Connection $connection Existing WordPress database connection.
	 * @param TableNames $tables Existing per-site table-name authority.
	 */
	public static function handler( Connection $connection, TableNames $tables ): PlaygroundRequestHandler {
		$retrieval = new RetrievalConfig();
		$chunks    = new WpdbChunkSearchStore( $connection, $tables );

		ProviderBootstrap::register();
		VectorStoreBootstrap::register_local(
			$connection,
			$tables,
			new LocalVectorStoreConfig( $retrieval->lexical_candidate_limit, $retrieval->semantic_top_k )
		);

		$providers = ProviderBootstrap::registry();

		$configuration = new PlaygroundConfigurationResolver(
			new PlaygroundBotConfigurationResolver( new WpdbBotRepository( $connection, $tables ) ),
			new PlaygroundRetrievalConfigurationResolver(
				new WpdbKnowledgeSourceRepository( $connection, $tables ),
				$connection,
				$tables
			)
		);

		$semantic = new PlaygroundSemanticRetrieverResolver(
			new PlaygroundEmbeddingProviderResolver( $providers ),
			new PlaygroundVectorCollectionResolver(),
			new PlaygroundVectorStoreResolver( VectorStoreBootstrap::registry() ),
			new EmbeddingBatchConfig( 1 ),
			new VectorFilterMapper(),
			$retrieval
		);

		$hybrid = new PlaygroundHybridRetrieverResolver(
			new LexicalRetriever( $chunks, new LexicalScorer(), $retrieval ),
			new ReciprocalRankFusion( $retrieval ),
			new ConfidenceEstimator(),
			new DefaultCandidateAccessPolicy(),
			$retrieval
		);

		$responders = new ProductionChatResponderFactory(
			new ChatRequestPolicy( new QueryPreprocessor( $retrieval ) ),
			new MemoryAssembler( new StatelessPlaygroundConversationHistory() ),
			new DeterministicGroundingPolicy(),
			new PromptBuilder(),
			new CitationValidator()
		);
		$chat_graph = new PlaygroundChatGraphResolver(
			$responders,
			new DebugTraceProjector()
		);

		return new PlaygroundRequestHandler(
			$configuration,
			new PlaygroundExecutorResolver(
				new PlaygroundSemanticConfigurationResolver(),
				$semantic,
				$hybrid,
				new PlaygroundGenerationProviderResolver( $providers ),
				new PlaygroundAccessContextResolver( $chunks ),
				$chat_graph
			)
		);
	}
}
