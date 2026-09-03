<?php
/**
 * XML document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Extracts bounded XML text without entity or network expansion.
 */
final class XmlDocumentExtractor implements DocumentExtractor {
	private const MAX_DEPTH = 64;

	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Implements the approved extractor contract.
	/**
	 * Return owned MIME types.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array {
		return array( 'application/xml', 'text/xml' );
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

	/**
	 * Extract deterministic XML leaf text.
	 *
	 * @param ValidatedFile $file Validated local file.
	 * @throws ExtractionException When the validated file cannot be safely extracted.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Extraction reads only a previously validated local file.
		$contents = file_get_contents( $file->path );
		if (
			false === $contents
			|| str_contains( $contents, "\0" )
			|| 1 !== preg_match( '//u', $contents )
			|| false !== stripos( $contents, '<!DOCTYPE' )
		) {
			throw new ExtractionException( 'Unable to extract XML document.' );
		}

		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		try {
			$loaded = $document->loadXML( $contents, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM extension exposes camelCase properties.
		$root = $document->documentElement;
		if ( ! $loaded || ! $root instanceof DOMElement ) {
			throw new ExtractionException( 'Unable to extract XML document.' );
		}
		$this->assertDepth( $root, 1 );

		$xpath = new DOMXPath( $document );
		$nodes = $xpath->query( '//*[not(*)]' );
		if ( false === $nodes ) {
			throw new ExtractionException( 'Unable to extract XML document.' );
		}

		$lines = array();
		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMNode ) {
				continue;
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM extension exposes camelCase properties.
			$text = preg_replace( '/\s+/u', ' ', trim( $node->textContent ) );
			if ( is_string( $text ) && '' !== $text ) {
				$lines[] = $text;
			}
		}

		if ( array() === $lines ) {
			throw new ExtractionException( 'XML document contains no extractable text.' );
		}

		return new ExtractedDocument(
			implode( "\n", $lines ),
			array(
				'format' => 'xml',
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM extension exposes camelCase properties.
				'root'   => $root->tagName,
			)
		);
	}

	/**
	 * Enforce the maximum element nesting depth.
	 *
	 * @param DOMElement $element Current element.
	 * @param int        $depth Current depth.
	 * @throws ExtractionException When XML nesting exceeds the extraction limit.
	 */
	private function assertDepth( DOMElement $element, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			throw new ExtractionException( 'XML document exceeds extraction limits.' );
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM extension exposes camelCase properties.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				$this->assertDepth( $child, $depth + 1 );
			}
		}
	}
}
