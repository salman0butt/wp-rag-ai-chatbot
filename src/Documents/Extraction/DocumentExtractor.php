<?php
/**
 * Document extractor contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

/**
 * Extracts normalized text from one validated file.
 */
interface DocumentExtractor {
	/**
	 * Return MIME types owned by this extractor.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array;

	/**
	 * Extract one validated file.
	 *
	 * @param ValidatedFile $file Validated file.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument;
}
