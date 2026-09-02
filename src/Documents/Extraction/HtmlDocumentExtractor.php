<?php
/**
 * HTML document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use DOMDocument;
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
			if ( null !== $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$nodes = $xpath->query( '//body//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::p or self::li or self::tr]' );
		if ( false === $nodes ) {
			throw new ExtractionException( 'Unable to extract HTML document.' );
		}

		$lines = array();
		foreach ( $nodes as $node ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM extension exposes camelCase properties.
			$text = preg_replace( '/\s+/u', ' ', trim( $node->textContent ) );
			if ( is_string( $text ) && '' !== $text ) {
				$lines[] = $text;
			}
		}

		if ( array() === $lines ) {
			throw new ExtractionException( 'HTML document contains no extractable text.' );
		}

		return new ExtractedDocument( implode( "\n", $lines ), array( 'format' => 'html' ) );
	}
}
