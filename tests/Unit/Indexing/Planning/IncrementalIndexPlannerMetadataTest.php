<?php
/**
 * Incremental index metadata invalidation tests.
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

// phpcs:disable WordPress.NamingConventions -- Assertions intentionally use the approved camelCase domain contracts.
/**
 * Verifies metadata changes cannot leave stale indexed/citation records.
 */
final class IncrementalIndexPlannerMetadataTest extends TestCase {
	/**
	 * Language is an index compatibility boundary even when key/content are stable.
	 */
	public function test_language_change_forces_upsert(): void {
		$previous = $this->chunk( 'en', 'Title', array( 'category' => 'docs' ) );
		$current  = $this->chunk( 'fr', 'Title', array( 'category' => 'docs' ) );

		$plan = ( new IncrementalIndexPlanner() )->plan(
			array( $previous ),
			new ChunkDeduplicationResult( array( $current ), array() )
		);

		self::assertSame( array( $current ), $plan->upsert );
		self::assertSame( array(), $plan->unchanged );
	}

	/**
	 * Citation metadata changes require updating the stored index record.
	 */
	public function test_title_or_source_metadata_change_forces_upsert(): void {
		$previous = $this->chunk( 'en', 'Old title', array( 'category' => 'old' ) );
		$current  = $this->chunk( 'en', 'New title', array( 'category' => 'new' ) );

		$plan = ( new IncrementalIndexPlanner() )->plan(
			array( $previous ),
			new ChunkDeduplicationResult( array( $current ), array() )
		);

		self::assertSame( array( $current ), $plan->upsert );
		self::assertSame( array(), $plan->unchanged );
	}

	/**
	 * Token-count metadata changes require updating the stored index record.
	 */
	public function test_token_count_change_forces_upsert(): void {
		$previous = $this->chunk( 'en', 'Title', array( 'category' => 'docs' ), 1 );
		$current  = $this->chunk( 'en', 'Title', array( 'category' => 'docs' ), 2 );

		$plan = ( new IncrementalIndexPlanner() )->plan(
			array( $previous ),
			new ChunkDeduplicationResult( array( $current ), array() )
		);

		self::assertSame( array( $current ), $plan->upsert );
		self::assertSame( array(), $plan->unchanged );
	}

	/**
	 * Build one stable-key/content chunk while varying indexed metadata.
	 *
	 * @param string               $language Chunk language.
	 * @param string               $title Citation title.
	 * @param array<string, mixed> $sourceMetadata Source metadata.
	 * @param int                  $tokenCount Stored chunk token count.
	 */
	private function chunk( string $language, string $title, array $sourceMetadata, int $tokenCount = 1 ): ChunkRecord {
		return new ChunkRecord(
			DocumentHasher::hash( array( 'chunk-key' => 'metadata-stable' ) ),
			'doc-plan',
			7,
			'post',
			$title,
			'https://example.test/plan',
			'same',
			DocumentHasher::hash(
				array(
					'document_key'  => 'doc-plan',
					'source_id'     => 7,
					'canonical_url' => 'https://example.test/plan',
					'heading_path'  => array(),
					'parent_key'    => DocumentHasher::hash( array( 'parent' => 'stable' ) ),
					'content'       => 'same',
				)
			),
			'v1',
			DocumentHasher::hash( array( 'document' => 'plan' ) ),
			$language,
			'public',
			0,
			DocumentHasher::hash( array( 'parent' => 'stable' ) ),
			array(),
			$tokenCount,
			'm07-v1',
			DocumentHasher::hash( array( 'fingerprint' => 'chunking-v1' ) ),
			null,
			$sourceMetadata
		);
	}
}
// phpcs:enable WordPress.NamingConventions
