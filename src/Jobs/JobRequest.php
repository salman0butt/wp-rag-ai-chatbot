<?php
/**
 * Immutable queue request.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use JsonException;

/**
 * Validates one persisted job enqueue request before storage.
 */
final class JobRequest {
	private const MAX_PAYLOAD_BYTES = 65536;
	private const MAX_PAYLOAD_DEPTH = 8;

	/**
	 * Create a bounded queue request.
	 *
	 * @param string               $type Job-handler type.
	 * @param array<string, mixed> $payload JSON-compatible payload object.
	 * @param string|null          $idempotency_key Optional active-job deduplication key.
	 * @param int                  $max_attempts Maximum execution attempts.
	 * @throws JobQueueException When any queue-request field is outside its safe contract.
	 */
	public function __construct(
		public readonly string $type,
		public readonly array $payload,
		public readonly ?string $idempotency_key = null,
		public readonly int $max_attempts = 3
	) {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_.-]{0,99}$/', $type ) ) {
			throw new JobQueueException( 'Job type must use the stable lowercase queue grammar.' );
		}
		if ( null !== $idempotency_key && ( '' === $idempotency_key || strlen( $idempotency_key ) > 191 ) ) {
			throw new JobQueueException( 'Idempotency key must contain 1 to 191 bytes when provided.' );
		}
		if ( $max_attempts < 1 || $max_attempts > 10 ) {
			throw new JobQueueException( 'Maximum attempts must be between 1 and 10.' );
		}
		if ( self::payload_depth( $payload ) > self::MAX_PAYLOAD_DEPTH ) {
			throw new JobQueueException( 'Job payload nesting exceeds the supported depth.' );
		}

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This pure domain contract runs without a WordPress runtime.
			$encoded = json_encode( $payload, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new JobQueueException( 'Job payload must contain JSON-compatible values.' );
		}

		if ( strlen( $encoded ) > self::MAX_PAYLOAD_BYTES ) {
			throw new JobQueueException( 'Job payload exceeds the maximum encoded size.' );
		}
	}

	/**
	 * Determine the maximum nested array depth.
	 *
	 * @param array<mixed> $value Current payload level.
	 */
	private static function payload_depth( array $value ): int {
		$depth = 1;
		foreach ( $value as $item ) {
			if ( is_array( $item ) ) {
				$depth = max( $depth, 1 + self::payload_depth( $item ) );
			}
		}
		return $depth;
	}
}
