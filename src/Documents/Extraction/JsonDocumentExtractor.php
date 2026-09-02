<?php
/**
 * JSON document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use JsonException;

/**
 * Extracts bounded deterministic JSON text.
 */
final class JsonDocumentExtractor implements DocumentExtractor {
	private const MAX_DEPTH = 64;

	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Implements the approved extractor contract.
	/**
	 * Return owned MIME types.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array {
		return array( 'application/json' );
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

	/**
	 * Extract canonical pretty-printed JSON text.
	 *
	 * @param ValidatedFile $file Validated local file.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Extraction reads only a previously validated local file.
		$contents = file_get_contents( $file->path );
		if ( false === $contents || str_contains( $contents, "\0" ) || 1 !== preg_match( '//u', $contents ) ) {
			throw new ExtractionException( 'Unable to extract JSON document.' );
		}

		try {
			$decoded = json_decode( $contents, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR );
			$text    = json_encode(
				$decoded,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			);
		} catch ( JsonException ) {
			throw new ExtractionException( 'Unable to extract JSON document.' );
		}

		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			throw new ExtractionException( 'JSON document contains no extractable text.' );
		}

		return new ExtractedDocument( $text, array( 'format' => 'json' ) );
	}
}
