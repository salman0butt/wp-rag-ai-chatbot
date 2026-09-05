<?php
/**
 * Retrieval result contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\ChannelEvidence;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalResult;
use WpRagAiChatbot\Retrieval\RetrievalTrace;

/**
 * Defines immutable candidate, evidence, trace, and result boundaries.
 */
final class RetrievalContractsTest extends TestCase {
	/**
	 * Candidate lineage and channel evidence remain explicit immutable values.
	 */
	public function test_candidate_preserves_bounded_lineage_and_evidence(): void {
		$evidence = new ChannelEvidence( 'semantic', 0.91, 1, 1.0, 0.0163934426 );
		$candidate = new RetrievalCandidate(
			'chunk-1',
			'doc-1',
			42,
			'Grounded chunk content.',
			'en',
			'public',
			array( $evidence ),
			0.0163934426
		);

		self::assertSame( 'chunk-1', $candidate->chunk_id );
		self::assertSame( 'doc-1', $candidate->document_id );
		self::assertSame( 42, $candidate->source_id );
		self::assertSame( 'public', $candidate->visibility );
		self::assertSame( array( $evidence ), $candidate->channel_evidence );
	}

	/**
	 * Channel evidence rejects impossible ranks and non-finite scores.
	 */
	public function test_channel_evidence_rejects_invalid_numeric_values(): void {
		$this->expectException( InvalidArgumentException::class );
		new ChannelEvidence( 'lexical', INF, 0, 1.0, 0.0 );
	}

	/**
	 * Trace stores only safe query diagnostics and bounded channel counts.
	 */
	public function test_trace_exposes_safe_diagnostics_without_raw_query(): void {
		$trace = new RetrievalTrace(
			hash( 'sha256', 'reset SKU-42/A' ),
			14,
			array(
				'semantic' => 3,
				'lexical'  => 5,
			)
		);

		self::assertSame( 64, strlen( $trace->query_hash ) );
		self::assertSame( 14, $trace->query_bytes );
		self::assertSame( 3, $trace->channel_counts['semantic'] );
		self::assertFalse( property_exists( $trace, 'query' ) );
	}

	/**
	 * Result preserves deterministic ordered candidate membership and trace.
	 */
	public function test_result_contains_ordered_candidates_and_trace(): void {
		$evidence = new ChannelEvidence( 'lexical', 10.0, 1, 1.0, 0.0163934426 );
		$candidate = new RetrievalCandidate(
			'chunk-2',
			'doc-2',
			7,
			'Exact SKU text.',
			null,
			'public',
			array( $evidence ),
			0.0163934426
		);
		$trace = new RetrievalTrace( hash( 'sha256', 'SKU-42/A' ), 8, array( 'lexical' => 1 ) );
		$result = new RetrievalResult( array( $candidate ), $trace );

		self::assertSame( array( $candidate ), $result->candidates );
		self::assertSame( $trace, $result->trace );
	}
}
