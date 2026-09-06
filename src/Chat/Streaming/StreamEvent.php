<?php
/**
 * Normalized bounded streaming event.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat\Streaming;

use InvalidArgumentException;
use WpRagAiChatbot\Chat\ChatFailureReason;

/**
 * Immutable client-safe stream event.
 */
final readonly class StreamEvent {
	private const MAX_TEXT_BYTES = 16384;

	/**
	 * Create one normalized event.
	 *
	 * @param int                    $sequence Monotonic non-negative sequence.
	 * @param StreamEventType        $type Stable event type.
	 * @param string|null            $text Normalized text payload when applicable.
	 * @param string|null            $citation_id Request-local citation ID when applicable.
	 * @param ChatFailureReason|null $error_reason Stable safe error reason when applicable.
	 */
	public function __construct(
		public int $sequence,
		public StreamEventType $type,
		public ?string $text = null,
		public ?string $citation_id = null,
		public ?ChatFailureReason $error_reason = null
	) {
		if ( $sequence < 0 ) {
			throw new InvalidArgumentException( 'Stream event sequence must not be negative.' );
		}

		if ( null !== $text && strlen( $text ) > self::MAX_TEXT_BYTES ) {
			throw new InvalidArgumentException( 'Stream event text exceeds the hard byte limit.' );
		}

		if ( null !== $citation_id && 1 !== preg_match( '/^C[1-9][0-9]*$/', $citation_id ) ) {
			throw new InvalidArgumentException( 'Citation ID is invalid.' );
		}

		if ( StreamEventType::MESSAGE_DELTA === $type && ( null === $text || '' === $text ) ) {
			throw new InvalidArgumentException( 'Message delta requires non-empty text.' );
		}

		if ( StreamEventType::CITATION === $type && null === $citation_id ) {
			throw new InvalidArgumentException( 'Citation event requires a citation ID.' );
		}

		if ( StreamEventType::ERROR === $type && null === $error_reason ) {
			throw new InvalidArgumentException( 'Error event requires a stable failure reason.' );
		}
	}
}
