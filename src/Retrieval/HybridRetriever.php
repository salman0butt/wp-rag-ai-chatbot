<?php
/**
 * Fail-closed hybrid retrieval orchestration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

use InvalidArgumentException;
use RuntimeException;
use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetrievalChannel;
use WpRagAiChatbot\Retrieval\Rerank\Reranker;
use WpRagAiChatbot\Retrieval\Rerank\RerankRequest;
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
	 * @param Reranker|null            $reranker Optional post-filter reranker.
	 */
	public function __construct(
		private SemanticRetrievalChannel $semantic,
		private LexicalRetrievalChannel $lexical,
		private ReciprocalRankFusion $fusion,
		private ConfidenceEstimator $confidence,
		private CandidateAccessPolicy $access_policy,
		private RetrievalConfig $config,
		private ?Reranker $reranker = null
	) {
	}

	/**
	 * Execute one hybrid retrieval request.
	 *
	 * @param RetrievalQuery           $query Preprocessed retrieval query.
	 * @param SemanticRetrievalContext $semantic_context Trusted semantic scope and resolver.
	 * @param LexicalFilter            $lexical_filter Trusted lexical scope.
	 * @param bool                     $allow_single_channel_degradation Whether one unavailable channel may degrade.
	 * @throws RetrievalException When required channel availability or reranking policy is not satisfied.
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

		list( $approved, $rerank_status ) = $this->rerank( $query, $approved );
		$approved                         = array_slice( $approved, 0, $this->config->context_candidate_limit );
		$trace                            = new RetrievalTrace(
			hash( 'sha256', $query->normalized ),
			strlen( $query->normalized ),
			$counts,
			$failures,
			$rerank_status
		);

		return new RetrievalResult( $approved, $trace );
	}

	/**
	 * Apply optional reranking only to the bounded access-approved prefix.
	 *
	 * @param RetrievalQuery $query Preprocessed query.
	 * @param array          $approved Access-approved fused candidates.
	 * @phpstan-param list<RetrievalCandidate> $approved
	 * @return array{0: list<RetrievalCandidate>, 1: string}
	 * @throws InvalidArgumentException When reranker output references an unknown candidate.
	 * @throws RetrievalException When reranking fails and fallback is disabled.
	 */
	private function rerank( RetrievalQuery $query, array $approved ): array {
		if ( null === $this->reranker || array() === $approved ) {
			return array( $approved, 'disabled' );
		}

		$top  = array_slice( $approved, 0, $this->config->rerank_top_n );
		$tail = array_slice( $approved, $this->config->rerank_top_n );

		try {
			$result = $this->reranker->rerank( new RerankRequest( $query, $top ) );
			$by_id  = array();
			$rank   = array();
			foreach ( $top as $index => $candidate ) {
				$by_id[ $candidate->chunk_id ] = $candidate;
				$rank[ $candidate->chunk_id ]  = $index;
			}

			$rows = array();
			foreach ( $result->scores as $chunk_id => $score ) {
				if ( ! isset( $by_id[ $chunk_id ] ) ) {
					throw new InvalidArgumentException( 'Reranker returned an unknown candidate.' );
				}
				$rows[] = array(
					'candidate' => $by_id[ $chunk_id ],
					'score'     => $score,
					'rank'      => $rank[ $chunk_id ],
				);
			}

			usort(
				$rows,
				static function ( array $left, array $right ): int {
					$score_order = $right['score'] <=> $left['score'];
					return 0 !== $score_order ? $score_order : $left['rank'] <=> $right['rank'];
				}
			);

			$reranked = array();
			foreach ( $rows as $row ) {
				$reranked[] = $this->with_rerank_score( $row['candidate'], $row['score'] );
			}

			return array( array_merge( $reranked, $tail ), 'applied' );
		} catch ( RuntimeException | InvalidArgumentException ) {
			if ( $this->config->rerank_failure_fallback ) {
				return array( $approved, 'fallback_unavailable' );
			}

			throw new RetrievalException( 'Reranker failed.' );
		}
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
			$this->confidence->estimate( $candidate ),
			$candidate->rerank_score
		);
	}

	/**
	 * Attach a finite rerank score while preserving trusted candidate data.
	 *
	 * @param RetrievalCandidate $candidate Access-approved candidate.
	 * @param float              $rerank_score Validated reranker score.
	 */
	private function with_rerank_score( RetrievalCandidate $candidate, float $rerank_score ): RetrievalCandidate {
		return new RetrievalCandidate(
			$candidate->chunk_id,
			$candidate->document_id,
			$candidate->source_id,
			$candidate->content,
			$candidate->language,
			$candidate->visibility,
			$candidate->channel_evidence,
			$candidate->fused_score,
			$candidate->confidence,
			$rerank_score
		);
	}
}
