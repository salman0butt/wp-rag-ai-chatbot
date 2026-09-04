<?php
/**
 * M07 index-plan to M08 embedding/vector execution boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Embeddings;

use InvalidArgumentException;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Planning\IndexPlan;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorDeleteStore;
use WpRagAiChatbot\VectorStore\VectorRecord;
use WpRagAiChatbot\VectorStore\VectorUpsertStore;

// phpcs:disable WordPress.NamingConventions -- M07 domain DTO properties intentionally use the approved camelCase contract.
/**
 * Executes one bounded accepted incremental indexing plan without owning retries or queues.
 */
final class IndexEmbeddingExecutor {
	/** Maximum chunks embedded by one synchronous execution. */
	private const MAX_UPSERTS_PER_EXECUTION = 1000;

	/** Maximum vector deletes issued by one synchronous execution. */
	private const MAX_DELETES_PER_EXECUTION = 1000;

	/**
	 * Create the execution boundary.
	 *
	 * @param EmbeddingService  $embedding_service Provider-neutral embedding service.
	 * @param VectorUpsertStore $upsert_store Raw-vector upsert capability.
	 * @param VectorDeleteStore $delete_store Raw-vector delete capability.
	 * @param VectorCollection  $collection Selected collection/profile boundary.
	 */
	public function __construct(
		private readonly EmbeddingService $embedding_service,
		private readonly VectorUpsertStore $upsert_store,
		private readonly VectorDeleteStore $delete_store,
		private readonly VectorCollection $collection
	) {
	}

	/**
	 * Execute only the embedding/vector mutations explicitly selected by M07 planning.
	 *
	 * @param IndexPlan $plan Accepted incremental indexing plan.
	 * @throws InvalidArgumentException When the plan cannot safely execute under the selected profile.
	 */
	public function execute( IndexPlan $plan ): void {
		if ( count( $plan->upsert ) > self::MAX_UPSERTS_PER_EXECUTION ) {
			throw new InvalidArgumentException( 'Index plan exceeds the bounded embedding execution limit.' );
		}
		if ( count( $plan->deleteKeys ) > self::MAX_DELETES_PER_EXECUTION ) {
			throw new InvalidArgumentException( 'Index plan exceeds the bounded vector delete execution limit.' );
		}
		if ( $this->collection->profile->embedding->provider_id !== $this->embedding_service->provider_id() ) {
			throw new InvalidArgumentException( 'Selected vector profile does not match the configured embedding provider.' );
		}

		$fingerprint = $this->collection->profile->fingerprint();
		$upserts     = array();
		foreach ( $plan->upsert as $chunk ) {
			if ( null !== $chunk->embeddingCompatibilityKey && ! hash_equals( $fingerprint, $chunk->embeddingCompatibilityKey ) ) {
				throw new InvalidArgumentException( 'Chunk embedding compatibility does not match the selected vector collection.' );
			}

			$metadata = $this->metadata_for( $chunk );
			new VectorRecord(
				$this->collection,
				$chunk->chunkKey,
				array_fill( 0, $this->collection->profile->embedding->dimensions, 0.0 ),
				$fingerprint,
				$metadata
			);
			$upserts[] = array( $chunk, $metadata );
		}

		foreach ( $plan->deleteKeys as $delete_key ) {
			if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,191}$/', $delete_key ) ) {
				throw new InvalidArgumentException( 'Index plan delete key is invalid.' );
			}
		}

		if ( array() !== $upserts ) {
			$inputs = array_map(
				static fn ( array $entry ): string => $entry[0]->content,
				$upserts
			);
			$result = $this->embedding_service->embed(
				new EmbeddingRequest(
					$this->collection->profile->embedding->model_id,
					$inputs,
					$this->collection->profile->embedding->dimensions
				)
			);

			foreach ( $upserts as $index => $entry ) {
				/**
				 * Typed chunk extracted from the validated execution list.
				 *
				 * @var ChunkRecord $chunk
				 */
				$chunk = $entry[0];
				$this->upsert_store->upsert(
					new VectorRecord(
						$this->collection,
						$chunk->chunkKey,
						$result->vectors[ $index ]->values,
						$fingerprint,
						$entry[1]
					)
				);
			}
		}

		foreach ( $plan->deleteKeys as $delete_key ) {
			$this->delete_store->delete( $this->collection, $delete_key );
		}
	}

	/**
	 * Build bounded portable lineage metadata without copying arbitrary source metadata.
	 *
	 * @param ChunkRecord $chunk Indexed chunk.
	 * @return array<string, scalar>
	 */
	private function metadata_for( ChunkRecord $chunk ): array {
		$metadata = array(
			'document_key'         => $chunk->documentKey,
			'source_id'            => $chunk->sourceId,
			'document_type'        => $chunk->documentType,
			'content_hash'         => $chunk->contentHash,
			'visibility'           => $chunk->visibility,
			'sequence'             => $chunk->sequence,
			'chunking_version'     => $chunk->chunkingVersion,
			'chunking_fingerprint' => $chunk->chunkingFingerprint,
		);

		if ( null !== $chunk->language ) {
			$metadata['language'] = $chunk->language;
		}
		if ( null !== $chunk->sourceVersion ) {
			$metadata['source_version'] = $chunk->sourceVersion;
		}

		return $metadata;
	}
}
// phpcs:enable WordPress.NamingConventions
