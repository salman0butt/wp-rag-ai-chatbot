<?php
/**
 * Incremental index document-lineage planning tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Indexing\Planning;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Dedup\ChunkDeduplicationResult;
use WpRagAiChatbot\Indexing\Planning\IncrementalIndexPlanner;

// phpcs:disable WordPress.NamingConventions -- Assertions intentionally use approved camelCase domain contracts.
/**
 * Verifies document-wide lineage changes remain explicit without forcing re-embedding.
 */
final class IncrementalIndexPlannerLineageTest extends TestCase {
	/**
	 * Source-version/document-hash churn requires metadata refresh, not true unchanged reuse.
	 */
	public function test_document_lineage_change_is_metadata_refresh_not_unchanged(): void {
		$previous = $this->chunk(
			'v1',
			DocumentHasher::hash( array( 'document' => 'before' ) )
		);
		$current  = $this->chunk(
			'v2',
			DocumentHasher::hash( array( 'document' => 'after' ) )
		);

		$plan = ( new IncrementalIndexPlanner() )->plan(
			array( $previous ),
			new ChunkDeduplicationResult( array( $current ), array() )
		);

		self::assertSame( array(), $plan->upsert );
		self::assertSame( array(), $plan->unchanged );
		self::assertTrue( property_exists( $plan, 'metadataRefresh' ) );
		self::assertSame( array( $current ), $plan->metadataRefresh );
	}

	/**
	 * Build one stable embedding/content chunk while varying document-wide lineage.
	 *
	 * @param string $sourceVersion Source revision/version marker.
	 * @param string $documentContentHash Current document-wide content hash.
	 */
	private function chunk( string $sourceVersion, string $documentContentHash ): ChunkRecord {
		$parent_key = DocumentHasher::hash( array( 'parent' => 'stable' ) );

		return new ChunkRecord(
			DocumentHasher::hash( array( 'chunk-key' => 'stable-lineage' ) ),
			'doc-plan',
			7,
			'post',
			'Title',
			'https://example.test/plan',
			'same content',
			DocumentHasher::hash(
				array(
					'document_key'  => 'doc-plan',
					'source_id'     => 7,
					'canonical_url' => 'https://example.test/plan',
					'heading_path'  => array(),
					'parent_key'    => $parent_key,
					'content'       => 'same content',
				)
			),
			$sourceVersion,
			$documentContentHash,
			'en',
			'public',
			0,
			$parent_key,
			array(),
			2,
			'm07-v1',
			DocumentHasher::hash( array( 'fingerprint' => 'chunking-v1' ) ),
			null,
			array( 'category' => 'docs' )
		);
	}
}
// phpcs:enable WordPress.NamingConventions
