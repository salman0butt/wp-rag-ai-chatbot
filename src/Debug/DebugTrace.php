<?php
/**
 * Administrator-safe structured debug trace.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Debug;

/**
 * Immutable explicit debug projection ready for later REST serialization.
 */
final readonly class DebugTrace {
	/**
	 * Create one bounded retrieval debug trace.
	 *
	 * @param string $query_hash SHA-256 normalized query hash.
	 * @param int    $query_bytes Normalized query byte length.
	 * @param array  $channel_counts Safe per-channel counts.
	 * @param array  $channel_failures Stable per-channel failure codes.
	 * @param string $rerank_status Stable reranker status.
	 * @param array  $candidates Bounded allow-listed candidate projections.
	 * @phpstan-param array<string, int> $channel_counts
	 * @phpstan-param array<string, string> $channel_failures
	 * @phpstan-param list<array<string, mixed>> $candidates
	 */
	public function __construct(
		private string $query_hash,
		private int $query_bytes,
		private array $channel_counts,
		private array $channel_failures,
		private string $rerank_status,
		private array $candidates
	) {
	}

	/**
	 * Return the explicit serialization contract.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'query'         => array(
				'hash'  => $this->query_hash,
				'bytes' => $this->query_bytes,
			),
			'channels'      => array(
				'counts'   => $this->channel_counts,
				'failures' => $this->channel_failures,
			),
			'rerank_status' => $this->rerank_status,
			'candidates'    => $this->candidates,
		);
	}
}
