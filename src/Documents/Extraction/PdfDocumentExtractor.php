<?php
/**
 * PDF document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extracts visible text from validated PDF files through a stable boundary.
 */
final class PdfDocumentExtractor implements DocumentExtractor {
	private const DEFAULT_MAX_PAGES = 200;
	private const DEFAULT_MAX_TEXT_BYTES = 2097152;

	/**
	 * Configure bounded PDF extraction.
	 *
	 * @param int $maxPages Maximum parsed pages.
	 * @param int $maxTextBytes Maximum normalized extracted-text bytes.
	 */
	public function __construct(
		private readonly int $maxPages = self::DEFAULT_MAX_PAGES,
		private readonly int $maxTextBytes = self::DEFAULT_MAX_TEXT_BYTES
	) {
		if ( $this->maxPages <= 0 || $this->maxTextBytes <= 0 ) {
			throw new InvalidArgumentException( 'PDF extraction limits must be positive.' );
		}
	}

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

			$document = ( new Parser() )->parseFile( $file->path );
			if ( count( $document->getPages() ) > $this->maxPages ) {
				throw new ExtractionException( 'PDF extraction failed.' );
			}

			$text = $document->getText();
			$text = trim( str_replace( array( "\r\n", "\r" ), "\n", $text ) );
			if ( '' === $text || strlen( $text ) > $this->maxTextBytes ) {
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
