<?php
/**
 * HTML document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Extracts bounded visible text from validated HTML.
 */
final class HtmlDocumentExtractor implements DocumentExtractor {
	private const MAX_NODES = 5000;

	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Implements the approved extractor contract.
	/**
	 * Return owned MIME types.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array {
		return array( 'text/html' );
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

	/**
	 * Extract deterministic visible HTML text.
	 *
	 * @param ValidatedFile $file Validated local file.
	 * @throws ExtractionException When the validated file cannot be safely extracted.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Extraction reads only a previously validated local file.
		$contents = file_get_contents( $file->path );
		if ( false === $contents || str_contains( $contents, "\0" ) || 1 !== preg_match( '//u', $contents ) ) {
			throw new ExtractionException( 'Unable to extract HTML document.' );
		}

		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		try {
			$loaded = $document->loadHTML( $contents, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}

		if ( ! $loaded || $document->getElementsByTagName( '*' )->length > self::MAX_NODES ) {
			throw new ExtractionException( 'Unable to extract HTML document.' );
		}

		$xpath   = new DOMXPath( $document );
		$ignored = $xpath->query( '//script|//style|//comment()' );
		if ( false === $ignored ) {
			throw new ExtractionException( 'Unable to extract HTML document.' );
		}
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM extension exposes camelCase properties.
		foreach ( $ignored as $node ) {
			if ( $node instanceof DOMNode && null !== $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$body = $document->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body instanceof DOMNode ) {
			throw new ExtractionException( 'HTML document contains no extractable text.' );
		}

		$lines   = array();
		$current = '';
		$this->collectVisibleText( $body, $lines, $current );
		$this->flushLine( $lines, $current );

		if ( array() === $lines ) {
			throw new ExtractionException( 'HTML document contains no extractable text.' );
		}

		return new ExtractedDocument( implode( "\n", $lines ), array( 'format' => 'html' ) );
	}

	/**
	 * Collect visible text while retaining deterministic block boundaries.
	 *
	 * @param DOMNode      $node Current DOM node.
	 * @param list<string> $lines Extracted lines.
	 * @param string       $current Current line buffer.
	 */
	private function collectVisibleText( DOMNode $node, array &$lines, string &$current ): void {
		if ( $node instanceof DOMText ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM extension exposes camelCase properties.
			$value = preg_replace( '/\s+/u', ' ', $node->nodeValue ?? '' );
			if ( is_string( $value ) ) {
				$current .= $value;
			}
			return;
		}

		if ( ! $node instanceof DOMElement ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM extension exposes camelCase properties.
		$tag       = strtolower( $node->tagName );
		$is_block  = in_array(
			$tag,
			array( 'body', 'article', 'aside', 'blockquote', 'div', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'li', 'main', 'p', 'pre', 'section', 'tr' ),
			true
		);
		$is_break  = 'br' === $tag;

		if ( $is_block || $is_break ) {
			$this->flushLine( $lines, $current );
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM extension exposes camelCase properties.
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMNode ) {
				$this->collectVisibleText( $child, $lines, $current );
			}
		}

		if ( $is_block ) {
			$this->flushLine( $lines, $current );
		}
	}

	/**
	 * Flush one normalized non-empty line.
	 *
	 * @param list<string> $lines Extracted lines.
	 * @param string       $current Current line buffer.
	 */
	private function flushLine( array &$lines, string &$current ): void {
		$line    = preg_replace( '/\s+/u', ' ', trim( $current ) );
		$current = '';
		if ( is_string( $line ) && '' !== $line ) {
			$lines[] = $line;
		}
	}
}
