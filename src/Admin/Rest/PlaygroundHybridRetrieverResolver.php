<?php
/**
 * Playground hybrid-retriever composition boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\HybridRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetrievalChannel;
use WpRagAiChatbot\Retrieval\Rerank\Reranker;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalChannel;

/**
 * Composes the existing production hybrid retriever for one Playground request.
 */
final readonly class PlaygroundHybridRetrieverResolver {
	/**
	 * Create the request-local hybrid-retriever composer.
	 *
	 * @param LexicalRetrievalChannel $lexical Existing production lexical retrieval channel.
	 * @param ReciprocalRankFusion    $fusion Existing production deterministic fusion service.
	 * @param ConfidenceEstimator     $confidence Existing production confidence estimator.
	 * @param CandidateAccessPolicy   $access_policy Existing production trusted access policy.
	 * @param RetrievalConfig         $retrieval_config Existing bounded production retrieval configuration.
	 * @param Reranker|null           $reranker Existing optional production reranker.
	 */
	public function __construct(
		private LexicalRetrievalChannel $lexical,
		private ReciprocalRankFusion $fusion,
		private ConfidenceEstimator $confidence,
		private CandidateAccessPolicy $access_policy,
		private RetrievalConfig $retrieval_config,
		private ?Reranker $reranker = null
	) {
	}

	/**
	 * Compose one production hybrid retriever without executing retrieval work.
	 *
	 * @param SemanticRetrievalChannel $semantic Existing request-local production semantic channel.
	 */
	public function resolve( SemanticRetrievalChannel $semantic ): HybridRetriever {
		return new HybridRetriever(
			$semantic,
			$this->lexical,
			$this->fusion,
			$this->confidence,
			$this->access_policy,
			$this->retrieval_config,
			$this->reranker
		);
	}
}
