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
	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Public domain contract uses camelCase consistently with existing records/contracts.
	/**
	 * Return MIME types owned by this extractor.
	 *
	 * @return list<string>
	 */
	public function supportedMimeTypes(): array;
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

	/**
	 * Extract one validated file.
	 *
	 * @param ValidatedFile $file Validated file.
	 */
	public function extract( ValidatedFile $file ): ExtractedDocument;
}
