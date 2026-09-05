<?php
/**
 * Safe retrieval trace contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

use InvalidArgumentException;

/**
 * Immutable query diagnostics that omit raw query content by default.
 */
final readonly class RetrievalTrace {
	/**
	 * Create one safe retrieval trace.
	 *
	 * @param string $query_hash SHA-256 hash of the normalized query.
	 * @param int    $query_bytes Normalized query byte length.
	 * @param array  $channel_counts Bounded per-channel candidate counts.
	 * @phpstan-param array<string, int> $channel_counts
	 * @throws InvalidArgumentException When trace values are invalid.
	 */
	public function __construct(
		public string $query_hash,
		public int $query_bytes,
		public array $channel_counts
	) {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $query_hash ) || $query_bytes < 0 ) {
			throw new InvalidArgumentException( 'Retrieval trace query diagnostics are invalid.' );
		}

		foreach ( $channel_counts as $channel => $count ) {
			if ( '' === trim( $channel ) || $count < 0 ) {
				throw new InvalidArgumentException( 'Retrieval trace channel diagnostics are invalid.' );
			}
		}
	}
}
