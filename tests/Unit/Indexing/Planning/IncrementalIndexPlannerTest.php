<?php
/**
 * Incremental index planning tests.
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
use WpRagAiChatbot\Indexing\Planning\IndexPlan;

// phpcs:disable WordPress.NamingConventions -- Assertions intentionally use the approved camelCase domain contracts.
/**
 * Verifies deterministic minimal-work incremental index plans.
 */
final class IncrementalIndexPlannerTest extends TestCase {
	/**
	 * Initial indexing upserts every current canonical chunk.
	 */
	public function test_initial_index_upserts_all_current_chunks(): void {
		$this->requirePlanner();
		$first   = $this->chunk( 'first', 0 );
		$second  = $this->chunk( 'second', 1 );
		$current = new ChunkDeduplicationResult( array( $second, $first ), array() );

		$plan = ( new IncrementalIndexPlanner() )->plan( array(), $current );

		self::assertInstanceOf( IndexPlan::class, $plan );
		self::assertSame( array( $first, $second ), $plan->upsert );
		self::assertSame( array(), $plan->deleteKeys );
		self::assertSame( array(), $plan->unchanged );
	}

	/**
	 * Exact previous/current matches require no re-embedding work.
	 */
	public function test_exact_match_is_unchanged_without_upsert_or_delete(): void {
		$this->requirePlanner();
		$chunk   = $this->chunk( 'stable', 0 );
		$current = new ChunkDeduplicationResult( array( $chunk ), array() );

		$plan = ( new IncrementalIndexPlanner() )->plan( array( $chunk ), $current );

		self::assertSame( array(), $plan->upsert );
		self::assertSame( array(), $plan->deleteKeys );
		self::assertSame( array( $chunk ), $plan->unchanged );
	}

	/**
	 * A localized change only upserts the changed current chunk.
	 */
	public function test_localized_content_change_only_upserts_changed_chunk(): void {
		$this->requirePlanner();
		$stable         = $this->chunk( 'stable', 0 );
		$previous_local = $this->chunk( 'local', 1, 'before' );
		$current_local  = $this->chunk( 'local', 1, 'after' );
		$current        = new ChunkDeduplicationResult( array( $current_local, $stable ), array() );

		$plan = ( new IncrementalIndexPlanner() )->plan( array( $previous_local, $stable ), $current );

		self::assertSame( array( $current_local ), $plan->upsert );
		self::assertSame( array(), $plan->deleteKeys );
		self::assertSame( array( $stable ), $plan->unchanged );
	}

	/**
	 * Previous canonical keys absent from current output are deleted.
	 */
	public function test_removed_chunk_key_is_deleted(): void {
		$this->requirePlanner();
		$kept    = $this->chunk( 'kept', 0 );
		$removed = $this->chunk( 'removed', 1 );
		$current = new ChunkDeduplicationResult( array( $kept ), array() );

		$plan = ( new IncrementalIndexPlanner() )->plan( array( $removed, $kept ), $current );

		self::assertSame( array(), $plan->upsert );
		self::assertSame( array( $removed->chunkKey ), $plan->deleteKeys );
		self::assertSame( array( $kept ), $plan->unchanged );
	}

	/**
	 * New canonical keys are added without touching stable existing chunks.
	 */
	public function test_new_chunk_is_upserted_while_existing_chunk_stays_unchanged(): void {
		$this->requirePlanner();
		$existing = $this->chunk( 'existing', 0 );
		$added    = $this->chunk( 'added', 1 );
		$current  = new ChunkDeduplicationResult( array( $added, $existing ), array() );

		$plan = ( new IncrementalIndexPlanner() )->plan( array( $existing ), $current );

		self::assertSame( array( $added ), $plan->upsert );
		self::assertSame( array(), $plan->deleteKeys );
		self::assertSame( array( $existing ), $plan->unchanged );
	}

	/**
	 * A chunking compatibility change forces a current key to be upserted.
	 */
	public function test_chunking_fingerprint_change_forces_upsert(): void {
		$this->requirePlanner();
		$previous = $this->chunk( 'same-key', 0, 'same', 'chunking-v1', null );
		$current  = $this->chunk( 'same-key', 0, 'same', 'chunking-v2', null );

		$plan = ( new IncrementalIndexPlanner() )->plan(
			array( $previous ),
			new ChunkDeduplicationResult( array( $current ), array() )
		);

		self::assertSame( array( $current ), $plan->upsert );
		self::assertSame( array(), $plan->unchanged );
	}

	/**
	 * An embedding compatibility change forces a current key to be upserted.
	 */
	public function test_embedding_compatibility_change_forces_upsert(): void {
		$this->requirePlanner();
		$previous = $this->chunk( 'same-key', 0, 'same', 'chunking-v1', 'embed-v1' );
		$current  = $this->chunk( 'same-key', 0, 'same', 'chunking-v1', 'embed-v2' );

		$plan = ( new IncrementalIndexPlanner() )->plan(
			array( $previous ),
			new ChunkDeduplicationResult( array( $current ), array() )
		);

		self::assertSame( array( $current ), $plan->upsert );
		self::assertSame( array(), $plan->unchanged );
	}

	/**
	 * All observable plan collections are deterministic regardless of caller order.
	 */
	public function test_plan_output_and_duplicate_aliases_are_deterministically_ordered(): void {
		$this->requirePlanner();
		$unchanged = $this->chunk( 'unchanged', 0 );
		$added     = $this->chunk( 'added', 1 );
		$removed_a = $this->chunk( 'removed-a', 2 );
		$removed_b = $this->chunk( 'removed-b', 3 );
		$alias_a   = DocumentHasher::hash( array( 'alias' => 'a' ) );
		$alias_b   = DocumentHasher::hash( array( 'alias' => 'b' ) );
		$aliases   = array(
			$alias_b => $unchanged->chunkKey,
			$alias_a => $added->chunkKey,
		);

		$plan = ( new IncrementalIndexPlanner() )->plan(
			array( $removed_b, $unchanged, $removed_a ),
			new ChunkDeduplicationResult( array( $added, $unchanged ), $aliases )
		);

		$expected_deletes = array( $removed_a->chunkKey, $removed_b->chunkKey );
		sort( $expected_deletes, SORT_STRING );
		$expected_aliases = $aliases;
		ksort( $expected_aliases, SORT_STRING );

		self::assertSame( array( $added ), $plan->upsert );
		self::assertSame( $expected_deletes, $plan->deleteKeys );
		self::assertSame( array( $unchanged ), $plan->unchanged );
		self::assertSame( $expected_aliases, $plan->duplicateAliases );
	}

	/**
	 * Require the wished-for production API while preserving assertion-style RED.
	 */
	private function requirePlanner(): void {
		if ( ! class_exists( IncrementalIndexPlanner::class ) ) {
			self::fail( 'IncrementalIndexPlanner class does not exist yet.' );
		}
		if ( ! class_exists( IndexPlan::class ) ) {
			self::fail( 'IndexPlan class does not exist yet.' );
		}
	}

	/**
	 * Build one immutable chunk fixture with a stable key independent of change dimensions.
	 *
	 * @param string      $id Stable fixture identifier.
	 * @param int         $sequence Deterministic chunk sequence.
	 * @param string      $content Chunk content.
	 * @param string      $chunkingFingerprint Chunking compatibility fixture value.
	 * @param string|null $embeddingCompatibilityKey Embedding compatibility fixture value.
	 */
	private function chunk(
		string $id,
		int $sequence,
		string $content = 'same',
		string $chunkingFingerprint = 'chunking-v1',
		?string $embeddingCompatibilityKey = null
	): ChunkRecord {
		return new ChunkRecord(
			DocumentHasher::hash( array( 'chunk-key' => $id ) ),
			'doc-plan',
			7,
			'post',
			'Title',
			'https://example.test/plan',
			$content,
			DocumentHasher::hash( array( 'content' => $content ) ),
			'v1',
			DocumentHasher::hash( array( 'document' => 'plan' ) ),
			'en',
			'public',
			$sequence,
			null,
			array(),
			1,
			'm07-v1',
			DocumentHasher::hash( array( 'fingerprint' => $chunkingFingerprint ) ),
			$embeddingCompatibilityKey,
			array()
		);
	}
}
// phpcs:enable WordPress.NamingConventions
