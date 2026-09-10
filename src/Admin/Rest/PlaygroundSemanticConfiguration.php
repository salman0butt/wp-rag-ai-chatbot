<?php
/**
 * Persisted Playground semantic runtime identity.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Embeddings\EmbeddingProfile;

// phpcs:disable WordPress.NamingConventions -- Public property name follows the existing provider/vector-store contracts.
/**
 * Carries the persisted embedding identity and vector-store selector required for semantic retrieval composition.
 */
final readonly class PlaygroundSemanticConfiguration {
	/**
	 * Create one persisted semantic retrieval configuration.
	 *
	 * @param EmbeddingProfile $embedding_profile Persisted embedding compatibility profile.
	 * @param string           $vector_store_id Persisted vector-store provider identifier.
	 */
	public function __construct(
		public EmbeddingProfile $embedding_profile,
		public string $vector_store_id
	) {
	}
}
// phpcs:enable WordPress.NamingConventions
