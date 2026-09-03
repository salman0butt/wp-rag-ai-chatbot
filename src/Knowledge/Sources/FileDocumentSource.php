<?php
/**
 * File document knowledge source.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\Sources;

use JsonException;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Documents\Extraction\DocumentExtractorRegistry;
use WpRagAiChatbot\Documents\Extraction\FileValidationPolicy;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

/**
 * Validates and extracts one persisted local file into a canonical document.
 */
final readonly class FileDocumentSource implements KnowledgeSource {
	/**
	 * Create the file source.
	 *
	 * @param FileValidationPolicy      $validation_policy File trust-boundary policy.
	 * @param DocumentExtractorRegistry $extractor_registry MIME extractor registry.
	 */
	public function __construct(
		private FileValidationPolicy $validation_policy,
		private DocumentExtractorRegistry $extractor_registry
	) {
	}

	/**
	 * Return the stable source type.
	 */
	public function type(): string {
		return 'file';
	}

	/**
	 * Normalize one persisted file source into one canonical document.
	 *
	 * @param KnowledgeSourceRecord $source Persisted file source.
	 * @return iterable<int, DocumentRecord>
	 * @throws KnowledgeSourceException When source configuration or hashing is invalid.
	 */
	public function documents( KnowledgeSourceRecord $source ): iterable {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain records intentionally expose approved camelCase properties.
		if ( $this->type() !== $source->sourceType ) {
			throw new KnowledgeSourceException( 'File source type does not match.' );
		}
		if ( null === $source->id || $source->id < 1 ) {
			throw new KnowledgeSourceException( 'File source must be persisted before normalization.' );
		}

		$path = $source->config['path'] ?? null;
		if ( ! is_string( $path ) || '' === trim( $path ) ) {
			throw new KnowledgeSourceException( 'File source requires a local path.' );
		}

		$allowed_root = $source->config['allowed_root'] ?? null;
		if ( null !== $allowed_root && ( ! is_string( $allowed_root ) || '' === trim( $allowed_root ) ) ) {
			throw new KnowledgeSourceException( 'File source allowed root is invalid.' );
		}

		$configured_visibility = $source->config['visibility'] ?? 'public';
		if ( ! is_string( $configured_visibility ) || ! in_array( $configured_visibility, array( 'public', 'private' ), true ) ) {
			throw new KnowledgeSourceException( 'File source visibility is invalid.' );
		}
		$visibility = $configured_visibility;

		$configured_language = $source->config['language'] ?? null;
		if ( null !== $configured_language && ! is_string( $configured_language ) ) {
			throw new KnowledgeSourceException( 'File source language is invalid.' );
		}
		$language = is_string( $configured_language ) && '' !== trim( $configured_language )
			? trim( $configured_language )
			: null;

		$configured_title = $source->config['title'] ?? null;
		$title            = is_string( $configured_title ) && '' !== trim( $configured_title )
			? trim( $configured_title )
			: $source->title;

		$validated = $this->validation_policy->validate( trim( $path ), $allowed_root );
		$extracted = $this->extractor_registry->get( $validated->mimeType )->extract( $validated );

		$document_key  = 'file:' . $source->sourceKey;
		$source_version = $validated->sha256 . ':' . $validated->size;
		$metadata       = array_merge(
			$extracted->metadata,
			array(
				'source_type' => $this->type(),
				'filename'    => $validated->basename,
				'extension'   => $validated->extension,
				'mime_type'   => $validated->mimeType,
				'size'        => $validated->size,
				'file_sha256' => $validated->sha256,
			)
		);

		try {
			$content_hash = DocumentHasher::hash(
				array(
					'document_key'   => $document_key,
					'external_id'    => $source->externalId,
					'document_type'  => $this->type(),
					'title'          => $title,
					'canonical_url'  => $source->canonicalUrl,
					'content'        => $extracted->text,
					'metadata'       => $metadata,
					'source_version' => $source_version,
					'language'       => $language,
					'visibility'     => $visibility,
				)
			);
		} catch ( JsonException $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Previous Throwable is not rendered output.
			throw new KnowledgeSourceException( 'File source could not be hashed.', 0, $exception );
		}

		yield new DocumentRecord(
			null,
			$document_key,
			$source->id,
			$source->externalId,
			$this->type(),
			$title,
			$source->canonicalUrl,
			$extracted->text,
			$metadata,
			$source_version,
			$content_hash,
			$language,
			$visibility,
			$source->updatedAt,
			$source->updatedAt
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
}
