<?php
/**
 * Structure-aware chunking behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Indexing\Chunking;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Indexing\Chunking\ChunkingConfig;
use WpRagAiChatbot\Indexing\Chunking\LexicalTokenCounter;
use WpRagAiChatbot\Indexing\Chunking\StructureAwareChunker;

/**
 * Verifies deterministic section-aware bounded chunks and source lineage.
 */
final class StructureAwareChunkerTest extends TestCase {
	/**
	 * Empty content yields no chunks and tiny content yields one fully attributed chunk.
	 */
	public function test_empty_and_tiny_documents_are_handled_deterministically(): void {
		$this->requireChunker();
		$chunker = $this->chunker();

		self::assertSame( array(), $chunker->chunks( $this->document( '' ) ) );

		$chunks = $chunker->chunks( $this->document( 'Tiny content.' ) );
		self::assertCount( 1, $chunks );
		self::assertSame( 0, $chunks[0]->sequence );
		self::assertSame( 'Tiny content.', $chunks[0]->content );
		self::assertSame( 3, $chunks[0]->tokenCount );
		self::assertSame( 7, $chunks[0]->sourceId );
		self::assertSame( 'post', $chunks[0]->documentType );
		self::assertSame( 'Title', $chunks[0]->title );
		self::assertSame( 'https://example.test/doc', $chunks[0]->canonicalUrl );
		self::assertSame( 'v1', $chunks[0]->sourceVersion );
		self::assertSame( 'en', $chunks[0]->language );
		self::assertSame( 'private', $chunks[0]->visibility );
		self::assertSame( array( 'category' => 'docs' ), $chunks[0]->sourceMetadata );
		self::assertSame( $this->document( 'Tiny content.' )->contentHash, $chunks[0]->documentContentHash );
	}

	/**
	 * ATX headings establish heading paths and stable structural parent keys.
	 */
	public function test_headings_and_paragraphs_preserve_structure_and_order(): void {
		$this->requireChunker();
		$content = "# Alpha\n\nFirst paragraph.\n\n## Beta\n\nSecond paragraph.";
		$chunks  = $this->chunker()->chunks( $this->document( $content ) );

		self::assertCount( 2, $chunks );
		self::assertSame( array( 'Alpha' ), $chunks[0]->headingPath );
		self::assertSame( array( 'Alpha', 'Beta' ), $chunks[1]->headingPath );
		self::assertNotNull( $chunks[0]->parentChunkKey );
		self::assertNotNull( $chunks[1]->parentChunkKey );
		self::assertNotSame( $chunks[0]->parentChunkKey, $chunks[1]->parentChunkKey );
		self::assertSame( array( 0, 1 ), array( $chunks[0]->sequence, $chunks[1]->sequence ) );
	}

	/**
	 * Oversized paragraphs split by sentence, then oversized sentences fall back safely.
	 */
	public function test_oversized_content_is_bounded_with_sentence_and_lexical_fallbacks(): void {
		$this->requireChunker();
		$sentence = implode( ' ', array_fill( 0, 70, 'word' ) ) . '.';
		$content  = "# Long\n\n" . implode( ' ', array_fill( 0, 18, 'short' ) ) . '. ' . $sentence;
		$chunks   = $this->chunker()->chunks( $this->document( $content ) );

		self::assertGreaterThan( 2, count( $chunks ) );
		foreach ( $chunks as $chunk ) {
			self::assertGreaterThan( 0, $chunk->tokenCount );
			self::assertLessThanOrEqual( 32, $chunk->tokenCount );
			self::assertSame( array( 'Long' ), $chunk->headingPath );
		}
	}

	/**
	 * Repeated calls produce byte-stable keys, hashes and lineage.
	 */
	public function test_repeated_calls_are_deterministic(): void {
		$this->requireChunker();
		$document = $this->document( "# A\n\nOne two three.\n\nFour five six." );
		$chunker  = $this->chunker();
		$first    = $chunker->chunks( $document );
		$second   = $chunker->chunks( $document );

		self::assertEquals( $first, $second );
		foreach ( $first as $chunk ) {
			self::assertSame( 64, strlen( $chunk->chunkKey ) );
			self::assertSame( 64, strlen( $chunk->contentHash ) );
			self::assertSame( 'm07-v1', $chunk->chunkingVersion );
			self::assertSame( $this->chunkerConfig()->fingerprint(), $chunk->chunkingFingerprint );
			self::assertNull( $chunk->embeddingCompatibilityKey );
		}
	}

	private function requireChunker(): void {
		if ( ! class_exists( StructureAwareChunker::class ) ) {
			self::fail( 'StructureAwareChunker class does not exist yet.' );
		}
	}

	private function chunker(): StructureAwareChunker {
		return new StructureAwareChunker( new LexicalTokenCounter(), $this->chunkerConfig() );
	}

	private function chunkerConfig(): ChunkingConfig {
		return new ChunkingConfig( 32, 0, 'm07-v1', null );
	}

	private function document( string $content ): DocumentRecord {
		return new DocumentRecord(
			null,
			'doc-key',
			7,
			'external-1',
			'post',
			'Title',
			'https://example.test/doc',
			$content,
			array( 'category' => 'docs' ),
			'v1',
			DocumentHasher::hash( array( 'content' => $content ) ),
			'en',
			'private',
			new DateTimeImmutable( '2026-09-03T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-09-03T00:00:00+00:00' )
		);
	}
}
