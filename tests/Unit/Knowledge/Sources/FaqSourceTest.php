<?php
/**
 * FAQ knowledge source tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\Sources;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\FaqSource;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;

/**
 * Verifies deterministic FAQ normalization.
 */
final class FaqSourceTest extends TestCase {
	public function test_normalizes_multiple_faq_documents_deterministically(): void {
		$this->requireSource();
		$source = $this->source();
		$normalizer = new FaqSource();
		$first = iterator_to_array( $normalizer->documents( $source ) );
		$second = iterator_to_array( $normalizer->documents( $source ) );

		self::assertCount( 2, $first );
		self::assertCount( 2, $second );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain record public API intentionally follows the approved camelCase contract.
		self::assertSame( 'faq:support-faq:0', $first[0]->documentKey );
		self::assertSame( 'faq:support-faq:1', $first[1]->documentKey );
		self::assertSame( 'Question: What is RAG?\nAnswer: Retrieval augmented generation.', $first[0]->content );
		self::assertSame( 'What is RAG?', $first[0]->title );
		self::assertSame( 'faq', $first[0]->documentType );
		self::assertSame( 9, $first[0]->sourceId );
		self::assertSame( 'en', $first[0]->language );
		self::assertSame( 'private', $first[0]->visibility );
		self::assertSame( array( 'source_type' => 'faq', 'item_index' => 0 ), $first[0]->metadata );
		self::assertSame( 'faq-source-v1', $first[0]->sourceVersion );
		self::assertSame( $source->updatedAt, $first[0]->createdAt );
		self::assertSame( $source->updatedAt, $first[0]->updatedAt );
		self::assertSame( $first[0]->contentHash, $second[0]->contentHash );
		self::assertSame( $first[1]->contentHash, $second[1]->contentHash );
		self::assertNotSame( $first[0]->contentHash, $first[1]->contentHash );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	public function test_rejects_malformed_item(): void {
		$this->requireSource();
		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array( ( new FaqSource() )->documents( $this->source( config: array( 'items' => array( array( 'question' => 'Missing answer' ) ) ) ) ) );
	}

	public function test_rejects_invalid_visibility(): void {
		$this->requireSource();
		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array( ( new FaqSource() )->documents( $this->source( config: array( 'items' => array( array( 'question' => 'Q', 'answer' => 'A' ) ), 'visibility' => 'members-only' ) ) ) );
	}

	public function test_rejects_unpersisted_source(): void {
		$this->requireSource();
		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array( ( new FaqSource() )->documents( $this->source( id: null ) ) );
	}

	public function test_rejects_empty_items_list(): void {
		$this->requireSource();
		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array( ( new FaqSource() )->documents( $this->source( config: array( 'items' => array() ) ) ) );
	}

	/**
	 * @param int|null             $id Persisted identifier.
	 * @param array<string, mixed> $config Source configuration.
	 */
	private function source(
		?int $id = 9,
		array $config = array(
			'items' => array(
				array( 'question' => 'What is RAG?', 'answer' => 'Retrieval augmented generation.' ),
				array( 'question' => 'Does it support WordPress?', 'answer' => 'Yes.' ),
			),
			'language' => 'en',
			'visibility' => 'private',
		)
	): KnowledgeSourceRecord {
		$created_at = new DateTimeImmutable( '2026-09-01 10:00:00', new DateTimeZone( 'UTC' ) );
		$updated_at = new DateTimeImmutable( '2026-09-02 11:00:00', new DateTimeZone( 'UTC' ) );
		return new KnowledgeSourceRecord(
			$id,
			'support-faq',
			'faq',
			'faq-1',
			'Support FAQ',
			null,
			'active',
			$config,
			'faq-source-v1',
			null,
			$created_at,
			$updated_at
		);
	}

	private function requireSource(): void {
		if ( ! class_exists( FaqSource::class ) ) {
			self::fail( 'FaqSource class does not exist yet.' );
		}
	}
}
