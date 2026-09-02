<?php
/**
 * Document extractor registry.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use InvalidArgumentException;

/**
 * Deterministic MIME-to-extractor registry.
 */
final class DocumentExtractorRegistry {
	/**
	 * Registered extractors keyed by normalized MIME type.
	 *
	 * @var array<string, DocumentExtractor>
	 */
	private array $extractors = array();

	/**
	 * Register one extractor for all MIME types it owns.
	 *
	 * @param DocumentExtractor $extractor Extractor to register.
	 * @throws InvalidArgumentException When MIME ownership is invalid or duplicated.
	 */
	public function register( DocumentExtractor $extractor ): void {
		$mime_types = $extractor->supportedMimeTypes();

		if ( array() === $mime_types ) {
			throw new InvalidArgumentException( 'Document extractor must support at least one MIME type.' );
		}

		$normalized = array();
		foreach ( $mime_types as $mime_type ) {
			$mime_type = strtolower( trim( $mime_type ) );
			if ( '' === $mime_type ) {
				throw new InvalidArgumentException( 'Document extractor MIME type must not be empty.' );
			}
			if ( isset( $normalized[ $mime_type ] ) || isset( $this->extractors[ $mime_type ] ) ) {
				throw new InvalidArgumentException( 'Document extractor MIME type is already registered.' );
			}

			$normalized[ $mime_type ] = true;
		}

		foreach ( array_keys( $normalized ) as $mime_type ) {
			$this->extractors[ $mime_type ] = $extractor;
		}
	}

	/**
	 * Resolve the extractor that owns one exact MIME type.
	 *
	 * @param string $mime_type MIME type to resolve.
	 * @throws ExtractionException When no extractor owns the MIME type.
	 */
	public function get( string $mime_type ): DocumentExtractor {
		$mime_type = strtolower( trim( $mime_type ) );
		if ( '' === $mime_type || ! isset( $this->extractors[ $mime_type ] ) ) {
			throw new ExtractionException( 'No document extractor is registered for this MIME type.' );
		}

		return $this->extractors[ $mime_type ];
	}
}
