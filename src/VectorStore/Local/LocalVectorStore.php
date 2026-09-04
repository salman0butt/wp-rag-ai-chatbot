<?php
/**
 * Bounded database-backed local WordPress vector store.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Local;

use InvalidArgumentException;
use JsonException;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\VectorStore\Filter\AndFilter;
use WpRagAiChatbot\VectorStore\Filter\EqualsFilter;
use WpRagAiChatbot\VectorStore\Filter\InFilter;
use WpRagAiChatbot\VectorStore\Filter\VectorFilter;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorDeleteStore;
use WpRagAiChatbot\VectorStore\VectorMatch;
use WpRagAiChatbot\VectorStore\VectorRecord;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorSearchResult;
use WpRagAiChatbot\VectorStore\VectorSearchStore;
use WpRagAiChatbot\VectorStore\VectorStoreCapabilities;
use WpRagAiChatbot\VectorStore\VectorStoreErrorCode;
use WpRagAiChatbot\VectorStore\VectorStoreException;
use WpRagAiChatbot\VectorStore\VectorStoreHealth;
use WpRagAiChatbot\VectorStore\VectorUpsertStore;
use WpRagAiChatbot\VectorStore\VectorWriteResult;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exceptions are not rendered output.
/**
 * Stores modest vector collections in dedicated per-site WordPress tables.
 */
final class LocalVectorStore implements VectorUpsertStore, VectorDeleteStore, VectorSearchStore {
	/**
	 * Create the local vector store.
	 *
	 * @param Connection             $connection Database connection.
	 * @param TableNames             $tables Plugin table names.
	 * @param LocalVectorStoreConfig $config Hard local-search bounds.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly TableNames $tables,
		private readonly LocalVectorStoreConfig $config
	) {
	}

	/**
	 * Return the stable store ID.
	 */
	public function store_id(): string {
		return 'local-wordpress';
	}

	/**
	 * The local adapter supports all raw vector operations.
	 */
	public function capabilities(): VectorStoreCapabilities {
		return VectorStoreCapabilities::all();
	}

	/**
	 * Report whether the dedicated local tables are present.
	 */
	public function health(): VectorStoreHealth {
		if ( ! $this->connection->table_exists( $this->tables->vector_collections() ) || ! $this->connection->table_exists( $this->tables->vectors() ) ) {
			return VectorStoreHealth::unhealthy( 'Local vector-store tables are unavailable.' );
		}

		return VectorStoreHealth::healthy();
	}

	/**
	 * Insert or replace one stable vector record.
	 *
	 * @param VectorRecord $record Record to write.
	 * @throws VectorStoreException When persistence or encoding fails.
	 */
	public function upsert( VectorRecord $record ): VectorWriteResult {
		$this->ensure_collection_for_write( $record->collection );

		$sql      = $this->connection->prepare(
			'SELECT vector_json, metadata_json, fingerprint FROM %i WHERE collection_key = %s AND vector_key = %s LIMIT 1',
			$this->tables->vectors(),
			$record->collection->id,
			$record->id
		);
		$existing = $this->connection->get_row( $sql );

		$vector_json   = wp_json_encode( $record->values );
		$metadata_json = wp_json_encode( $record->metadata );
		if ( false === $vector_json || false === $metadata_json ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Could not encode local vector data.' );
		}

		if (
			null !== $existing
			&& (string) ( $existing['vector_json'] ?? '' ) === $vector_json
			&& (string) ( $existing['metadata_json'] ?? '' ) === $metadata_json
			&& hash_equals( $record->compatibility_fingerprint, (string) ( $existing['fingerprint'] ?? '' ) )
		) {
			return new VectorWriteResult( false );
		}

		$now  = gmdate( 'Y-m-d H:i:s' );
		$data = array(
			'fingerprint'   => $record->compatibility_fingerprint,
			'vector_json'   => $vector_json,
			'metadata_json' => $metadata_json,
			'updated_at'    => $now,
		);

		if ( null === $existing ) {
			$data['collection_key'] = $record->collection->id;
			$data['vector_key']     = $record->id;
			$data['created_at']     = $now;
			$result                 = $this->connection->insert( $this->tables->vectors(), $data );
		} else {
			$result = $this->connection->update(
				$this->tables->vectors(),
				$data,
				array(
					'collection_key' => $record->collection->id,
					'vector_key'     => $record->id,
				)
			);
		}

		if ( false === $result ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Could not persist local vector data.' );
		}

		return new VectorWriteResult( true );
	}

	/**
	 * Delete one stable ID from a collection idempotently.
	 *
	 * @param VectorCollection $collection Collection boundary.
	 * @param string           $id Stable record ID.
	 * @throws VectorStoreException When the ID is invalid or persistence fails.
	 */
	public function delete( VectorCollection $collection, string $id ): VectorWriteResult {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,191}$/', $id ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, 'Local vector record ID is invalid.' );
		}

		$result = $this->connection->delete(
			$this->tables->vectors(),
			array(
				'collection_key' => $collection->id,
				'vector_key'     => $id,
			)
		);
		if ( false === $result ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Could not delete local vector data.' );
		}

		return new VectorWriteResult( (int) $result > 0 );
	}

	/**
	 * Search one collection using bounded candidate selection before PHP scoring.
	 *
	 * @param VectorSearchRequest $request Search request.
	 * @throws VectorStoreException When the request, persisted data, or operation is invalid.
	 */
	public function search( VectorSearchRequest $request ): VectorSearchResult {
		if ( $request->top_k > $this->config->max_top_k ) {
			throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, 'Local vector search top-K exceeds the configured limit.' );
		}

		if ( ! $this->assert_persisted_collection_compatible( $request->collection ) ) {
			return new VectorSearchResult( array() );
		}

		$filter_sql  = '';
		$filter_args = array();
		if ( null !== $request->filter ) {
			list( $filter_sql, $filter_args ) = $this->filter_sql( $request->filter );
		}

		$sql  = 'SELECT vector_key, vector_json, metadata_json FROM %i WHERE collection_key = %s AND fingerprint = %s'
			. $filter_sql
			. ' ORDER BY vector_key ASC LIMIT %d';
		$args = array_merge(
			array( $this->tables->vectors(), $request->collection->id, $request->compatibility_fingerprint ),
			$filter_args,
			array( $this->config->candidate_limit + 1 )
		);

		// @phpstan-ignore argument.type
		$prepared = $this->connection->prepare( $sql, ...$args );
		$rows     = $this->connection->get_results( $prepared );
		if ( count( $rows ) > $this->config->candidate_limit ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Local vector candidate limit was exceeded.' );
		}

		$matches = array();
		foreach ( $rows as $row ) {
			try {
				$vector   = $this->decode_vector( $row['vector_json'] ?? null );
				$metadata = $this->decode_metadata( $row['metadata_json'] ?? null );
				if ( null !== $request->filter && ! $request->filter->matches( $metadata ) ) {
					continue;
				}
				$matches[] = new VectorMatch(
					(string) ( $row['vector_key'] ?? '' ),
					CosineSimilarity::score( $request->vector, $vector ),
					$metadata
				);
			} catch ( InvalidArgumentException | JsonException ) {
				throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Stored local vector data is invalid.' );
			}
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
	 * Create a missing collection or reject an incompatible persisted profile.
	 *
	 * @param VectorCollection $collection Collection boundary.
	 * @throws VectorStoreException When the collection is incompatible or cannot be created.
	 */
	private function ensure_collection_for_write( VectorCollection $collection ): void {
		if ( $this->assert_persisted_collection_compatible( $collection ) ) {
			return;
		}

		$now    = gmdate( 'Y-m-d H:i:s' );
		$result = $this->connection->insert(
			$this->tables->vector_collections(),
			array(
				'collection_key' => $collection->id,
				'fingerprint'    => $collection->profile->fingerprint(),
				'dimensions'     => $collection->profile->embedding->dimensions,
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
		if ( false === $result ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Could not create local vector collection.' );
		}
	}

	/**
	 * Verify persisted collection compatibility when a collection exists.
	 *
	 * @param VectorCollection $collection Collection boundary.
	 * @return bool Whether the collection already exists.
	 * @throws VectorStoreException When the persisted collection profile is incompatible.
	 */
	private function assert_persisted_collection_compatible( VectorCollection $collection ): bool {
		$sql = $this->connection->prepare(
			'SELECT fingerprint, dimensions FROM %i WHERE collection_key = %s LIMIT 1',
			$this->tables->vector_collections(),
			$collection->id
		);
		$row = $this->connection->get_row( $sql );
		if ( null === $row ) {
			return false;
		}

		$fingerprint = (string) ( $row['fingerprint'] ?? '' );
		$dimensions  = (int) ( $row['dimensions'] ?? 0 );
		if ( ! hash_equals( $collection->profile->fingerprint(), $fingerprint ) || $collection->profile->embedding->dimensions !== $dimensions ) {
			throw new VectorStoreException( VectorStoreErrorCode::INCOMPATIBLE_PROFILE, 'Local vector collection profile is incompatible.' );
		}

		return true;
	}

	/**
	 * Translate the bounded portable filter AST to prepared SQL only.
	 *
	 * @param VectorFilter $filter Portable filter.
	 * @return array{0:string,1:array<int,mixed>}
	 * @throws VectorStoreException When the filter type is unsupported.
	 */
	private function filter_sql( VectorFilter $filter ): array {
		if ( $filter instanceof EqualsFilter ) {
			return array(
				' AND JSON_EXTRACT(metadata_json, %s) = JSON_EXTRACT(%s, \'$\')',
				array( $this->json_path( $filter->key ), $this->json_scalar( $filter->value ) ),
			);
		}

		if ( $filter instanceof InFilter ) {
			$clauses = array();

			$args = array();
			foreach ( $filter->values as $value ) {
				$clauses[] = 'JSON_EXTRACT(metadata_json, %s) = JSON_EXTRACT(%s, \'$\')';
				$args[]    = $this->json_path( $filter->key );
				$args[]    = $this->json_scalar( $value );
			}

			return array( ' AND (' . implode( ' OR ', $clauses ) . ')', $args );
		}

		if ( $filter instanceof AndFilter ) {
			$sql = '';

			$args = array();
			foreach ( $filter->filters as $child ) {
				list( $child_sql, $child_args ) = $this->filter_sql( $child );

				$sql .= $child_sql;

				$args = array_merge( $args, $child_args );
			}

			return array( $sql, $args );
		}

		throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, 'Local vector filter is unsupported.' );
	}

	/**
	 * Encode a validated metadata key as a JSON path argument.
	 *
	 * @param string $key Metadata key.
	 */
	private function json_path( string $key ): string {
		return '$."' . $key . '"';
	}

	/**
	 * Encode one portable scalar for JSON comparison.
	 *
	 * @param string|int|float|bool $value Portable scalar.
	 * @throws VectorStoreException When the scalar cannot be encoded.
	 */
	private function json_scalar( string|int|float|bool $value ): string {
		$encoded = wp_json_encode( $value );
		if ( false === $encoded ) {
			throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, 'Local vector filter value is invalid.' );
		}

		return $encoded;
	}

	/**
	 * Decode a stored dense vector.
	 *
	 * @param mixed $value Stored JSON value.
	 * @return list<int|float>
	 * @throws JsonException|InvalidArgumentException When stored vector data is malformed.
	 */
	private function decode_vector( mixed $value ): array {
		$decoded = json_decode( (string) $value, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $decoded ) || ! array_is_list( $decoded ) || array() === $decoded ) {
			throw new InvalidArgumentException( 'Stored local vector must be a non-empty ordered list.' );
		}

		foreach ( $decoded as $entry ) {
			if ( ( ! is_int( $entry ) && ! is_float( $entry ) ) || ! is_finite( (float) $entry ) ) {
				throw new InvalidArgumentException( 'Stored local vector values must be finite numeric values.' );
			}
		}

		/**
		 * Validated stored vector.
		 *
		 * @var list<int|float> $decoded
		 */
		return $decoded;
	}

	/**
	 * Decode stored portable metadata.
	 *
	 * @param mixed $value Stored JSON value.
	 * @return array<string, scalar>
	 * @throws JsonException|InvalidArgumentException When stored metadata is malformed.
	 */
	private function decode_metadata( mixed $value ): array {
		if ( null === $value || '' === $value ) {
			return array();
		}

		$decoded = json_decode( (string) $value, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $decoded ) ) {
			throw new InvalidArgumentException( 'Stored local vector metadata must decode to an object.' );
		}
		foreach ( $decoded as $key => $entry ) {
			if ( ! is_string( $key ) || ! is_scalar( $entry ) ) {
				throw new InvalidArgumentException( 'Stored local vector metadata must contain scalar keyed values.' );
			}
		}

		/**
		 * Validated stored metadata.
		 *
		 * @var array<string, scalar> $decoded
		 */
		return $decoded;
	}
}
// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
