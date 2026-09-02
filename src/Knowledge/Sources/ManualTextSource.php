<?php
/**
 * Manual text knowledge source.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\Sources;

use JsonException;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

/**
 * Normalizes one configured manual-text source into a canonical document.
 */
final class ManualTextSource implements KnowledgeSource {
	/**
	 * Return the stable source type.
	 */
	public function type(): string {
		return 'manual_text';
	}

	/**
	 * Normalize manual source configuration into one canonical document.
	 *
	 * @param KnowledgeSourceRecord $source Persisted manual source.
	 * @return iterable<int, DocumentRecord>
	 * @throws KnowledgeSourceException When the source/configuration is invalid.
	 * @throws JsonException When deterministic hashing cannot encode the payload.
	 */
	public function documents( KnowledgeSourceRecord $source ): iterable {
		if ( $this->type() !== $source->sourceType ) {
			throw new KnowledgeSourceException( 'Manual text source type does not match.' );
		}
		if ( null === $source->id || $source->id < 1 ) {
			throw new KnowledgeSourceException( 'Manual text source must be persisted before normalization.' );
		}

		$text = $source->config['text'] ?? null;
		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			throw new KnowledgeSourceException( 'Manual text source requires non-empty text.' );
		}
		$text = trim( $text );

		$configured_title = $source->config['title'] ?? null;
		$title            = is_string( $configured_title ) && '' !== trim( $configured_title )
			? trim( $configured_title )
			: $source->title;

		$configured_visibility = $source->config['visibility'] ?? 'public';
		if ( ! is_string( $configured_visibility ) || ! in_array( $configured_visibility, array( 'public', 'private' ), true ) ) {
			throw new KnowledgeSourceException( 'Manual text source visibility is invalid.' );
		}
		$visibility = $configured_visibility;

		$configured_language = $source->config['language'] ?? null;
		$language            = is_string( $configured_language ) && '' !== trim( $configured_language )
			? trim( $configured_language )
			: null;

		$document_key   = 'manual:' . $source->sourceKey;
		$metadata       = array( 'source_type' => $this->type() );
		$source_version = $source->sourceHash ?? DocumentHasher::hash(
			array(
				'source_key' => $source->sourceKey,
				'config'     => $source->config,
			)
		);
		$content_hash   = DocumentHasher::hash(
			array(
				'document_key'   => $document_key,
				'external_id'    => $source->externalId,
				'document_type'  => $this->type(),
				'title'          => $title,
				'canonical_url'  => null,
				'content'        => $text,
				'metadata'       => $metadata,
				'source_version' => $source_version,
				'language'       => $language,
				'visibility'     => $visibility,
			)
		);

		yield new DocumentRecord(
			null,
			$document_key,
			$source->id,
			$source->externalId,
			$this->type(),
			$title,
			null,
			$text,
			$metadata,
			$source_version,
			$content_hash,
			$language,
			$visibility,
			$source->updatedAt,
			$source->updatedAt
		);
	}
}
