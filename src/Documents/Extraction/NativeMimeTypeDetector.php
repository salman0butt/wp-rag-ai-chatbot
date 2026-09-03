<?php
/**
 * Native MIME type detector.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use finfo;

/**
 * Detects MIME types with PHP fileinfo.
 */
final class NativeMimeTypeDetector implements MimeTypeDetector {
	/**
	 * Detect a local file MIME type.
	 *
	 * @param string $path Canonical local file path.
	 * @throws ExtractionException When MIME detection fails.
	 */
	public function detect( string $path ): string {
		$detector  = new finfo( FILEINFO_MIME_TYPE );
		$mime_type = $detector->file( $path );

		if ( false === $mime_type || '' === trim( $mime_type ) ) {
			throw new ExtractionException( 'Unable to detect file MIME type.' );
		}

		return strtolower( trim( $mime_type ) );
	}
}
