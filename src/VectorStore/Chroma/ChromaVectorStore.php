<?php
/**
 * Chroma raw-vector adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Chroma;

use InvalidArgumentException;
use JsonException;
use Throwable;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Providers\Http\HttpRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\HttpTransport;
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
 * Maps portable raw-vector contracts to Chroma's v2 HTTP API.
 */
final class ChromaVectorStore implements VectorUpsertStore, VectorDeleteStore, VectorSearchStore {
	/** HTTP timeout for one bounded adapter request. */
	private const TIMEOUT_SECONDS = 10;

	/** Reserved metadata key containing the compatibility fingerprint. */
	private const METADATA_FINGERPRINT = '_wp_rag_fingerprint';

	/**
	 * Create the Chroma adapter without performing network I/O.
	 *
	 * @param ChromaConfig       $config Validated remote configuration.
	 * @param VectorIndexProfile $profile Required vector index profile.
	 * @param HttpTransport      $transport Single-send HTTP transport.
	 */
	public function __construct(
		private readonly ChromaConfig $config,
		private readonly VectorIndexProfile $profile,
		private readonly HttpTransport $transport
	) {
	}

	/** Return the stable store ID. */
	public function store_id(): string {
		return 'chroma';
	}

	/** Return truthful raw-vector capabilities. */
	public function capabilities(): VectorStoreCapabilities {
		return VectorStoreCapabilities::all();
	}

	/** Perform one explicit bounded Chroma health request. */
	public function health(): VectorStoreHealth {
		try {
			$response = $this->send( 'GET', '/api/v2/healthcheck' );
			if ( $this->successful( $response ) ) {
				return VectorStoreHealth::healthy();
			}
		} catch ( VectorStoreException ) {
			return VectorStoreHealth::unhealthy( 'Chroma is unavailable.' );
		}

		return VectorStoreHealth::unhealthy( 'Chroma is unavailable.' );
	}

	/**
	 * Insert or replace one plugin stable vector record.
	 *
	 * @param VectorRecord $record Record to write.
	 * @throws VectorStoreException When validation, transport, or the remote operation fails.
	 */
	public function upsert( VectorRecord $record ): VectorWriteResult {
		$this->assert_collection_profile( $record->collection );
		$remote_id = $this->remote_collection_id( $record->collection );
		$metadata  = array_merge(
			array( self::METADATA_FINGERPRINT => $record->compatibility_fingerprint ),
			$record->metadata
		);
		$response  = $this->send(
			'POST',
			$this->collection_operation_path( $remote_id, 'upsert' ),
			array(
				'ids'        => array( $record->id ),
				'embeddings' => array( $record->values ),
				'metadatas'  => array( $metadata ),
			)
		);
		$this->require_success( $response, 'Chroma vector upsert failed.' );

		return new VectorWriteResult( true );
	}

	/**
	 * Delete one plugin stable ID from one compatibility-isolated collection.
	 *
	 * @param VectorCollection $collection Collection boundary.
	 * @param string           $id Plugin stable vector ID.
	 * @throws VectorStoreException When the ID/profile, transport, or remote operation is invalid.
	 */
	public function delete( VectorCollection $collection, string $id ): VectorWriteResult {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,191}$/', $id ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, 'Chroma vector record ID is invalid.' );
		}

		$this->assert_collection_profile( $collection );
		$remote_id = $this->remote_collection_id( $collection );
		$response  = $this->send(
			'POST',
			$this->collection_operation_path( $remote_id, 'delete' ),
			array( 'ids' => array( $id ) )
		);
		$this->require_success( $response, 'Chroma vector delete failed.' );

		return new VectorWriteResult( true );
	}

	/**
	 * Search one compatibility-isolated Chroma collection.
	 *
	 * @param VectorSearchRequest $request Portable vector search request.
	 * @throws VectorStoreException When validation, mapping, transport, or the remote response fails.
	 */
	public function search( VectorSearchRequest $request ): VectorSearchResult {
		$this->assert_collection_profile( $request->collection );
		$conditions = array(
			array(
				self::METADATA_FINGERPRINT => array( '$eq' => $request->compatibility_fingerprint ),
			),
		);
		if ( null !== $request->filter ) {
			$conditions = array_merge( $conditions, $this->filter_conditions( $request->filter ) );
		}

		$remote_id = $this->remote_collection_id( $request->collection );
		$response  = $this->send(
			'POST',
			$this->collection_operation_path( $remote_id, 'query' ),
			array(
				'query_embeddings' => array( $request->vector ),
				'n_results'        => $request->top_k,
				'where'            => array( '$and' => $conditions ),
				'include'          => array( 'distances', 'metadatas' ),
			)
		);
		$this->require_success( $response, 'Chroma vector search failed.' );

		return $this->search_result( $response->body, $request );
	}

	/**
	 * Fail before network execution when a logical collection does not match adapter configuration.
	 *
	 * @param VectorCollection $collection Collection to validate.
	 * @throws VectorStoreException When the configured profile differs.
	 */
	private function assert_collection_profile( VectorCollection $collection ): void {
		if ( ! hash_equals( $this->profile->fingerprint(), $collection->profile->fingerprint() ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::INCOMPATIBLE_PROFILE, 'Chroma vector collection profile is incompatible.' );
		}
	}

	/**
	 * Resolve and verify one compatibility-isolated remote collection.
	 *
	 * @param VectorCollection $collection Logical collection.
	 * @return string Remote Chroma collection UUID.
	 * @throws VectorStoreException When the collection is unavailable, malformed, or incompatible.
	 */
	private function remote_collection_id( VectorCollection $collection ): string {
		$response = $this->send( 'GET', $this->logical_collection_path( $collection ) );
		if ( 404 === $response->status ) {
			throw new VectorStoreException( VectorStoreErrorCode::UNAVAILABLE, 'Chroma vector collection is unavailable.' );
		}
		$this->require_success( $response, 'Chroma vector collection could not be inspected.' );
		$data = $this->decode_json( $response->body, 'Chroma collection response is invalid.' );

		$id          = $data['id'] ?? null;
		$name        = $data['name'] ?? null;
		$tenant      = $data['tenant'] ?? null;
		$database    = $data['database'] ?? null;
		$dimension   = $data['dimension'] ?? null;
		$metadata    = $data['metadata'] ?? null;
		$config      = $data['configuration_json'] ?? null;
		$fingerprint = is_array( $metadata ) ? ( $metadata[ self::METADATA_FINGERPRINT ] ?? null ) : null;
		$space       = is_array( $config ) && is_array( $config['hnsw'] ?? null ) ? ( $config['hnsw']['space'] ?? null ) : null;
		if (
			! is_string( $id ) || 1 !== preg_match( '/^[A-Fa-f0-9-]{36}$/', $id ) ||
			! is_string( $name ) || $this->physical_collection_name( $collection ) !== $name ||
			! is_string( $tenant ) || $this->config->tenant !== $tenant ||
			! is_string( $database ) || $this->config->database !== $database ||
			! is_int( $dimension ) || $this->profile->embedding->dimensions !== $dimension ||
			! is_string( $space ) || $this->chroma_space() !== $space ||
			! is_string( $fingerprint ) || ! hash_equals( $collection->profile->fingerprint(), $fingerprint )
		) {
			throw new VectorStoreException( VectorStoreErrorCode::INCOMPATIBLE_PROFILE, 'Chroma vector collection profile is incompatible.' );
		}

		return $id;
	}

	/**
	 * Map the portable filter AST to bounded Chroma metadata conditions.
	 *
	 * @param VectorFilter $filter Portable filter.
	 * @return list<array<string, mixed>>
	 * @throws VectorStoreException When the filter is unsupported.
	 */
	private function filter_conditions( VectorFilter $filter ): array {
		if ( $filter instanceof EqualsFilter ) {
			return array( array( $filter->key => array( '$eq' => $filter->value ) ) );
		}
		if ( $filter instanceof InFilter ) {
			return array( array( $filter->key => array( '$in' => $filter->values ) ) );
		}
		if ( $filter instanceof AndFilter ) {
			$conditions = array();
			foreach ( $filter->filters as $child ) {
				$conditions = array_merge( $conditions, $this->filter_conditions( $child ) );
			}
			return $conditions;
		}

		throw new VectorStoreException( VectorStoreErrorCode::UNSUPPORTED_CAPABILITY, 'Chroma vector filter is unsupported.' );
	}

	/**
	 * Parse one untrusted Chroma query response into deterministic portable matches.
	 *
	 * @param string              $body Response body.
	 * @param VectorSearchRequest $request Original bounded request.
	 * @throws VectorStoreException When response structure or values are invalid.
	 */
	private function search_result( string $body, VectorSearchRequest $request ): VectorSearchResult {
		$data      = $this->decode_json( $body, 'Chroma query response is invalid.' );
		$ids       = $data['ids'] ?? null;
		$distances = $data['distances'] ?? null;
		$metadatas = $data['metadatas'] ?? null;
		if (
			! is_array( $ids ) || 1 !== count( $ids ) || ! is_array( $ids[0] ?? null ) || ! array_is_list( $ids[0] ) ||
			! is_array( $distances ) || 1 !== count( $distances ) || ! is_array( $distances[0] ?? null ) || ! array_is_list( $distances[0] ) ||
			! is_array( $metadatas ) || 1 !== count( $metadatas ) || ! is_array( $metadatas[0] ?? null ) || ! array_is_list( $metadatas[0] ) ||
			count( $ids[0] ) > $request->top_k || count( $ids[0] ) !== count( $distances[0] ) || count( $ids[0] ) !== count( $metadatas[0] )
		) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Chroma query response is invalid.' );
		}

		$matches = array();
		foreach ( $ids[0] as $index => $id ) {
			$distance = $distances[0][ $index ] ?? null;
			$metadata = $metadatas[0][ $index ] ?? null;
			if ( ! is_string( $id ) || ! is_numeric( $distance ) || ! is_finite( (float) $distance ) || ! is_array( $metadata ) || array_is_list( $metadata ) ) {
				throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Chroma query response is invalid.' );
			}
			$fingerprint = $metadata[ self::METADATA_FINGERPRINT ] ?? null;
			if ( ! is_string( $fingerprint ) || ! hash_equals( $request->compatibility_fingerprint, $fingerprint ) ) {
				throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Chroma query response is invalid.' );
			}
			unset( $metadata[ self::METADATA_FINGERPRINT ] );
			try {
				$matches[] = new VectorMatch( $id, $this->score_from_distance( (float) $distance ), $metadata );
			} catch ( InvalidArgumentException ) {
				throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Chroma query response is invalid.' );
			}
		}

		usort(
			$matches,
			static function ( VectorMatch $left, VectorMatch $right ): int {
				$score_order = $right->score <=> $left->score;
				return 0 !== $score_order ? $score_order : strcmp( $left->id, $right->id );
			}
		);

		return new VectorSearchResult( $matches );
	}

	/**
	 * Return the deterministic compatibility-isolated physical collection name.
	 *
	 * @param VectorCollection $collection Logical collection.
	 */
	private function physical_collection_name( VectorCollection $collection ): string {
		return 'wp-' . substr( hash( 'sha256', $collection->id ), 0, 12 ) . '-' . substr( $collection->profile->fingerprint(), 0, 16 );
	}

	/**
	 * Return one encoded logical collection inspection path.
	 *
	 * @param VectorCollection $collection Logical collection.
	 */
	private function logical_collection_path( VectorCollection $collection ): string {
		return $this->scope_path() . '/collections/' . rawurlencode( $this->physical_collection_name( $collection ) );
	}

	/**
	 * Return one encoded remote collection operation path.
	 *
	 * @param string $remote_id Remote collection UUID.
	 * @param string $operation Adapter-owned operation name.
	 */
	private function collection_operation_path( string $remote_id, string $operation ): string {
		return $this->scope_path() . '/collections/' . rawurlencode( $remote_id ) . '/' . $operation;
	}

	/** Return the encoded Chroma tenant/database v2 API scope. */
	private function scope_path(): string {
		return '/api/v2/tenants/' . rawurlencode( $this->config->tenant ) . '/databases/' . rawurlencode( $this->config->database );
	}

	/** Return Chroma's metric identifier for the configured profile. */
	private function chroma_space(): string {
		return match ( $this->profile->distance ) {
			DistanceMetric::COSINE      => 'cosine',
			DistanceMetric::DOT_PRODUCT => 'ip',
		};
	}

	/**
	 * Convert Chroma distance into a portable higher-is-better score.
	 *
	 * @param float $distance Chroma distance.
	 */
	private function score_from_distance( float $distance ): float {
		return 1.0 - $distance;
	}

	/**
	 * Send exactly one request through the injected transport.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $path Adapter-owned path.
	 * @param array<string, mixed>|null $body Optional JSON body.
	 * @throws VectorStoreException When transport execution fails.
	 */
	private function send( string $method, string $path, ?array $body = null ): HttpResponse {
		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
		if ( null !== $this->config->token() ) {
			$headers['x-chroma-token'] = $this->config->token();
		}
		$request = new HttpRequest(
			'chroma',
			$method,
			$this->config->base_url() . $path,
			$headers,
			$body,
			self::TIMEOUT_SECONDS,
			0
		);

		try {
			return $this->transport->send( $request );
		} catch ( Throwable ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Chroma request failed.' );
		}
	}

	/**
	 * Determine whether an HTTP response is successful.
	 *
	 * @param HttpResponse $response Response to inspect.
	 */
	private function successful( HttpResponse $response ): bool {
		return $response->status >= 200 && $response->status < 300;
	}

	/**
	 * Require a successful response without surfacing opaque remote bodies.
	 *
	 * @param HttpResponse $response Response to inspect.
	 * @param string       $message Sanitized failure message.
	 * @throws VectorStoreException When the response is unsuccessful.
	 */
	private function require_success( HttpResponse $response, string $message ): void {
		if ( ! $this->successful( $response ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, $message );
		}
	}

	/**
	 * Decode an untrusted remote JSON object.
	 *
	 * @param string $body Response body.
	 * @param string $message Sanitized failure message.
	 * @return array<string, mixed>
	 * @throws VectorStoreException When JSON is malformed or not an object.
	 */
	private function decode_json( string $body, string $message ): array {
		try {
			$decoded = json_decode( $body, true, 32, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, $message );
		}
		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, $message );
		}

		return $decoded;
	}
}
