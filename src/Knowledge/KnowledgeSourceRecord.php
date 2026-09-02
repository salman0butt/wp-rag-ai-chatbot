<?php
/**
 * Knowledge source persistence record.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge;

use DateTimeImmutable;

// phpcs:disable WordPress.NamingConventions -- Public record property names follow the approved domain contract.
/**
 * Immutable representation of one persisted knowledge source.
 */
final readonly class KnowledgeSourceRecord {
	/**
	 * Create a source record.
	 *
	 * @param int|null               $id Persisted identifier, or null before insert.
	 * @param string                 $sourceKey Stable source key.
	 * @param string                 $sourceType Source type.
	 * @param string|null            $externalId External source identifier.
	 * @param string                 $title Source title.
	 * @param string|null            $canonicalUrl Canonical URL.
	 * @param string                 $status Source status.
	 * @param array<string, mixed>   $config Source configuration.
	 * @param string|null            $sourceHash Source content hash.
	 * @param DateTimeImmutable|null $lastSyncedAt Last successful synchronization time.
	 * @param DateTimeImmutable      $createdAt Creation time.
	 * @param DateTimeImmutable      $updatedAt Last update time.
	 */
	public function __construct(
		public ?int $id,
		public string $sourceKey,
		public string $sourceType,
		public ?string $externalId,
		public string $title,
		public ?string $canonicalUrl,
		public string $status,
		public array $config,
		public ?string $sourceHash,
		public ?DateTimeImmutable $lastSyncedAt,
		public DateTimeImmutable $createdAt,
		public DateTimeImmutable $updatedAt
	) {
	}

	/**
	 * Return the same record with its persisted identifier.
	 *
	 * @param int $id Persisted identifier.
	 */
	public function withId( int $id ): self {
		return new self(
			$id,
			$this->sourceKey,
			$this->sourceType,
			$this->externalId,
			$this->title,
			$this->canonicalUrl,
			$this->status,
			$this->config,
			$this->sourceHash,
			$this->lastSyncedAt,
			$this->createdAt,
			$this->updatedAt
		);
	}
}
// phpcs:enable WordPress.NamingConventions
