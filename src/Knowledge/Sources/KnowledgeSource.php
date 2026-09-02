<?php
/**
 * Knowledge source contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\Sources;

use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

/**
 * Normalizes one supported knowledge source into canonical documents.
 */
interface KnowledgeSource {
	/**
	 * Return the stable source type identifier.
	 */
	public function type(): string;

	/**
	 * Normalize a persisted source record into canonical documents.
	 *
	 * @param KnowledgeSourceRecord $source Persisted source configuration.
	 * @return iterable<int, DocumentRecord>
	 */
	public function documents( KnowledgeSourceRecord $source ): iterable;
}
