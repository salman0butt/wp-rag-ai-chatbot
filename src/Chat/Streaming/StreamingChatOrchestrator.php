<?php
/**
 * Provider-neutral streaming normalization.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat\Streaming;

use Throwable;
use WpRagAiChatbot\Chat\ChatFailureReason;
use WpRagAiChatbot\Citations\CitationRegistry;
use WpRagAiChatbot\Citations\CitationValidator;

/**
 * Normalizes provider stream deltas into stable bounded application events.
 */
final class StreamingChatOrchestrator {
	private const MAX_DELTA_BYTES = 4096;

	/**
	 * Create the streaming normalizer.
	 *
	 * @param CitationValidator $citation_validator Request-local citation validator.
	 */
	public function __construct( private readonly CitationValidator $citation_validator = new CitationValidator() ) {
	}

	/**
	 * Normalize one provider stream.
	 *
	 * @param GenerationStream $stream Provider-neutral text-delta stream.
	 * @param CitationRegistry $registry Trusted request-local citation registry.
	 * @param Cancellation     $cancellation Application-owned cancellation hook.
	 * @return \Generator<int,StreamEvent,void,void>
	 */
	public function stream(
		GenerationStream $stream,
		CitationRegistry $registry,
		Cancellation $cancellation
	): \Generator {
		$sequence = 0;
		$answer   = '';

		yield new StreamEvent( $sequence++, StreamEventType::MESSAGE_START );

		try {
			while ( ! $cancellation->is_cancelled() ) {
				$delta = $stream->next_delta();
				if ( null === $delta ) {
					break;
				}
				if ( '' === $delta ) {
					continue;
				}

				foreach ( $this->bounded_deltas( $delta ) as $bounded_delta ) {
					// The generator may resume after its caller mutates Cancellation between yielded chunks.
					// @phpstan-ignore if.alwaysFalse.
					if ( $cancellation->is_cancelled() ) {
						break;
					}

					$answer .= $bounded_delta;
					yield new StreamEvent( $sequence++, StreamEventType::MESSAGE_DELTA, $bounded_delta );
				}
			}

			if ( $cancellation->is_cancelled() ) {
				yield new StreamEvent( $sequence, StreamEventType::ERROR, null, null, ChatFailureReason::CANCELLED );
				return;
			}

			$validation = $this->citation_validator->validate( $answer, $registry );
			if ( ! $validation->valid ) {
				yield new StreamEvent( $sequence, StreamEventType::ERROR, null, null, ChatFailureReason::INVALID_CITATIONS );
				return;
			}

			foreach ( $validation->citations as $citation ) {
				yield new StreamEvent( $sequence++, StreamEventType::CITATION, null, $citation->id );
			}

			yield new StreamEvent( $sequence, StreamEventType::MESSAGE_COMPLETE );
		} catch ( Throwable ) {
			yield new StreamEvent( $sequence, StreamEventType::ERROR, null, null, ChatFailureReason::GENERATION_FAILED );
		} finally {
			try {
				$stream->close();
			} catch ( Throwable $cleanup_exception ) {
				// Provider cleanup diagnostics are intentionally discarded at the client-safe boundary.
				unset( $cleanup_exception );
			}
		}
	}

	/**
	 * Split one provider delta into UTF-8-preserving bounded chunks.
	 *
	 * @param string $delta Provider text delta.
	 * @return list<string>
	 */
	private function bounded_deltas( string $delta ): array {
		if ( strlen( $delta ) <= self::MAX_DELTA_BYTES ) {
			return array( $delta );
		}

		$characters = preg_split( '//u', $delta, -1, PREG_SPLIT_NO_EMPTY );
		if ( false === $characters ) {
			return str_split( $delta, self::MAX_DELTA_BYTES );
		}

		$chunks  = array();
		$current = '';
		foreach ( $characters as $character ) {
			if ( '' !== $current && strlen( $current . $character ) > self::MAX_DELTA_BYTES ) {
				$chunks[] = $current;
				$current  = '';
			}
			$current .= $character;
		}
		$chunks[] = $current;

		return $chunks;
	}
}
