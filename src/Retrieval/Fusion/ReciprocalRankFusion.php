<?php
/**
 * Deterministic reciprocal-rank fusion.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Fusion;

use InvalidArgumentException;
use WpRagAiChatbot\Retrieval\ChannelEvidence;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalConfig;

/**
 * Fuse bounded native-channel rankings without comparing their score scales.
 */
final readonly class ReciprocalRankFusion {
	/**
	 * Create the fusion service.
	 *
	 * @param RetrievalConfig $config Retrieval execution configuration.
	 */
	public function __construct( private RetrievalConfig $config ) {
	}

	/**
	 * Fuse channel rankings by stable chunk ID.
	 *
	 * @param array $channels Ranked candidates keyed by channel name.
	 * @phpstan-param array<string, array<array-key, RankedCandidate>> $channels
	 * @return list<RetrievalCandidate>
	 */
	public function fuse( array $channels ): array {
		/** @var array<string, array{candidate: RankedCandidate, evidence: list<ChannelEvidence>, score: float, best_rank: int}> $fused */
		$fused = array();

		foreach ( $channels as $channel => $candidates ) {
			$weight = $this->channel_weight( $channel );
			$ranked = array_values( $candidates );

			foreach ( $ranked as $candidate ) {
				if ( ! $candidate instanceof RankedCandidate ) {
					throw new InvalidArgumentException( 'Fusion channels must contain ranked candidates.' );
				}
			}

			usort(
				$ranked,
				static function ( RankedCandidate $left, RankedCandidate $right ): int {
					$score_order = $right->native_score <=> $left->native_score;
					return 0 !== $score_order ? $score_order : strcmp( $left->chunk_id, $right->chunk_id );
				}
			);

			$seen = array();
			$rank = 0;
			foreach ( $ranked as $candidate ) {
				if ( isset( $seen[ $candidate->chunk_id ] ) ) {
					continue;
				}
				$seen[ $candidate->chunk_id ] = true;
				++$rank;

				$contribution = $weight / ( $this->config->rrf_k + $rank );
				$evidence     = new ChannelEvidence(
					$channel,
					$candidate->native_score,
					$rank,
					$weight,
					$contribution
				);

				if ( ! isset( $fused[ $candidate->chunk_id ] ) ) {
					$fused[ $candidate->chunk_id ] = array(
						'candidate' => $candidate,
						'evidence'  => array( $evidence ),
						'score'     => $contribution,
						'best_rank' => $rank,
					);
					continue;
				}

				$existing = $fused[ $candidate->chunk_id ]['candidate'];
				if (
					$existing->document_id !== $candidate->document_id ||
					$existing->source_id !== $candidate->source_id ||
					$existing->content !== $candidate->content ||
					$existing->language !== $candidate->language ||
					$existing->visibility !== $candidate->visibility
				) {
					throw new InvalidArgumentException( 'Duplicate chunk IDs must have identical retrieval lineage.' );
				}

				$fused[ $candidate->chunk_id ]['evidence'][] = $evidence;
				$fused[ $candidate->chunk_id ]['score']     += $contribution;
				$fused[ $candidate->chunk_id ]['best_rank']  = min( $fused[ $candidate->chunk_id ]['best_rank'], $rank );
			}
		}

		$entries = array_values( $fused );
		usort(
			$entries,
			static function ( array $left, array $right ): int {
				$score_order = $right['score'] <=> $left['score'];
				if ( 0 !== $score_order ) {
					return $score_order;
				}

				$rank_order = $left['best_rank'] <=> $right['best_rank'];
				return 0 !== $rank_order
					? $rank_order
					: strcmp( $left['candidate']->chunk_id, $right['candidate']->chunk_id );
			}
		);

		$entries = array_slice( $entries, 0, $this->config->fused_candidate_limit );

		return array_map(
			static fn ( array $entry ): RetrievalCandidate => new RetrievalCandidate(
				$entry['candidate']->chunk_id,
				$entry['candidate']->document_id,
				$entry['candidate']->source_id,
				$entry['candidate']->content,
				$entry['candidate']->language,
				$entry['candidate']->visibility,
				$entry['evidence'],
				$entry['score']
			),
			$entries
		);
	}

	/**
	 * Resolve one supported channel's configured fusion weight.
	 *
	 * @param string $channel Retrieval channel identifier.
	 * @return float
	 * @throws InvalidArgumentException When the channel is unsupported.
	 */
	private function channel_weight( string $channel ): float {
		return match ( $channel ) {
			'semantic' => $this->config->semantic_weight,
			'lexical'  => $this->config->lexical_weight,
			default    => throw new InvalidArgumentException( 'Unsupported retrieval fusion channel.' ),
		};
	}
}
