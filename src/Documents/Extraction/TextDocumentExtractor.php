<?php
/**
 * Plain text document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

/**
 * Extracts validated UTF-8 plain text files.
 */
final class TextDocumentExtractor implements DocumentExtractor {
	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Implements the approved extractor contract.
	/**
	 * Return owned MIME types.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array {
		return array( 'text/plain' );
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

	/**
	 * Extract normalized plain text.
	 *
	 * @param ValidatedFile $file Validated local file.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Extraction reads only a previously validated local file.
		$contents = file_get_contents( $file->path );
		if ( false === $contents || str_contains( $contents, "\0" ) || 1 !== preg_match( '//u', $contents ) ) {
			throw new ExtractionException( 'Unable to extract plain text document.' );
		}

		$text = trim( str_replace( array( "\r\n", "\r" ), "\n", $contents ) );
		if ( '' === $text ) {
			throw new ExtractionException( 'Plain text document contains no extractable text.' );
		}

		return new ExtractedDocument( $text, array( 'format' => 'txt' ) );
	}
}
