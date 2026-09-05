<?php
/**
 * Hybrid reranking integration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Fusion\RankedCandidate;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\HybridRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetrievalChannel;
use WpRagAiChatbot\Retrieval\Rerank\Reranker;
use WpRagAiChatbot\Retrieval\Rerank\RerankRequest;
use WpRagAiChatbot\Retrieval\Rerank\RerankResult;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\RetrievalException;
use WpRagAiChatbot\Retrieval\RetrievalQuery;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalChannel;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Proves reranking runs only after trusted access filtering and remains bounded.
 */
final class HybridRerankingTest extends TestCase {
	/**
	 * Reranking sees only access-approved candidates and the configured top-N.
	 */
	public function test_reranker_receives_only_access_approved_bounded_candidates(): void {
		$reranker = new class() implements Reranker {
			/**
			 * Candidate IDs observed by the fake reranker.
			 *
			 * @var list<string>
			 */
			public array $seen = array();

			/**
			 * Record supplied candidate IDs.
			 *
			 * @param RerankRequest $request Bounded rerank request.
			 */
			public function rerank( RerankRequest $request ): RerankResult {
				$this->seen = array_column( $request->candidates, 'chunk_id' );
				return new RerankResult( array( $request->candidates[0]->chunk_id => 1.0 ) );
			}
		};

		$policy = new class() implements CandidateAccessPolicy {
			/**
			 * Reject one leading fixture.
			 *
			 * @param RetrievalCandidate $candidate Candidate under review.
			 * @param RetrievalFilter    $filter Trusted filter.
			 */
			public function allows( RetrievalCandidate $candidate, RetrievalFilter $filter ): bool {
				return 'blocked' !== $candidate->chunk_id;
			}
		};

		$config = new RetrievalConfig( rerank_top_n: 1, context_candidate_limit: 2 );

		$retriever = $this->retriever(
			array( $this->ranked( 'blocked', 1.0 ), $this->ranked( 'allowed-a', 0.9 ), $this->ranked( 'allowed-b', 0.8 ) ),
			array(),
			$policy,
			$config,
			$reranker
		);

		$result = $retriever->retrieve( $this->query(), $this->semantic_context(), $this->lexical_filter(), true );

		self::assertSame( array( 'allowed-a' ), $reranker->seen );
		self::assertSame( array( 'allowed-a', 'allowed-b' ), array_column( $result->candidates, 'chunk_id' ) );
		self::assertSame( 'applied', $result->trace->rerank_status );
	}

	/**
	 * Unknown reranker IDs cannot inject candidates and fall back when configured.
	 */
	public function test_unknown_reranker_id_falls_back_to_fused_order_when_enabled(): void {
		$reranker = new class() implements Reranker {
			/**
			 * Return an unauthorized candidate ID.
			 *
			 * @param RerankRequest $request Bounded rerank request.
			 */
			public function rerank( RerankRequest $request ): RerankResult {
				return new RerankResult( array( 'injected' => 1.0 ) );
			}
		};

		$retriever = $this->retriever(
			array( $this->ranked( 'first', 1.0 ), $this->ranked( 'second', 0.9 ) ),
			array(),
			null,
			new RetrievalConfig( rerank_failure_fallback: true ),
			$reranker
		);

		$result = $retriever->retrieve( $this->query(), $this->semantic_context(), $this->lexical_filter(), true );

		self::assertSame( array( 'first', 'second' ), array_column( $result->candidates, 'chunk_id' ) );
		self::assertSame( 'fallback_unavailable', $result->trace->rerank_status );
	}

	/**
	 * Invalid reranker output fails closed when fallback is disabled.
	 */
	public function test_unknown_reranker_id_fails_when_fallback_is_disabled(): void {
		$reranker = new class() implements Reranker {
			/**
			 * Return an unauthorized candidate ID.
			 *
			 * @param RerankRequest $request Bounded rerank request.
			 */
			public function rerank( RerankRequest $request ): RerankResult {
				return new RerankResult( array( 'injected' => 1.0 ) );
			}
		};

		$retriever = $this->retriever(
			array( $this->ranked( 'first', 1.0 ) ),
			array(),
			null,
			new RetrievalConfig( rerank_failure_fallback: false ),
			$reranker
		);

		$this->expectException( RetrievalException::class );
		$this->expectExceptionMessage( 'Reranker failed.' );
		$retriever->retrieve( $this->query(), $this->semantic_context(), $this->lexical_filter(), true );
	}

	/**
	 * Create the orchestrator with deterministic fake channels.
	 *
	 * @param array                      $semantic Semantic fixtures.
	 * @param array                      $lexical Lexical fixtures.
	 * @param CandidateAccessPolicy|null $policy Optional trusted policy.
	 * @param RetrievalConfig            $config Retrieval bounds.
	 * @param Reranker                   $reranker Reranker under test.
	 * @phpstan-param list<RankedCandidate> $semantic
	 * @phpstan-param list<RankedCandidate> $lexical
	 */
	private function retriever(
		array $semantic,
		array $lexical,
		?CandidateAccessPolicy $policy,
		RetrievalConfig $config,
		Reranker $reranker
	): HybridRetriever {
		$semantic_channel = new class( $semantic ) implements SemanticRetrievalChannel {
			/**
			 * Create the fake semantic channel.
			 *
			 * @param array $result Semantic fixtures.
			 * @phpstan-param list<RankedCandidate> $result
			 */
			public function __construct( private array $result ) {
			}

			/**
			 * Return semantic fixtures.
			 *
			 * @param RetrievalQuery           $query Query.
			 * @param SemanticRetrievalContext $context Trusted context.
			 * @return list<RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				return $this->result;
			}
		};

		$lexical_channel = new class( $lexical ) implements LexicalRetrievalChannel {
			/**
			 * Create the fake lexical channel.
			 *
			 * @param array $result Lexical fixtures.
			 * @phpstan-param list<RankedCandidate> $result
			 */
			public function __construct( private array $result ) {
			}

			/**
			 * Return lexical fixtures.
			 *
			 * @param RetrievalQuery $query Query.
			 * @param LexicalFilter  $filter Trusted filter.
			 * @return list<RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				return $this->result;
			}
		};

		$policy ??= new class() implements CandidateAccessPolicy {
			/**
			 * Allow all fixtures.
			 *
			 * @param RetrievalCandidate $candidate Candidate.
			 * @param RetrievalFilter    $filter Trusted filter.
			 */
			public function allows( RetrievalCandidate $candidate, RetrievalFilter $filter ): bool {
				return true;
			}
		};

		return new HybridRetriever(
			$semantic_channel,
			$lexical_channel,
			new ReciprocalRankFusion( $config ),
			new ConfidenceEstimator(),
			$policy,
			$config,
			$reranker
		);
	}

	/**
	 * Create one native candidate fixture.
	 *
	 * @param string $id Stable chunk identifier.
	 * @param float  $score Native score.
	 */
	private function ranked( string $id, float $score ): RankedCandidate {
		return new RankedCandidate( $id, 'doc-' . $id, 8, 'content-' . $id, 'en', 'public', $score );
	}

	/**
	 * Create the query fixture.
	 */
	private function query(): RetrievalQuery {
		return new RetrievalQuery( 'refund policy', array( 'refund', 'policy' ) );
	}

	/**
	 * Create trusted semantic context.
	 */
	private function semantic_context(): SemanticRetrievalContext {
		return new SemanticRetrievalContext(
			new RetrievalFilter( 'public', 'en', array( 8 ) ),
			static fn (): null => null
		);
	}

	/**
	 * Create trusted lexical scope.
	 */
	private function lexical_filter(): LexicalFilter {
		return new LexicalFilter( 'collection-1', null, 8, 'en', 'public' );
	}
}
