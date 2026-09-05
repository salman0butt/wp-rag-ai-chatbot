<?php
/**
 * Reciprocal-rank fusion tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval\Fusion;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\Fusion\RankedCandidate;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\RetrievalConfig;

/**
 * Defines deterministic weighted RRF behavior for M10.
 */
final class ReciprocalRankFusionTest extends TestCase {
	/**
	 * Cross-channel matches are deduplicated and their weighted contributions add.
	 */
	public function test_fusion_sums_rank_contributions_for_duplicate_chunks(): void {
		$fusion = new ReciprocalRankFusion( new RetrievalConfig() );

		$results = $fusion->fuse(
			array(
				'semantic' => array(
					$this->candidate( 'chunk-a', 0.91 ),
					$this->candidate( 'chunk-b', 0.80 ),
				),
				'lexical'  => array(
					$this->candidate( 'chunk-a', 12.0 ),
					$this->candidate( 'chunk-c', 9.0 ),
				),
			)
		);

		self::assertSame( array( 'chunk-a', 'chunk-b', 'chunk-c' ), array_column( $results, 'chunk_id' ) );
		self::assertEqualsWithDelta( 2.0 / 61.0, $results[0]->fused_score, 0.000000001 );
		self::assertCount( 2, $results[0]->channel_evidence );
		self::assertEqualsWithDelta( 1.0 / 61.0, $results[0]->channel_evidence[0]->rrf_contribution, 0.000000001 );
		self::assertEqualsWithDelta( 1.0 / 61.0, $results[0]->channel_evidence[1]->rrf_contribution, 0.000000001 );
	}

	/**
	 * Configured channel weights affect contributions without normalizing native scores.
	 */
	public function test_fusion_uses_configured_channel_weights(): void {
		$config = new RetrievalConfig( semantic_weight: 2.0, lexical_weight: 0.5 );
		$fusion = new ReciprocalRankFusion( $config );

		$results = $fusion->fuse(
			array(
				'semantic' => array( $this->candidate( 'chunk-semantic', 0.50 ) ),
				'lexical'  => array( $this->candidate( 'chunk-lexical', 500.0 ) ),
			)
		);

		self::assertSame( 'chunk-semantic', $results[0]->chunk_id );
		self::assertEqualsWithDelta( 2.0 / 61.0, $results[0]->fused_score, 0.000000001 );
		self::assertEqualsWithDelta( 0.5 / 61.0, $results[1]->fused_score, 0.000000001 );
	}

	/**
	 * Native score ties use stable chunk IDs so input ordering cannot change output.
	 */
	public function test_fusion_breaks_native_score_ties_by_chunk_id(): void {
		$fusion = new ReciprocalRankFusion( new RetrievalConfig() );

		$results = $fusion->fuse(
			array(
				'semantic' => array(
					$this->candidate( 'chunk-b', 0.75 ),
					$this->candidate( 'chunk-a', 0.75 ),
				),
			)
		);

		self::assertSame( array( 'chunk-a', 'chunk-b' ), array_column( $results, 'chunk_id' ) );
	}

	/**
	 * Fused output cannot exceed the configured hard candidate ceiling.
	 */
	public function test_fusion_applies_fused_candidate_limit(): void {
		$fusion = new ReciprocalRankFusion( new RetrievalConfig( fused_candidate_limit: 2 ) );

		$results = $fusion->fuse(
			array(
				'lexical' => array(
					$this->candidate( 'chunk-a', 3.0 ),
					$this->candidate( 'chunk-b', 2.0 ),
					$this->candidate( 'chunk-c', 1.0 ),
				),
			)
		);

		self::assertCount( 2, $results );
		self::assertSame( array( 'chunk-a', 'chunk-b' ), array_column( $results, 'chunk_id' ) );
	}

	/**
	 * Fusion parameters must remain finite and positive.
	 */
	public function test_config_rejects_invalid_fusion_parameters(): void {
		$this->expectException( InvalidArgumentException::class );
		new RetrievalConfig( rrf_k: 0 );
	}

	/**
	 * Create one pre-fusion candidate with stable lineage.
	 *
	 * @param string $chunk_id Stable chunk identifier.
	 * @param float  $native_score Native channel score.
	 */
	private function candidate( string $chunk_id, float $native_score ): RankedCandidate {
		return new RankedCandidate(
			$chunk_id,
			'doc-' . $chunk_id,
			7,
			'Content for ' . $chunk_id,
			'en',
			'public',
			$native_score
		);
	}
}
