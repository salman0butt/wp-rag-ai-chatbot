<?php
/**
 * Test-only in-memory vector-store adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\VectorStore;

use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorDeleteStore;
use WpRagAiChatbot\VectorStore\VectorMatch;
use WpRagAiChatbot\VectorStore\VectorRecord;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorSearchResult;
use WpRagAiChatbot\VectorStore\VectorSearchStore;
use WpRagAiChatbot\VectorStore\VectorStoreCapabilities;
use WpRagAiChatbot\VectorStore\VectorStoreHealth;
use WpRagAiChatbot\VectorStore\VectorUpsertStore;
use WpRagAiChatbot\VectorStore\VectorWriteResult;

/**
 * Deterministic reference adapter for reusable contract tests only.
 */
final class InMemoryVectorStore implements VectorUpsertStore, VectorDeleteStore, VectorSearchStore {
	/** @var array<string, array<string, VectorRecord>> */
	private array $records = array();

	/**
	 * @param string $id Store ID.
	 */
	public function __construct( private readonly string $id ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function store_id(): string {
		return $this->id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function capabilities(): VectorStoreCapabilities {
		return VectorStoreCapabilities::all();
	}

	/**
	 * {@inheritDoc}
	 */
	public function health(): VectorStoreHealth {
		return VectorStoreHealth::healthy();
	}

	/**
	 * {@inheritDoc}
	 */
	public function upsert( VectorRecord $record ): VectorWriteResult {
		$collection_id = $record->collection->id;
		$changed       = ! isset( $this->records[ $collection_id ][ $record->id ] )
			|| $this->records[ $collection_id ][ $record->id ] != $record;
		$this->records[ $collection_id ][ $record->id ] = $record;

		return new VectorWriteResult( $changed );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( VectorCollection $collection, string $id ): VectorWriteResult {
		$changed = isset( $this->records[ $collection->id ][ $id ] );
		unset( $this->records[ $collection->id ][ $id ] );

		return new VectorWriteResult( $changed );
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( VectorSearchRequest $request ): VectorSearchResult {
		$matches = array();
		foreach ( $this->records[ $request->collection->id ] ?? array() as $record ) {
			if ( ! hash_equals( $request->compatibility_fingerprint, $record->compatibility_fingerprint ) ) {
				continue;
			}
			if ( null !== $request->filter && ! $request->filter->matches( $record->metadata ) ) {
				continue;
			}

			$matches[] = new VectorMatch( $record->id, $this->cosine( $request->vector, $record->values ), $record->metadata );
		}

		usort(
			$matches,
			static function ( VectorMatch $left, VectorMatch $right ): int {
				$score_order = $right->score <=> $left->score;
				return 0 !== $score_order ? $score_order : strcmp( $left->id, $right->id );
			}
		);

		return new VectorSearchResult( array_slice( $matches, 0, $request->top_k ) );
	}

	/**
	 * Calculate deterministic cosine similarity for contract tests.
	 *
	 * @param list<int|float> $left Query vector.
	 * @param list<int|float> $right Candidate vector.
	 */
	private function cosine( array $left, array $right ): float {
		$dot        = 0.0;
		$left_norm  = 0.0;
		$right_norm = 0.0;
		foreach ( $left as $index => $value ) {
			$left_value   = (float) $value;
			$right_value  = (float) $right[ $index ];
			$dot         += $left_value * $right_value;
			$left_norm   += $left_value * $left_value;
			$right_norm  += $right_value * $right_value;
		}

		if ( 0.0 === $left_norm || 0.0 === $right_norm ) {
			return 0.0;
		}

		return $dot / ( sqrt( $left_norm ) * sqrt( $right_norm ) );
	}
}
