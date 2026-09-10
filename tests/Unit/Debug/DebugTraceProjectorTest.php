<?php
/**
 * Debug trace projection tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Debug;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Debug\DebugTraceProjector;
use WpRagAiChatbot\Retrieval\ChannelEvidence;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalResult;
use WpRagAiChatbot\Retrieval\RetrievalTrace;

/**
 * Proves retrieval debug output is explicit, bounded, and safe to serialize.
 */
final class DebugTraceProjectorTest extends TestCase {
	/**
	 * Existing M10 retrieval evidence maps to an explicit bounded debug DTO.
	 */
	public function test_projects_bounded_allow_list_from_retrieval_result(): void {
		$candidates = array();
		for ( $index = 1; $index <= 25; $index++ ) {
			$content = str_repeat( 'å', 1200 );
			if ( 25 === $index ) {
				$content .= 'PROVIDER-SECRET-SENTINEL';
			}

			$candidates[] = new RetrievalCandidate(
				'chunk-' . $index,
				'doc-' . $index,
				$index,
				$content,
				'en',
				'public',
				array(
					new ChannelEvidence( 'semantic', 0.9, $index, 1.0, 0.01 ),
				),
				0.01,
				null,
				0.8
			);
		}

		$retrieval = new RetrievalResult(
			$candidates,
			new RetrievalTrace(
				hash( 'sha256', 'RAW-QUERY-SECRET-SENTINEL' ),
				25,
				array(
					'semantic' => 25,
					'lexical'  => 0,
				),
				array( 'lexical' => 'lexical_unavailable' ),
				'applied'
			)
		);

		$projected = ( new DebugTraceProjector() )->projectRetrieval( $retrieval )->to_array();
		$json      = (string) wp_json_encode( $projected );

		self::assertSame(
			array( 'query', 'channels', 'rerank_status', 'candidates' ),
			array_keys( $projected )
		);
		self::assertSame( 20, count( $projected['candidates'] ) );
		self::assertSame( 25, $projected['query']['bytes'] );
		self::assertSame( 64, strlen( $projected['query']['hash'] ) );
		self::assertSame( 'lexical_unavailable', $projected['channels']['failures']['lexical'] );
		self::assertSame( 'applied', $projected['rerank_status'] );
		self::assertSame(
			array(
				'chunk_id',
				'document_id',
				'source_id',
				'language',
				'visibility',
				'fused_score',
				'rerank_score',
				'channel_evidence',
				'content',
				'content_truncated',
			),
			array_keys( $projected['candidates'][0] )
		);
		self::assertTrue( $projected['candidates'][0]['content_truncated'] );
		self::assertLessThanOrEqual( 2000, strlen( $projected['candidates'][0]['content'] ) );
		self::assertSame( 1, preg_match( '//u', $projected['candidates'][0]['content'] ) );
		self::assertStringNotContainsString( 'RAW-QUERY-SECRET-SENTINEL', $json );
		self::assertStringNotContainsString( 'PROVIDER-SECRET-SENTINEL', $json );
	}

	/**
	 * Only repository-owned retrieval channel identifiers may be serialized.
	 */
	public function test_filters_unapproved_channel_count_keys(): void {
		$retrieval = new RetrievalResult(
			array(),
			new RetrievalTrace(
				hash( 'sha256', 'safe query' ),
				10,
				array(
					'semantic'               => 3,
					'SECRET-CHANNEL-SENTINEL' => 99,
				)
			)
		);

		$projected = ( new DebugTraceProjector() )->projectRetrieval( $retrieval )->to_array();
		$json      = (string) wp_json_encode( $projected );

		self::assertSame( array( 'semantic' => 3 ), $projected['channels']['counts'] );
		self::assertStringNotContainsString( 'SECRET-CHANNEL-SENTINEL', $json );
	}
}
