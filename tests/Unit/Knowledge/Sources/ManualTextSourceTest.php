<?php
/**
 * Manual text knowledge source tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\Sources;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;
use WpRagAiChatbot\Knowledge\Sources\ManualTextSource;

/**
 * Verifies deterministic manual text normalization.
 */
final class ManualTextSourceTest extends TestCase {
	/**
	 * A persisted manual source yields one canonical document.
	 */
	public function test_normalizes_one_manual_document(): void {
		$this->requireSource();

		$source    = $this->source();
		$documents = iterator_to_array( ( new ManualTextSource() )->documents( $source ) );

		self::assertCount( 1, $documents );
		$document = $documents[0];

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain record public API intentionally follows the approved camelCase contract.
		self::assertSame( 7, $document->sourceId );
		self::assertSame( 'manual:manual-home', $document->documentKey );
		self::assertSame( 'manual-1', $document->externalId );
		self::assertSame( 'manual_text', $document->documentType );
		self::assertSame( 'About us', $document->title );
		self::assertNull( $document->canonicalUrl );
		self::assertSame( 'We build useful software.', $document->content );
		self::assertSame( array( 'source_type' => 'manual_text' ), $document->metadata );
		self::assertSame( 'source-version-1', $document->sourceVersion );
		self::assertSame( 'en', $document->language );
		self::assertSame( 'private', $document->visibility );
		self::assertSame( $source->updatedAt, $document->createdAt );
		self::assertSame( $source->updatedAt, $document->updatedAt );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $document->contentHash );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Equivalent source records produce the same content hash.
	 */
	public function test_hash_is_stable_for_equivalent_source_content(): void {
		$this->requireSource();

		$normalizer = new ManualTextSource();
		$first      = iterator_to_array( $normalizer->documents( $this->source() ) )[0];
		$second     = iterator_to_array( $normalizer->documents( $this->source() ) )[0];

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain record public API intentionally follows the approved camelCase contract.
		self::assertSame( $first->contentHash, $second->contentHash );
	}

	/**
	 * Blank manual text must fail closed.
	 */
	public function test_rejects_blank_text(): void {
		$this->requireSource();

		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array(
			( new ManualTextSource() )->documents(
				$this->source( config: array( 'text' => '   ' ) )
			)
		);
	}

	/**
	 * Visibility is restricted to the supported access values.
	 */
	public function test_rejects_invalid_visibility(): void {
		$this->requireSource();

		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array(
			( new ManualTextSource() )->documents(
				$this->source(
					config: array(
						'text'       => 'Content',
						'visibility' => 'members-only',
					)
				)
			)
		);
	}

	/**
	 * Documents can only reference a persisted source ID.
	 */
	public function test_rejects_unpersisted_source(): void {
		$this->requireSource();

		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array( ( new ManualTextSource() )->documents( $this->source( id: null ) ) );
	}

	/**
	 * A source record cannot be normalized by a mismatched implementation.
	 */
	public function test_rejects_mismatched_source_type(): void {
		$this->requireSource();

		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array( ( new ManualTextSource() )->documents( $this->source( source_type: 'faq' ) ) );
	}

	/**
	 * Build a source record with selected overrides.
	 *
	 * @param int|null             $id Persisted identifier.
	 * @param string               $source_type Source type.
	 * @param array<string, mixed> $config Source configuration.
	 */
	private function source(
		?int $id = 7,
		string $source_type = 'manual_text',
		array $config = array(
			'text'       => 'We build useful software.',
			'title'      => 'About us',
			'language'   => 'en',
			'visibility' => 'private',
		)
	): KnowledgeSourceRecord {
		$created_at = new DateTimeImmutable( '2026-09-01 10:00:00', new DateTimeZone( 'UTC' ) );
		$updated_at = new DateTimeImmutable( '2026-09-02 11:00:00', new DateTimeZone( 'UTC' ) );

		return new KnowledgeSourceRecord(
			$id,
			'manual-home',
			$source_type,
			'manual-1',
			'Manual source',
			null,
			'active',
			$config,
			'source-version-1',
			null,
			$created_at,
			$updated_at
		);
	}

	/**
	 * Fail as a PHPUnit assertion while the test-first production type does not exist.
	 */
	private function requireSource(): void {
		if ( ! class_exists( ManualTextSource::class ) ) {
			self::fail( 'ManualTextSource class does not exist yet.' );
		}
	}
}
