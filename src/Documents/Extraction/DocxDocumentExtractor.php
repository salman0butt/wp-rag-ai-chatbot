<?php
/**
 * DOCX document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

/**
 * Extracts visible text from validated DOCX files through PHPWord.
 */
final readonly class DocxDocumentExtractor implements DocumentExtractor {
	/**
	 * Create the DOCX extractor.
	 *
	 * @param DocxArchiveInspector $archive_inspector Pre-parser ZIP resource guard.
	 */
	public function __construct( private DocxArchiveInspector $archive_inspector ) {
	}

	/**
	 * Return owned MIME types.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array {
		return array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
	}

	/**
	 * Extract normalized DOCX text.
	 *
	 * @param ValidatedFile $file Validated DOCX file.
	 * @throws ExtractionException When DOCX parsing cannot be completed safely.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument {
		try {
			$this->archive_inspector->inspect( $file->path );
			$document = IOFactory::load( $file->path, 'Word2007' );
			$lines    = array();

			foreach ( $document->getSections() as $section ) {
				$this->collectContainerText( $section, $lines );
			}

			$text = trim( implode( "\n", $lines ) );
			if ( '' === $text ) {
				throw new ExtractionException( 'DOCX extraction failed.' );
			}

			return new ExtractedDocument(
				text: $text,
				metadata: array( 'format' => 'docx' )
			);
		} catch ( ExtractionException $exception ) {
			throw $exception;
		} catch ( Throwable ) {
			throw new ExtractionException( 'DOCX extraction failed.' );
		}
	}

	/**
	 * Collect visible text recursively from one PHPWord container.
	 *
	 * @param AbstractContainer $container PHPWord element container.
	 * @param array<string>     $lines Collected output lines.
	 */
	private function collectContainerText( AbstractContainer $container, array &$lines ): void {
		foreach ( $container->getElements() as $element ) {
			if ( $element instanceof Text ) {
				$text = trim( $element->getText() );
				if ( '' !== $text ) {
					$lines[] = $text;
				}
				continue;
			}

			if ( $element instanceof AbstractContainer ) {
				$this->collectContainerText( $element, $lines );
			}
		}
	}
}
