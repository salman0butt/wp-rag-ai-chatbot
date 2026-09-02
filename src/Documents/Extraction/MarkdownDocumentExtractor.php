<?php
/**
 * Markdown document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

/**
 * Preserves readable Markdown structure while normalizing text bytes.
 */
final class MarkdownDocumentExtractor implements DocumentExtractor {
	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Implements the approved extractor contract.
	/**
	 * Return owned MIME types.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array {
		return array( 'text/markdown' );
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

	/**
	 * Extract normalized Markdown text.
	 *
	 * @param ValidatedFile $file Validated local file.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Extraction reads only a previously validated local file.
		$contents = file_get_contents( $file->path );
		if ( false === $contents || str_contains( $contents, "\0" ) || 1 !== preg_match( '//u', $contents ) ) {
			throw new ExtractionException( 'Unable to extract Markdown document.' );
		}

		$text = trim( str_replace( array( "\r\n", "\r" ), "\n", $contents ) );
		if ( '' === $text ) {
			throw new ExtractionException( 'Markdown document contains no extractable text.' );
		}

		return new ExtractedDocument( $text, array( 'format' => 'markdown' ) );
	}
}
