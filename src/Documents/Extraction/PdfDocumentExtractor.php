<?php
/**
 * PDF document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extracts visible text from validated PDF files through a stable boundary.
 */
final class PdfDocumentExtractor implements DocumentExtractor {
	/**
	 * Return owned MIME types.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array {
		return array( 'application/pdf' );
	}

	/**
	 * Extract normalized PDF text.
	 *
	 * @param ValidatedFile $file Validated PDF file.
	 * @throws ExtractionException When PDF parsing cannot be completed safely.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Validated local file; WordPress filesystem abstraction is not appropriate for parser input.
			$raw_pdf = file_get_contents( $file->path );
			if ( false === $raw_pdf || false !== strpos( $raw_pdf, '/Encrypt' ) ) {
				throw new ExtractionException( 'PDF extraction failed.' );
			}

			$text = ( new Parser() )->parseFile( $file->path )->getText();
			$text = trim( str_replace( array( "\r\n", "\r" ), "\n", $text ) );
			if ( '' === $text ) {
				throw new ExtractionException( 'PDF extraction failed.' );
			}

			return new ExtractedDocument(
				text: $text,
				metadata: array( 'format' => 'pdf' )
			);
		} catch ( ExtractionException $exception ) {
			throw $exception;
		} catch ( Throwable ) {
			throw new ExtractionException( 'PDF extraction failed.' );
		}
	}
}
