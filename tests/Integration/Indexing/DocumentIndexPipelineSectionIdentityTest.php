<?php
/**
 * Section-identity integration regression coverage.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration\Indexing;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Indexing\Chunking\ChunkingConfig;
use WpRagAiChatbot\Indexing\Chunking\LexicalTokenCounter;
use WpRagAiChatbot\Indexing\Chunking\StructureAwareChunker;
use WpRagAiChatbot\Indexing\Dedup\ChunkDeduplicator;
use WpRagAiChatbot\Indexing\DocumentIndexPipeline;
use WpRagAiChatbot\Indexing\Normalization\ContentNormalizer;
use WpRagAiChatbot\Indexing\Planning\IncrementalIndexPlanner;

// phpcs:disable WordPress.NamingConventions -- Assertions intentionally use approved camelCase domain contracts.
/**
 * Verifies localized structural edits do not churn unrelated section identities.
 */
final class DocumentIndexPipelineSectionIdentityTest extends TestCase {
	/**
	 * Inserting an unrelated section must preserve a later stable section identity.
	 */
	public function test_unrelated_heading_insertion_preserves_later_section_identity(): void {
		$before = $this->document(
			"# Alpha\n\nAlpha remains stable.\n\n# Gamma\n\nGamma remains byte identical and must retain its structural identity.",
			'v1'
		);
		$after  = $this->document(
			"# Alpha\n\nAlpha remains stable.\n\n# Beta\n\nBeta is a newly inserted unrelated section.\n\n# Gamma\n\nGamma remains byte identical and must retain its structural identity.",
			'v2'
		);

		$pipeline = $this->pipeline();
		$initial  = $pipeline->plan( $before );
		$changed  = $pipeline->plan( $after, $initial->canonicalChunks );

		$before_gamma = array_values(
			array_filter(
				$initial->canonicalChunks,
				static fn ( $chunk ): bool => array( 'Gamma' ) === $chunk->headingPath
			)
		);
		$after_gamma  = array_values(
			array_filter(
				$changed->canonicalChunks,
				static fn ( $chunk ): bool => array( 'Gamma' ) === $chunk->headingPath
			)
		);
		$upsert_keys  = array_map(
			static fn ( $chunk ): string => $chunk->chunkKey,
			$changed->indexPlan->upsert
		);

		self::assertCount( 1, $before_gamma );
		self::assertCount( 1, $after_gamma );
		self::assertSame( $before_gamma[0]->content, $after_gamma[0]->content );
		self::assertSame( $before_gamma[0]->parentChunkKey, $after_gamma[0]->parentChunkKey );
		self::assertSame( $before_gamma[0]->chunkKey, $after_gamma[0]->chunkKey );
		self::assertNotContains( $after_gamma[0]->chunkKey, $upsert_keys );
	}

	/**
	 * Build the pure M07 pipeline under the approved lexical contract.
	 */
	private function pipeline(): DocumentIndexPipeline {
		return new DocumentIndexPipeline(
			new ContentNormalizer(),
			new StructureAwareChunker( new LexicalTokenCounter(), new ChunkingConfig( 32, 4 ) ),
			new ChunkDeduplicator(),
			new IncrementalIndexPlanner()
		);
	}

	/**
	 * Build one canonical document fixture.
	 *
	 * @param string $content Canonical text.
	 * @param string $sourceVersion Source revision/version marker.
	 */
	private function document( string $content, string $sourceVersion ): DocumentRecord {
		$time = new DateTimeImmutable( '2026-01-01T00:00:00+00:00' );

		return new DocumentRecord(
			null,
			DocumentHasher::hash( array( 'document' => 'heading-insertion' ) ),
			17,
			'heading-insertion',
			'post',
			'Heading insertion',
			'https://example.test/heading-insertion',
			$content,
			array(),
			$sourceVersion,
			DocumentHasher::hash( array( 'content' => $content ) ),
			'en',
			'public',
			$time,
			$time
		);
	}
}
// phpcs:enable WordPress.NamingConventions
