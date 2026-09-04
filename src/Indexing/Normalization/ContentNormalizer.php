<?php
/**
 * Deterministic canonical content normalization.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Normalization;

/**
 * Applies meaning-preserving whitespace normalization to canonical document text.
 */
final class ContentNormalizer {
	/**
	 * Normalize canonical document content without interpreting untrusted text.
	 *
	 * @param string $content Canonical document content.
	 */
	public static function normalize( string $content ): string {
		if ( str_starts_with( $content, "\xEF\xBB\xBF" ) ) {
			$content = substr( $content, 3 );
		}

		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
		$lines   = explode( "\n", $content );
		$lines   = array_map(
			static fn ( string $line ): string => rtrim( $line, " \t" ),
			$lines
		);
		$content = implode( "\n", $lines );

		$collapsed = preg_replace( '/\n{3,}/', "\n\n", $content );
		if ( null !== $collapsed ) {
			$content = $collapsed;
		}

		return trim( $content, " \t\n" );
	}
}
