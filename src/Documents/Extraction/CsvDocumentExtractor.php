<?php
/**
 * CSV document extractor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

/**
 * Extracts bounded tabular text from validated CSV files.
 */
final class CsvDocumentExtractor implements DocumentExtractor {
	private const MAX_ROWS    = 1000;
	private const MAX_COLUMNS = 100;

	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Implements the approved extractor contract.
	/**
	 * Return owned MIME types.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array {
		return array( 'text/csv' );
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

	/**
	 * Extract deterministic tabular text.
	 *
	 * @param ValidatedFile $file Validated local file.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument {
		// phpcs:disable WordPress.WP.AlternativeFunctions -- Extraction reads only a previously validated local file and needs streaming CSV parsing.
		$handle = fopen( $file->path, 'rb' );
		if ( false === $handle ) {
			throw new ExtractionException( 'Unable to extract CSV document.' );
		}

		$lines       = array();
		$row_count   = 0;
		$column_count = 0;
		try {
			while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) {
				++$row_count;
				if ( $row_count > self::MAX_ROWS || count( $row ) > self::MAX_COLUMNS ) {
					throw new ExtractionException( 'CSV document exceeds extraction limits.' );
				}

				$column_count = max( $column_count, count( $row ) );
				$cells        = array();
				foreach ( $row as $cell ) {
					$value = null === $cell ? '' : $cell;
					if ( str_contains( $value, "\0" ) || 1 !== preg_match( '//u', $value ) ) {
						throw new ExtractionException( 'Unable to extract CSV document.' );
					}
					$normalized = preg_replace( '/\s+/u', ' ', trim( str_replace( array( "\r\n", "\r", "\n", "\t" ), ' ', $value ) ) );
					$cells[]    = is_string( $normalized ) ? $normalized : '';
				}
				$lines[] = implode( "\t", $cells );
			}
		} finally {
			fclose( $handle );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions

		$text = trim( implode( "\n", $lines ) );
		if ( '' === $text ) {
			throw new ExtractionException( 'CSV document contains no extractable text.' );
		}

		return new ExtractedDocument(
			$text,
			array(
				'format'  => 'csv',
				'rows'    => $row_count,
				'columns' => $column_count,
			)
		);
	}
}
