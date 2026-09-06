<?php
/**
 * Citation validator.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Citations;

/**
 * Validates model-authored citation markers against request-local trusted lineage.
 */
final class CitationValidator {
	/**
	 * Validate bracketed citation markers in an answer.
	 *
	 * @param string           $answer Model-authored answer text.
	 * @param CitationRegistry $registry Request-local trusted citation registry.
	 */
	public function validate( string $answer, CitationRegistry $registry ): CitationValidationResult {
		preg_match_all( '/\[(C[0-9]+)\]/', $answer, $matches );

		$citations       = array();
		$invalid_markers = array();
		$seen            = array();

		foreach ( $matches[1] as $marker ) {
			if ( 1 !== preg_match( '/^C[1-9][0-9]*$/', $marker ) ) {
				$invalid_markers[] = $marker;
				continue;
			}

			if ( isset( $seen[ $marker ] ) ) {
				$invalid_markers[] = $marker;
				continue;
			}
			$seen[ $marker ] = true;

			$citation = $registry->get( $marker );
			if ( null === $citation ) {
				$invalid_markers[] = $marker;
				continue;
			}

			$citations[] = $citation;
		}

		return new CitationValidationResult(
			array() === $invalid_markers,
			$citations,
			$invalid_markers
		);
	}
}
