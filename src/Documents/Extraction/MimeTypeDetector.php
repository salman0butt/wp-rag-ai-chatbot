<?php
/**
 * MIME type detector contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

/**
 * Detects a local file MIME type from server-observed bytes.
 */
interface MimeTypeDetector {
	/**
	 * Detect a local file MIME type.
	 *
	 * @param string $path Canonical local file path.
	 * @throws ExtractionException When MIME detection fails.
	 */
	public function detect( string $path ): string;
}
