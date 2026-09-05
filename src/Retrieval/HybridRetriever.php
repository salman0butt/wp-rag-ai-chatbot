<?php
/**
 * Fail-closed hybrid retrieval orchestration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

use RuntimeException;
use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetrievalChannel;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalChannel;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Runs bounded retrieval channels, fuses evidence, rechecks trusted access, and emits safe diagnostics.
 */
final readonly class HybridRetriever {
	/**
	 * Create the hybrid retrieval orchestrator.
	 *
	 * @param SemanticRetrievalChannel $semantic Semantic retrieval channel.
	 * @param LexicalRetrievalChannel  $lexical Lexical retrieval channel.
	 * @param ReciprocalRankFusion     $fusion Deterministic fusion service.
	 * @param ConfidenceEstimator      $confidence Deterministic confidence estimator.
	 * @param CandidateAccessPolicy    $access_policy Post-fusion trusted access policy.
	 * @param RetrievalConfig          $config Bounded retrieval configuration.
	 */
	public function __construct(
		private SemanticRetrievalChannel $semantic,
		private LexicalRetrievalChannel $lexical,
		private ReciprocalRankFusion $fusion,
		private ConfidenceEstimator $confidence,
		private CandidateAccessPolicy $access_policy,
		private RetrievalConfig $config
	) {
	}

	/**
	 * Execute one hybrid retrieval request.
	 *
	 * @param RetrievalQuery           $query Preprocessed retrieval query.
	 * @param SemanticRetrievalContext $semantic_context Trusted semantic scope and resolver.
	 * @param LexicalFilter            $lexical_filter Trusted lexical scope.
	 * @param bool                     $allow_single_channel_degradation Whether one unavailable channel may degrade.
	 * @throws RetrievalException When required channel availability is not satisfied.
	 */
	public function retrieve(
		RetrievalQuery $query,
		SemanticRetrievalContext $semantic_context,
		LexicalFilter $lexical_filter,
		bool $allow_single_channel_degradation = false
	): RetrievalResult {
		$channels = array();
		$failures = array();
		$counts   = array(
			'semantic' => 0,
			'lexical'  => 0,
		);

		try {
			$channels['semantic'] = $this->semantic->retrieve( $query, $semantic_context );
			$counts['semantic']   = count( $channels['semantic'] );
		} catch ( RuntimeException ) {
			$failures['semantic'] = 'semantic_unavailable';
		}

		try {
			$channels['lexical'] = $this->lexical->retrieve( $query, $lexical_filter );
			$counts['lexical']   = count( $channels['lexical'] );
		} catch ( RuntimeException ) {
			$failures['lexical'] = 'lexical_unavailable';
		}

		if ( 2 === count( $failures ) ) {
			throw new RetrievalException( 'Hybrid retrieval channels unavailable.' );
		}
		if ( array() !== $failures && ! $allow_single_channel_degradation ) {
			throw new RetrievalException( 'Hybrid retrieval channel failed.' );
		}

		$approved = array();
		foreach ( $this->fusion->fuse( $channels ) as $candidate ) {
			if ( ! $this->access_policy->allows( $candidate, $semantic_context->filter ) ) {
				continue;
			}
			$approved[] = $this->with_confidence( $candidate );
		}

		$approved = array_slice( $approved, 0, $this->config->context_candidate_limit );
		$trace    = new RetrievalTrace(
			hash( 'sha256', $query->normalized ),
			strlen( $query->normalized ),
			$counts,
			$failures
		);

		return new RetrievalResult( $approved, $trace );
	}

	/**
	 * Attach deterministic retrieval confidence without changing candidate lineage or evidence.
	 *
	 * @param RetrievalCandidate $candidate Access-approved fused candidate.
	 */
	private function with_confidence( RetrievalCandidate $candidate ): RetrievalCandidate {
		return new RetrievalCandidate(
			$candidate->chunk_id,
			$candidate->document_id,
			$candidate->source_id,
			$candidate->content,
			$candidate->language,
			$candidate->visibility,
			$candidate->channel_evidence,
			$candidate->fused_score,
			$this->confidence->estimate( $candidate )
		);
	}
}
