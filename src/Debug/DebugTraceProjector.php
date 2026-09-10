<?php
/**
 * Retrieval debug trace projection and redaction.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Debug;

use WpRagAiChatbot\Retrieval\ChannelEvidence;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalResult;

// phpcs:disable WordPress.NamingConventions -- DTO keys follow the approved debug contract.
/**
 * Projects production retrieval evidence into a bounded allow-listed debug DTO.
 */
final class DebugTraceProjector {
	private const MAX_CANDIDATES             = 20;
	private const MAX_CHANNEL_EVIDENCE       = 4;
	private const MAX_CANDIDATE_SCALAR_BYTES = 256;
	private const MAX_CHUNK_CONTENT_BYTES    = 2000;

	/**
	 * Project one production M10 retrieval result.
	 *
	 * @param RetrievalResult $result Production retrieval result to project.
	 */
	public function projectRetrieval( RetrievalResult $result ): DebugTrace {
		$candidates = array();
		foreach ( array_slice( $result->candidates, 0, self::MAX_CANDIDATES ) as $candidate ) {
			$candidates[] = self::project_candidate( $candidate );
		}

		$channel_counts = array();
		foreach ( array( 'semantic', 'lexical' ) as $channel ) {
			if ( array_key_exists( $channel, $result->trace->channel_counts ) ) {
				$channel_counts[ $channel ] = $result->trace->channel_counts[ $channel ];
			}
		}

		return new DebugTrace(
			$result->trace->query_hash,
			$result->trace->query_bytes,
			$channel_counts,
			$result->trace->channel_failures,
			$result->trace->rerank_status,
			$candidates
		);
	}

	/**
	 * Project one retrieval candidate without recursive/raw object serialization.
	 *
	 * @param RetrievalCandidate $candidate Retrieval candidate to project.
	 * @return array<string,mixed>
	 */
	private static function project_candidate( RetrievalCandidate $candidate ): array {
		$truncated        = strlen( $candidate->content ) > self::MAX_CHUNK_CONTENT_BYTES;
		$content          = $truncated
			? self::truncate_utf8_bytes( $candidate->content, self::MAX_CHUNK_CONTENT_BYTES )
			: $candidate->content;
		$channel_evidence = array();

		foreach ( $candidate->channel_evidence as $evidence ) {
			if ( ! in_array( $evidence->channel, array( 'semantic', 'lexical' ), true ) ) {
				continue;
			}

			$channel_evidence[] = self::project_channel_evidence( $evidence );
			if ( count( $channel_evidence ) >= self::MAX_CHANNEL_EVIDENCE ) {
				break;
			}
		}

		return array(
			'chunk_id'          => self::bound_scalar( $candidate->chunk_id ),
			'document_id'       => self::bound_scalar( $candidate->document_id ),
			'source_id'         => $candidate->source_id,
			'language'          => null === $candidate->language ? null : self::bound_scalar( $candidate->language ),
			'visibility'        => self::bound_scalar( $candidate->visibility ),
			'fused_score'       => $candidate->fused_score,
			'rerank_score'      => $candidate->rerank_score,
			'channel_evidence'  => $channel_evidence,
			'content'           => $content,
			'content_truncated' => $truncated,
		);
	}

	/**
	 * Project one allow-listed channel evidence value.
	 *
	 * @param ChannelEvidence $evidence Channel evidence to project.
	 * @return array<string,int|float|string>
	 */
	private static function project_channel_evidence( ChannelEvidence $evidence ): array {
		return array(
			'channel'          => $evidence->channel,
			'native_score'     => $evidence->native_score,
			'rank'             => $evidence->rank,
			'weight'           => $evidence->weight,
			'rrf_contribution' => $evidence->rrf_contribution,
		);
	}

	/**
	 * Bound one candidate scalar before it crosses the debug DTO boundary.
	 *
	 * @param string $value Candidate scalar value.
	 */
	private static function bound_scalar( string $value ): string {
		if ( strlen( $value ) <= self::MAX_CANDIDATE_SCALAR_BYTES ) {
			return $value;
		}

		return self::truncate_utf8_bytes( $value, self::MAX_CANDIDATE_SCALAR_BYTES );
	}

	/**
	 * Truncate to a byte limit without leaving an incomplete trailing UTF-8 sequence.
	 *
	 * @param string $content Content to truncate.
	 * @param int    $max_bytes Maximum UTF-8-safe byte length.
	 */
	private static function truncate_utf8_bytes( string $content, int $max_bytes ): string {
		$truncated = substr( $content, 0, $max_bytes );

		while ( '' !== $truncated && 1 !== preg_match( '//u', $truncated ) ) {
			$truncated = substr( $truncated, 0, -1 );
		}

		return $truncated;
	}
}
// phpcs:enable WordPress.NamingConventions
