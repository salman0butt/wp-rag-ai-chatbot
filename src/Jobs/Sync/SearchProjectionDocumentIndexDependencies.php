<?php
/**
 * M10 lexical-projection synchronization decorator for document indexing.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use InvalidArgumentException;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Planning\IndexPlan;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchStore;

/**
 * Keeps the durable lexical projection aligned with the same accepted M07 plan used by M08 execution.
 */
final class SearchProjectionDocumentIndexDependencies implements DocumentIndexDependencies {
	private const MAX_METADATA_ENTRIES = 32;

	/**
	 * Create one synchronization decorator.
	 *
	 * @param DocumentIndexDependencies $inner Existing server-side planning/M08 execution boundary.
	 * @param ChunkSearchStore          $search_store Durable lexical projection.
	 */
	public function __construct(
		private readonly DocumentIndexDependencies $inner,
		private readonly ChunkSearchStore $search_store
	) {
	}

	/**
	 * Preserve the existing server-side planning boundary unchanged.
	 *
	 * @param DocumentIndexJobPayload $payload Stable identifier-only job payload.
	 */
	public function plan( DocumentIndexJobPayload $payload ): IndexPlan {
		return $this->inner->plan( $payload );
	}

	/**
	 * Execute the accepted primary index work, then replace the matching lexical document projection.
	 *
	 * Projection writes deliberately follow the primary executor. If projection persistence fails, the
	 * queue retry can safely replay the idempotent vector mutations and document-level projection replace.
	 *
	 * @param DocumentIndexJobPayload $payload Stable identifier-only job payload.
	 * @param IndexPlan               $plan Accepted M07 plan.
	 * @throws InvalidArgumentException When the accepted plan crosses the queued document/source boundary.
	 */
	public function execute( DocumentIndexJobPayload $payload, IndexPlan $plan ): void {
		$records = $this->projection_records( $payload, $plan );

		$this->inner->execute( $payload, $plan );

		if ( array() === $records ) {
			$this->search_store->delete_document( $payload->collection_id, $payload->document_key );
			return;
		}

		$this->search_store->replace_document_chunks(
			$payload->collection_id,
			$payload->document_key,
			...$records
		);
	}

	/**
	 * Reconstruct the complete current canonical document projection from the accepted plan.
	 *
	 * M07 partitions current canonical chunks across upsert, metadata-refresh and unchanged buckets;
	 * delete keys and duplicate aliases do not represent current searchable chunks.
	 *
	 * @param DocumentIndexJobPayload $payload Stable queued document identity.
	 * @param IndexPlan               $plan Accepted M07 plan.
	 * @return ChunkSearchRecord[]
	 */
	private function projection_records( DocumentIndexJobPayload $payload, IndexPlan $plan ): array {
		$current = array_merge( $plan->upsert, $plan->metadataRefresh, $plan->unchanged );
		usort(
			$current,
			static function ( ChunkRecord $left, ChunkRecord $right ): int {
				$sequence = $left->sequence <=> $right->sequence;
				return 0 !== $sequence ? $sequence : strcmp( $left->chunkKey, $right->chunkKey );
			}
		);

		$records = array();
		foreach ( $current as $chunk ) {
			if ( $chunk->documentKey !== $payload->document_key || $chunk->sourceId !== $payload->source_id ) {
				throw new InvalidArgumentException( 'Accepted index plan crosses the queued document boundary.' );
			}

			$records[] = new ChunkSearchRecord(
				chunk_key: $chunk->chunkKey,
				document_key: $chunk->documentKey,
				source_id: $chunk->sourceId,
				document_type: $chunk->documentType,
				title: $chunk->title,
				canonical_url: $chunk->canonicalUrl,
				content: $chunk->content,
				content_hash: $chunk->contentHash,
				language: $chunk->language,
				visibility: $chunk->visibility,
				sequence: $chunk->sequence,
				metadata: $this->safe_metadata( $chunk->sourceMetadata )
			);
		}

		return $records;
	}

	/**
	 * Keep only bounded portable scalar source metadata suitable for retrieval/citation diagnostics.
	 *
	 * @param array<string, mixed> $metadata Raw copied source metadata from M07.
	 * @return array<string, scalar>
	 */
	private function safe_metadata( array $metadata ): array {
		$safe = array();
		foreach ( $metadata as $key => $value ) {
			if ( count( $safe ) >= self::MAX_METADATA_ENTRIES ) {
				break;
			}
			if ( 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/', $key ) || ! is_scalar( $value ) ) {
				continue;
			}
			if ( is_string( $value ) && strlen( $value ) > 512 ) {
				continue;
			}
			if ( is_float( $value ) && ! is_finite( $value ) ) {
				continue;
			}
			$safe[ $key ] = $value;
		}

		return $safe;
	}
}
