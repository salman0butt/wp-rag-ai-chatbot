<?php
/**
 * Citation validation result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Citations;

use InvalidArgumentException;

/**
 * Immutable result of validating model-authored citation markers.
 */
final readonly class CitationValidationResult {
	/**
	 * Validated citations in first-use answer order.
	 *
	 * @var list<Citation>
	 */
	public array $citations;

	/**
	 * Unknown, duplicate, or malformed citation identifiers.
	 *
	 * @var list<string>
	 */
	public array $invalid_markers;

	/**
	 * Create a validation result.
	 *
	 * @param bool  $valid Whether all discovered citation markers are valid.
	 * @param array $citations Validated citations.
	 * @param array $invalid_markers Invalid marker identifiers.
	 * @phpstan-param array<array-key, mixed> $citations
	 * @phpstan-param array<array-key, mixed> $invalid_markers
	 * @throws InvalidArgumentException When result members are invalid.
	 */
	public function __construct( public bool $valid, array $citations, array $invalid_markers ) {
		$validated_citations = array();
		foreach ( $citations as $citation ) {
			if ( ! $citation instanceof Citation ) {
				throw new InvalidArgumentException( 'Citation validation result contains an invalid citation.' );
			}
			$validated_citations[] = $citation;
		}

		$validated_markers = array();
		foreach ( $invalid_markers as $marker ) {
			if ( ! is_string( $marker ) || '' === trim( $marker ) ) {
				throw new InvalidArgumentException( 'Citation validation result contains an invalid marker.' );
			}
			$validated_markers[] = $marker;
		}

		if ( $valid && array() !== $validated_markers ) {
			throw new InvalidArgumentException( 'A valid citation result cannot contain invalid markers.' );
		}

		$this->citations       = $validated_citations;
		$this->invalid_markers = $validated_markers;
	}
}
