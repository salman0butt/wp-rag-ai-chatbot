<?php
/**
 * Document record tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Documents;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\DocumentRecord;

/**
 * Verifies document record invariants.
 */
final class DocumentRecordTest extends TestCase {
	/**
	 * Source IDs must refer to persisted sources.
	 */
	public function test_rejects_source_id_below_one(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->record( source_id: 0 );
	}

	/**
	 * Document keys are required.
	 */
	public function test_rejects_empty_document_key(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->record( document_key: '' );
	}

	/**
	 * Content hashes must be lowercase SHA-256 hex.
	 */
	public function test_rejects_invalid_content_hash(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->record( content_hash: 'not-a-sha256-hash' );
	}

	/**
	 * WithId returns the same record data with a persisted identifier.
	 */
	public function test_with_id_preserves_record_data_and_sets_id(): void {
		$record = $this->record();
		$saved  = $record->withId( 42 );

		self::assertSame( 42, $saved->id );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DocumentRecord public API is camelCase by approved contract.
		self::assertSame( $record->documentKey, $saved->documentKey );
		self::assertSame( $record->sourceId, $saved->sourceId );
		self::assertSame( $record->metadata, $saved->metadata );
		self::assertSame( $record->contentHash, $saved->contentHash );
		self::assertSame( $record->createdAt, $saved->createdAt );
		self::assertSame( $record->updatedAt, $saved->updatedAt );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Build a valid record with selected overrides.
	 *
	 * @param string $document_key Document key override.
	 * @param int    $source_id Source ID override.
	 * @param string $content_hash Content-hash override.
	 */
	private function record(
		string $document_key = 'document-1',
		int $source_id = 1,
		string $content_hash = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
	): DocumentRecord {
		$timestamp = new DateTimeImmutable( '2026-09-02 00:00:00', new DateTimeZone( 'UTC' ) );

		return new DocumentRecord(
			null,
			$document_key,
			$source_id,
			'ext-1',
			'page',
			'Document 1',
			'https://example.test/document/1',
			'Literal content',
			array( 'key' => 'value' ),
			'v1',
			$content_hash,
			'en',
			'public',
			$timestamp,
			$timestamp
		);
	}
}
