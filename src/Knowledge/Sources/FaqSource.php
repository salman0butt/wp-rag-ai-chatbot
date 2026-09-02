<?php
/**
 * FAQ knowledge source.
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
 * Normalizes configured FAQ items into canonical documents.
 */
final class FaqSource implements KnowledgeSource {
	/**
	 * Return the stable source type.
	 */
	public function type(): string {
		return 'faq';
	}

	/**
	 * Normalize configured FAQ items into canonical documents.
	 *
	 * @param KnowledgeSourceRecord $source Persisted FAQ source.
	 * @return iterable<int, DocumentRecord>
	 * @throws KnowledgeSourceException When the source/configuration is invalid.
	 */
	public function documents( KnowledgeSourceRecord $source ): iterable {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- M02 domain record public properties intentionally use the approved camelCase contract.
		if ( $this->type() !== $source->sourceType ) {
			throw new KnowledgeSourceException( 'FAQ source type does not match.' );
		}
		if ( null === $source->id || $source->id < 1 ) {
			throw new KnowledgeSourceException( 'FAQ source must be persisted before normalization.' );
		}

		$items = $source->config['items'] ?? null;
		if ( ! is_array( $items ) || array() === $items || ! array_is_list( $items ) ) {
			throw new KnowledgeSourceException( 'FAQ source requires a non-empty list of items.' );
		}

		$configured_visibility = $source->config['visibility'] ?? 'public';
		if ( ! is_string( $configured_visibility ) || ! in_array( $configured_visibility, array( 'public', 'private' ), true ) ) {
			throw new KnowledgeSourceException( 'FAQ source visibility is invalid.' );
		}
		$visibility = $configured_visibility;

		$configured_language = $source->config['language'] ?? null;
		$language            = is_string( $configured_language ) && '' !== trim( $configured_language )
			? trim( $configured_language )
			: null;

		try {
			$source_version = $source->sourceHash ?? DocumentHasher::hash(
				array(
					'source_key' => $source->sourceKey,
					'config'     => $source->config,
				)
			);

			foreach ( $items as $index => $item ) {
				if ( ! is_array( $item ) ) {
					throw new KnowledgeSourceException( 'FAQ source contains an invalid item.' );
				}

				$question = $item['question'] ?? null;
				$answer   = $item['answer'] ?? null;
				if ( ! is_string( $question ) || '' === trim( $question ) || ! is_string( $answer ) || '' === trim( $answer ) ) {
					throw new KnowledgeSourceException( 'FAQ source items require non-empty question and answer values.' );
				}

				$question     = trim( $question );
				$answer       = trim( $answer );
				$document_key = 'faq:' . $source->sourceKey . ':' . $index;
				$content      = "Question: {$question}\nAnswer: {$answer}";
				$metadata     = array(
					'source_type' => $this->type(),
					'item_index'  => $index,
				);
				$content_hash = DocumentHasher::hash(
					array(
						'document_key'   => $document_key,
						'external_id'    => $source->externalId,
						'document_type'  => $this->type(),
						'title'          => $question,
						'canonical_url'  => null,
						'content'        => $content,
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
					$question,
					null,
					$content,
					$metadata,
					$source_version,
					$content_hash,
					$language,
					$visibility,
					$source->updatedAt,
					$source->updatedAt
				);
			}
		} catch ( JsonException $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- This value is the previous Throwable, not rendered output.
			throw new KnowledgeSourceException( 'FAQ source could not be hashed.', 0, $exception );
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
}
