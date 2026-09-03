<?php
/**
 * Extracted document value.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use InvalidArgumentException;

/**
 * Immutable normalized parser output.
 */
final readonly class ExtractedDocument {
	/**
	 * Create extracted document output.
	 *
	 * @param string               $text Normalized extracted text.
	 * @param array<string, mixed> $metadata Safe parser metadata.
	 * @throws InvalidArgumentException When extracted text is blank.
	 */
	public function __construct(
		public string $text,
		public array $metadata
	) {
		if ( '' === trim( $text ) ) {
			throw new InvalidArgumentException( 'Extracted document text must not be blank.' );
		}
	}
}
