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
	 * @param array  $channel_failures Sanitized per-channel failure reason codes.
	 * @phpstan-param array<string, int> $channel_counts
	 * @phpstan-param array<string, string> $channel_failures
	 * @throws InvalidArgumentException When trace values are invalid.
	 */
	public function __construct(
		public string $query_hash,
		public int $query_bytes,
		public array $channel_counts,
		public array $channel_failures = array()
	) {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $query_hash ) || $query_bytes < 0 ) {
			throw new InvalidArgumentException( 'Retrieval trace query diagnostics are invalid.' );
		}

		foreach ( $channel_counts as $channel => $count ) {
			if ( '' === trim( $channel ) || $count < 0 ) {
				throw new InvalidArgumentException( 'Retrieval trace channel diagnostics are invalid.' );
			}
		}

		foreach ( $channel_failures as $channel => $reason ) {
			if (
				! is_string( $channel ) ||
				! is_string( $reason ) ||
				! in_array( $channel, array( 'semantic', 'lexical' ), true ) ||
				$reason !== $channel . '_unavailable'
			) {
				throw new InvalidArgumentException( 'Retrieval trace failure diagnostics are invalid.' );
			}
		}
	}
}
