<?php
/**
 * Persisted Playground retrieval configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

// phpcs:disable WordPress.NamingConventions -- Public property name follows the existing persisted collection contract.
/**
 * Carries the exact persisted source and vector collection selected for one Playground request.
 */
final readonly class PlaygroundRetrievalConfiguration {
	/**
	 * Create one explicit persisted retrieval selection.
	 *
	 * @param KnowledgeSourceRecord $source Persisted knowledge source.
	 * @param string                $collection_id Persisted vector collection key.
	 */
	public function __construct(
		public KnowledgeSourceRecord $source,
		public string $collection_id
	) {
	}
}
// phpcs:enable WordPress.NamingConventions
