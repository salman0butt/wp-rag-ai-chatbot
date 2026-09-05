<?php
/**
 * Hybrid retrieval orchestration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Fusion\RankedCandidate;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\HybridRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetrievalChannel;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\RetrievalException;
use WpRagAiChatbot\Retrieval\RetrievalQuery;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalChannel;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Defines deterministic hybrid orchestration, degradation, and final safety behavior.
 */
final class HybridRetrieverTest extends TestCase {
	public function test_both_channels_are_fused_and_duplicates_collapse(): void {
		$retriever = $this->retriever(
			array( $this->ranked( 'shared', 0.95 ), $this->ranked( 'semantic-only', 0.90 ) ),
			array( $this->ranked( 'lexical-only', 1.0 ), $this->ranked( 'shared', 0.80 ) )
		);

		$result = $retriever->retrieve( $this->query(), $this->semantic_context(), $this->lexical_filter() );

		self::assertSame( array( 'shared', 'lexical-only', 'semantic-only' ), array_column( $result->candidates, 'chunk_id' ) );
		self::assertCount( 2, $result->candidates[0]->channel_evidence );
		self::assertSame( 'high', $result->candidates[0]->confidence?->level );
	}

	public function test_post_fusion_access_policy_runs_before_final_limit(): void {
		$policy = new class() implements CandidateAccessPolicy {
			public function allows( \WpRagAiChatbot\Retrieval\RetrievalCandidate $candidate, RetrievalFilter $filter ): bool {
				return 'blocked' !== $candidate->chunk_id;
			}
		};
		$config = new RetrievalConfig( context_candidate_limit: 1 );
		$retriever = $this->retriever(
			array( $this->ranked( 'blocked', 1.0 ), $this->ranked( 'allowed', 0.9 ) ),
			array(),
			$policy,
			$config
		);

		$result = $retriever->retrieve( $this->query(), $this->semantic_context(), $this->lexical_filter(), true );

		self::assertSame( array( 'allowed' ), array_column( $result->candidates, 'chunk_id' ) );
	}

	public function test_single_channel_degradation_requires_explicit_opt_in_and_trace_is_sanitized(): void {
		$retriever = $this->retriever( new RuntimeException( 'secret provider response body' ), array( $this->ranked( 'lexical', 1.0 ) ) );

		try {
			$retriever->retrieve( $this->query(), $this->semantic_context(), $this->lexical_filter() );
			self::fail( 'Expected retrieval failure when degradation is disabled.' );
		} catch ( RetrievalException $exception ) {
			self::assertSame( 'Hybrid retrieval channel failed.', $exception->getMessage() );
		}

		$result = $retriever->retrieve( $this->query(), $this->semantic_context(), $this->lexical_filter(), true );
		self::assertSame( array( 'lexical' ), array_column( $result->candidates, 'chunk_id' ) );
		self::assertSame( array( 'semantic' => 'semantic_unavailable' ), $result->trace->channel_failures );
	}

	public function test_both_channel_failures_always_fail(): void {
		$retriever = $this->retriever( new RuntimeException( 'semantic secret' ), new RuntimeException( 'lexical secret' ) );

		$this->expectException( RetrievalException::class );
		$this->expectExceptionMessage( 'Hybrid retrieval channels unavailable.' );
		$retriever->retrieve( $this->query(), $this->semantic_context(), $this->lexical_filter(), true );
	}

	/** @param array<RankedCandidate>|RuntimeException $semantic @param array<RankedCandidate>|RuntimeException $lexical */
	private function retriever( array|RuntimeException $semantic, array|RuntimeException $lexical, ?CandidateAccessPolicy $policy = null, ?RetrievalConfig $config = null ): HybridRetriever {
		$semantic_channel = new class( $semantic ) implements SemanticRetrievalChannel {
			public function __construct( private array|RuntimeException $result ) {}
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				if ( $this->result instanceof RuntimeException ) { throw $this->result; }
				return $this->result;
			}
		};
		$lexical_channel = new class( $lexical ) implements LexicalRetrievalChannel {
			public function __construct( private array|RuntimeException $result ) {}
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				if ( $this->result instanceof RuntimeException ) { throw $this->result; }
				return $this->result;
			}
		};
		$policy ??= new class() implements CandidateAccessPolicy {
			public function allows( \WpRagAiChatbot\Retrieval\RetrievalCandidate $candidate, RetrievalFilter $filter ): bool { return true; }
		};
		$config ??= new RetrievalConfig();

		return new HybridRetriever( $semantic_channel, $lexical_channel, new ReciprocalRankFusion( $config ), new ConfidenceEstimator(), $policy, $config );
	}

	private function ranked( string $id, float $score ): RankedCandidate {
		return new RankedCandidate( $id, 'doc-' . $id, 8, 'content-' . $id, 'en', 'public', $score );
	}

	private function query(): RetrievalQuery { return new RetrievalQuery( 'refund policy', array( 'refund', 'policy' ) ); }

	private function semantic_context(): SemanticRetrievalContext {
		return new SemanticRetrievalContext( new RetrievalFilter( 'public', 'en', array( 8 ) ), static fn (): null => null );
	}

	private function lexical_filter(): LexicalFilter { return new LexicalFilter( 'collection-1', null, 8, 'en', 'public' ); }
}
