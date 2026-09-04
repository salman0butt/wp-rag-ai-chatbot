<?php
/**
 * Compatibility-safe chunk deduplication tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Indexing\Dedup;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Dedup\ChunkDeduplicator;
use WpRagAiChatbot\Indexing\Dedup\ChunkDeduplicationResult;

// phpcs:disable WordPress.NamingConventions -- Assertions intentionally use the approved camelCase domain contracts.
/**
 * Verifies deterministic deduplication and privacy/compatibility boundaries.
 */
final class ChunkDeduplicatorTest extends TestCase {
	/**
	 * Identical compatible content keeps the earliest chunk and aliases later duplicates.
	 */
	public function test_identical_compatible_content_is_deduplicated_to_earliest_chunk(): void {
		$this->requireDeduplicator();
		$first  = $this->chunk( 'first', 0, 'Alpha beta', 'en', 'public', 'embed-v1' );
		$second = $this->chunk( 'second', 1, 'Alpha beta', 'en', 'public', 'embed-v1' );

		$result = ( new ChunkDeduplicator() )->deduplicate( array( $first, $second ) );

		self::assertInstanceOf( ChunkDeduplicationResult::class, $result );
		self::assertSame( array( $first ), $result->canonicalChunks );
		self::assertSame( array( $second->chunkKey => $first->chunkKey ), $result->duplicateAliases );
	}

	/**
	 * Canonical selection follows deterministic chunk sequence, not caller array order.
	 */
	public function test_lowest_sequence_is_canonical_when_duplicate_input_order_is_reversed(): void {
		$this->requireDeduplicator();
		$earliest = $this->chunk( 'earliest', 2, 'Alpha beta', 'en', 'public', 'embed-v1' );
		$later    = $this->chunk( 'later', 9, 'Alpha beta', 'en', 'public', 'embed-v1' );

		$result = ( new ChunkDeduplicator() )->deduplicate( array( $later, $earliest ) );

		self::assertSame( array( $earliest ), $result->canonicalChunks );
		self::assertSame( array( $later->chunkKey => $earliest->chunkKey ), $result->duplicateAliases );
	}

	/**
	 * Canonical output ordering follows deterministic sequence, not fingerprint encounter order.
	 */
	public function test_canonical_chunks_are_ordered_by_sequence_when_distinct_groups_are_reversed(): void {
		$this->requireDeduplicator();
		$earlier = $this->chunk( 'earlier-group', 1, 'Alpha', 'en', 'public', 'embed-v1' );
		$later   = $this->chunk( 'later-group', 8, 'Beta', 'en', 'public', 'embed-v1' );

		$result = ( new ChunkDeduplicator() )->deduplicate( array( $later, $earlier ) );

		self::assertSame( array( $earlier, $later ), $result->canonicalChunks );
		self::assertSame( array(), $result->duplicateAliases );
	}

	/**
	 * Duplicate aliases have stable key ordering regardless of caller encounter order.
	 */
	public function test_duplicate_aliases_are_ordered_deterministically(): void {
		$this->requireDeduplicator();
		$canonical  = $this->chunk( 'canonical', 0, 'Same', 'en', 'public', 'embed-v1' );
		$first_dup  = $this->chunk( 'first-duplicate', 1, 'Same', 'en', 'public', 'embed-v1' );
		$next_dup   = $this->chunk( 'next-duplicate', 2, 'Same', 'en', 'public', 'embed-v1' );
		$duplicates = array( $first_dup, $next_dup );
		usort(
			$duplicates,
			static fn ( ChunkRecord $left, ChunkRecord $right ): int => strcmp( $right->chunkKey, $left->chunkKey )
		);

		$result   = ( new ChunkDeduplicator() )->deduplicate( array( $duplicates[0], $duplicates[1], $canonical ) );
		$expected = array(
			$first_dup->chunkKey => $canonical->chunkKey,
			$next_dup->chunkKey  => $canonical->chunkKey,
		);
		ksort( $expected, SORT_STRING );

		self::assertSame( $expected, $result->duplicateAliases );
	}

	/**
	 * Canonical content normalization prevents formatting-only duplicate embeddings.
	 */
	public function test_content_is_normalized_before_deduplication(): void {
		$this->requireDeduplicator();
		$first  = $this->chunk( 'first', 0, "Alpha  \r\n\r\n\r\nBeta", 'en', 'public', null );
		$second = $this->chunk( 'second', 1, "Alpha\n\nBeta", 'en', 'public', null );

		$result = ( new ChunkDeduplicator() )->deduplicate( array( $first, $second ) );

		self::assertSame( array( $first ), $result->canonicalChunks );
		self::assertSame( array( $second->chunkKey => $first->chunkKey ), $result->duplicateAliases );
	}

	/**
	 * Public and private content never shares a canonical chunk.
	 */
	public function test_visibility_is_a_deduplication_boundary(): void {
		$this->requireDeduplicator();
		$public  = $this->chunk( 'public', 0, 'Same', 'en', 'public', null );
		$private = $this->chunk( 'private', 1, 'Same', 'en', 'private', null );

		$result = ( new ChunkDeduplicator() )->deduplicate( array( $public, $private ) );

		self::assertSame( array( $public, $private ), $result->canonicalChunks );
		self::assertSame( array(), $result->duplicateAliases );
	}

	/**
	 * Language differences prevent cross-language deduplication.
	 */
	public function test_language_is_a_deduplication_boundary(): void {
		$this->requireDeduplicator();
		$english = $this->chunk( 'english', 0, 'Same', 'en', 'public', null );
		$german  = $this->chunk( 'german', 1, 'Same', 'de', 'public', null );

		$result = ( new ChunkDeduplicator() )->deduplicate( array( $english, $german ) );

		self::assertSame( array( $english, $german ), $result->canonicalChunks );
		self::assertSame( array(), $result->duplicateAliases );
	}

	/**
	 * Embedding compatibility changes force separate canonical chunks.
	 */
	public function test_embedding_compatibility_is_a_deduplication_boundary(): void {
		$this->requireDeduplicator();
		$v1 = $this->chunk( 'v1', 0, 'Same', 'en', 'public', 'embed-v1' );
		$v2 = $this->chunk( 'v2', 1, 'Same', 'en', 'public', 'embed-v2' );

		$result = ( new ChunkDeduplicator() )->deduplicate( array( $v1, $v2 ) );

		self::assertSame( array( $v1, $v2 ), $result->canonicalChunks );
		self::assertSame( array(), $result->duplicateAliases );
	}

	/**
	 * Deduplication does not reorder, replace, or mutate the caller's input records.
	 */
	public function test_input_records_are_not_mutated(): void {
		$this->requireDeduplicator();
		$first  = $this->chunk( 'first', 0, 'Same', null, 'public', null );
		$second = $this->chunk( 'second', 1, 'Same', null, 'public', null );
		$input  = array( $first, $second );
		$before = $input;

		( new ChunkDeduplicator() )->deduplicate( $input );

		self::assertSame( $before, $input );
		self::assertSame( $first, $input[0] );
		self::assertSame( $second, $input[1] );
	}

	/**
	 * Require the wished-for production API while preserving assertion-style RED.
	 */
	private function requireDeduplicator(): void {
		if ( ! class_exists( ChunkDeduplicator::class ) ) {
			self::fail( 'ChunkDeduplicator class does not exist yet.' );
		}
		if ( ! class_exists( ChunkDeduplicationResult::class ) ) {
			self::fail( 'ChunkDeduplicationResult class does not exist yet.' );
		}
	}

	/**
	 * Build one valid immutable chunk fixture.
	 *
	 * @param string      $id Stable fixture identifier.
	 * @param int         $sequence Deterministic sequence.
	 * @param string      $content Chunk content.
	 * @param string|null $language Language boundary.
	 * @param string      $visibility Visibility boundary.
	 * @param string|null $compatibility Embedding compatibility boundary.
	 */
	private function chunk(
		string $id,
		int $sequence,
		string $content,
		?string $language,
		string $visibility,
		?string $compatibility
	): ChunkRecord {
		return new ChunkRecord(
			DocumentHasher::hash( array( 'chunk' => $id ) ),
			'doc-' . $id,
			7,
			'post',
			'Title',
			'https://example.test/' . $id,
			$content,
			DocumentHasher::hash(
				array(
					'content' => $content,
					'id'      => $id,
				)
			),
			'v1',
			DocumentHasher::hash( array( 'document' => $id ) ),
			$language,
			$visibility,
			$sequence,
			null,
			array(),
			1,
			'm07-v1',
			DocumentHasher::hash( array( 'chunking' => 'm07-v1' ) ),
			$compatibility,
			array()
		);
	}
}
// phpcs:enable WordPress.NamingConventions
