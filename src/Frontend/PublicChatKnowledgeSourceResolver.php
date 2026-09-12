<?php
/**
 * Public chat knowledge-source resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use RuntimeException;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

/**
 * Resolves the exact persisted knowledge source selected by trusted bot runtime authority.
 */
final readonly class PublicChatKnowledgeSourceResolver {
	/**
	 * Create one resolver.
	 *
	 * @param KnowledgeSourceRepository $sources Persisted knowledge-source authority.
	 */
	public function __construct( private KnowledgeSourceRepository $sources ) {
	}

	/**
	 * Resolve the source bound to the trusted public chat runtime.
	 *
	 * @param PublicChatRuntime $runtime Trusted persisted runtime authority.
	 * @throws RuntimeException When the bound source is unavailable.
	 */
	public function resolve( PublicChatRuntime $runtime ): KnowledgeSourceRecord {
		$source = $this->sources->findById( $runtime->retrieval->source_id );
		if ( null === $source || $runtime->retrieval->source_id !== $source->id ) {
			throw new RuntimeException( 'Public chat knowledge source is unavailable.' );
		}

		return $source;
	}
}
