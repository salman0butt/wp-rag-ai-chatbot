<?php
/**
 * Document persistence record.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents;

use DateTimeImmutable;
use InvalidArgumentException;

// phpcs:disable WordPress.NamingConventions -- Public record property names follow the approved domain contract.
/**
 * Immutable representation of one normalized document.
 */
final readonly class DocumentRecord {
	/**
	 * Create a document record.
	 *
	 * @param int|null               $id Persisted identifier, or null before insert.
	 * @param string                 $documentKey Stable document key.
	 * @param int                    $sourceId Owning knowledge-source identifier.
	 * @param string|null            $externalId External document identifier.
	 * @param string                 $documentType Document type.
	 * @param string                 $title Document title.
	 * @param string|null            $canonicalUrl Canonical URL.
	 * @param string                 $content Normalized document content.
	 * @param array<string, mixed>   $metadata Document metadata.
	 * @param string|null            $sourceVersion Upstream version marker.
	 * @param string                 $contentHash Lowercase SHA-256 content hash.
	 * @param string|null            $language Language code when known.
	 * @param string                 $visibility Access visibility.
	 * @param DateTimeImmutable      $createdAt Creation time.
	 * @param DateTimeImmutable      $updatedAt Last update time.
	 */
	public function __construct(
		public ?int $id,
		public string $documentKey,
		public int $sourceId,
		public ?string $externalId,
		public string $documentType,
		public string $title,
		public ?string $canonicalUrl,
		public string $content,
		public array $metadata,
		public ?string $sourceVersion,
		public string $contentHash,
		public ?string $language,
		public string $visibility,
		public DateTimeImmutable $createdAt,
		public DateTimeImmutable $updatedAt
	) {
		if ( $sourceId < 1 ) {
			throw new InvalidArgumentException( 'Source ID must be at least 1.' );
		}
		if ( '' === $documentKey ) {
			throw new InvalidArgumentException( 'Document key must not be empty.' );
		}
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $contentHash ) ) {
			throw new InvalidArgumentException( 'Content hash must be a lowercase SHA-256 hexadecimal value.' );
		}
	}

	/**
	 * Return the same record with its persisted identifier.
	 *
	 * @param int $id Persisted identifier.
	 */
	public function withId( int $id ): self {
		return new self(
			$id,
			$this->documentKey,
			$this->sourceId,
			$this->externalId,
			$this->documentType,
			$this->title,
			$this->canonicalUrl,
			$this->content,
			$this->metadata,
			$this->sourceVersion,
			$this->contentHash,
			$this->language,
			$this->visibility,
			$this->createdAt,
			$this->updatedAt
		);
	}
}
// phpcs:enable WordPress.NamingConventions
