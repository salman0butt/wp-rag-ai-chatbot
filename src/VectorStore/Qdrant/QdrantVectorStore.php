<?php
/**
 * Qdrant raw-vector adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Qdrant;

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
 * Maps the portable raw-vector contracts to Qdrant's HTTP API.
 */
final class QdrantVectorStore implements VectorUpsertStore, VectorDeleteStore, VectorSearchStore {
	/** HTTP timeout for one bounded adapter request. */
	private const TIMEOUT_SECONDS = 10;

	/** Reserved payload key containing the plugin stable vector ID. */
	private const PAYLOAD_ID = '_wp_rag_id';

	/** Reserved payload key containing the compatibility fingerprint. */
	private const PAYLOAD_FINGERPRINT = '_wp_rag_fingerprint';

	/**
	 * Create the Qdrant adapter without performing network I/O.
	 *
	 * @param QdrantConfig       $config Validated remote configuration.
	 * @param VectorIndexProfile $profile Required vector index profile.
	 * @param HttpTransport      $transport Single-send HTTP transport.
	 */
	public function __construct(
		private readonly QdrantConfig $config,
		private readonly VectorIndexProfile $profile,
		private readonly HttpTransport $transport
	) {
	}

	/** Return the stable store ID. */
	public function store_id(): string {
		return 'qdrant';
	}

	/** Return truthful raw-vector capabilities. */
	public function capabilities(): VectorStoreCapabilities {
		return VectorStoreCapabilities::all();
	}

	/** Perform one explicit bounded Qdrant health request. */
	public function health(): VectorStoreHealth {
		try {
			$response = $this->send( 'GET', '/healthz' );
			if ( $this->successful( $response ) ) {
				return VectorStoreHealth::healthy();
			}
		} catch ( VectorStoreException ) {
			// Health is represented as state rather than an exception.
		}

		return VectorStoreHealth::unhealthy( 'Qdrant is unavailable.' );
	}

	/**
	 * Insert or replace one plugin stable vector record.
	 *
	 * @param VectorRecord $record Record to write.
	 * @throws VectorStoreException When profile validation, transport, or the remote operation fails.
	 */
	public function upsert( VectorRecord $record ): VectorWriteResult {
		$this->assert_collection_profile( $record->collection );
		$this->assert_remote_profile( $record->collection );

		$payload = array_merge(
			array(
				self::PAYLOAD_ID          => $record->id,
				self::PAYLOAD_FINGERPRINT => $record->compatibility_fingerprint,
			),
			$record->metadata
		);
		$response = $this->send(
			'PUT',
			$this->collection_path( $record->collection ) . '/points?wait=true',
			array(
				'points' => array(
					array(
						'id'      => $this->point_id( $record->collection, $record->id ),
						'vector'  => $record->values,
						'payload' => $payload,
					),
				),
			)
		);
		$this->require_success( $response, 'Qdrant vector upsert failed.' );

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
			throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, 'Qdrant vector record ID is invalid.' );
		}

		$this->assert_collection_profile( $collection );
		$this->assert_remote_profile( $collection );
		$response = $this->send(
			'POST',
			$this->collection_path( $collection ) . '/points/delete?wait=true',
			array( 'points' => array( $this->point_id( $collection, $id ) ) )
		);
		$this->require_success( $response, 'Qdrant vector delete failed.' );

		return new VectorWriteResult( true );
	}

	/**
	 * Search one compatibility-isolated Qdrant collection.
	 *
	 * @param VectorSearchRequest $request Portable vector search request.
	 * @throws VectorStoreException When validation, mapping, transport, or the remote response fails.
	 */
	public function search( VectorSearchRequest $request ): VectorSearchResult {
		$this->assert_collection_profile( $request->collection );
		$must = array(
			array(
				'key'   => self::PAYLOAD_FINGERPRINT,
				'match' => array( 'value' => $request->compatibility_fingerprint ),
			),
		);
		if ( null !== $request->filter ) {
			$must = array_merge( $must, $this->filter_conditions( $request->filter ) );
		}

		$this->assert_remote_profile( $request->collection );
		$response = $this->send(
			'POST',
			$this->collection_path( $request->collection ) . '/points/query',
			array(
				'query'        => $request->vector,
				'filter'       => array( 'must' => $must ),
				'limit'        => $request->top_k,
				'with_payload' => true,
				'with_vector'  => false,
			)
		);
		$this->require_success( $response, 'Qdrant vector search failed.' );

		$data   = $this->decode_json( $response->body, 'Qdrant search response is invalid.' );
		$points = $data['result']['points'] ?? null;
		if ( ! is_array( $points ) || ! array_is_list( $points ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Qdrant search response is invalid.' );
		}

		$matches = array();
		foreach ( $points as $point ) {
			$matches[] = $this->match_from_point( $point );
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
	 * Fail before network execution when a logical collection does not match adapter configuration.
	 *
	 * @param VectorCollection $collection Collection to validate.
	 * @throws VectorStoreException When the configured profile differs.
	 */
	private function assert_collection_profile( VectorCollection $collection ): void {
		if ( ! hash_equals( $this->profile->fingerprint(), $collection->profile->fingerprint() ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::INCOMPATIBLE_PROFILE, 'Qdrant vector collection profile is incompatible.' );
		}
	}

	/**
	 * Verify Qdrant native collection dimensions and metric before operations.
	 *
	 * @param VectorCollection $collection Collection to inspect.
	 * @throws VectorStoreException When the collection is unavailable, malformed, or incompatible.
	 */
	private function assert_remote_profile( VectorCollection $collection ): void {
		$response = $this->send( 'GET', $this->collection_path( $collection ) );
		if ( 404 === $response->status ) {
			throw new VectorStoreException( VectorStoreErrorCode::UNAVAILABLE, 'Qdrant vector collection is unavailable.' );
		}
		$this->require_success( $response, 'Qdrant vector collection could not be inspected.' );
		$data    = $this->decode_json( $response->body, 'Qdrant collection response is invalid.' );
		$vectors = $data['result']['config']['params']['vectors'] ?? null;
		if ( ! is_array( $vectors ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Qdrant collection response is invalid.' );
		}

		$size     = $vectors['size'] ?? null;
		$distance = $vectors['distance'] ?? null;
		if (
			! is_int( $size ) ||
			! is_string( $distance ) ||
			$this->profile->embedding->dimensions !== $size ||
			$this->qdrant_distance() !== $distance
		) {
			throw new VectorStoreException( VectorStoreErrorCode::INCOMPATIBLE_PROFILE, 'Qdrant vector collection profile is incompatible.' );
		}
	}

	/**
	 * Map the portable filter AST to bounded Qdrant conditions.
	 *
	 * @param VectorFilter $filter Portable filter.
	 * @return list<array<string, mixed>>
	 * @throws VectorStoreException When the filter is unsupported.
	 */
	private function filter_conditions( VectorFilter $filter ): array {
		if ( $filter instanceof EqualsFilter ) {
			return array(
				array(
					'key'   => $filter->key,
					'match' => array( 'value' => $filter->value ),
				),
			);
		}
		if ( $filter instanceof InFilter ) {
			return array(
				array(
					'key'   => $filter->key,
					'match' => array( 'any' => $filter->values ),
				),
			);
		}
		if ( $filter instanceof AndFilter ) {
			$conditions = array();
			foreach ( $filter->filters as $child ) {
				$conditions = array_merge( $conditions, $this->filter_conditions( $child ) );
			}
			return $conditions;
		}

		throw new VectorStoreException( VectorStoreErrorCode::UNSUPPORTED_CAPABILITY, 'Qdrant vector filter is unsupported.' );
	}

	/**
	 * Convert one untrusted Qdrant query point into a portable match.
	 *
	 * @param mixed $point Untrusted decoded point.
	 * @throws VectorStoreException When the point payload is malformed.
	 */
	private function match_from_point( mixed $point ): VectorMatch {
		if ( ! is_array( $point ) || ! isset( $point['score'] ) || ! is_numeric( $point['score'] ) || ! isset( $point['payload'] ) || ! is_array( $point['payload'] ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Qdrant search response is invalid.' );
		}

		$payload = $point['payload'];
		$id      = $payload[ self::PAYLOAD_ID ] ?? null;
		if ( ! is_string( $id ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Qdrant search response is invalid.' );
		}

		unset( $payload[ self::PAYLOAD_ID ], $payload[ self::PAYLOAD_FINGERPRINT ] );
		try {
			return new VectorMatch( $id, (float) $point['score'], $payload );
		} catch ( \InvalidArgumentException ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Qdrant search response is invalid.' );
		}
	}

	/**
	 * Return a profile-isolated physical collection path.
	 *
	 * @param VectorCollection $collection Logical collection.
	 */
	private function collection_path( VectorCollection $collection ): string {
		$name = $collection->id . '-' . substr( $collection->profile->fingerprint(), 0, 16 );
		return '/collections/' . rawurlencode( $name );
	}

	/**
	 * Derive a deterministic UUIDv5-shaped Qdrant point ID from plugin identity.
	 *
	 * @param VectorCollection $collection Collection boundary.
	 * @param string           $id Plugin stable vector ID.
	 */
	private function point_id( VectorCollection $collection, string $id ): string {
		$hex     = substr( hash( 'sha256', $this->collection_path( $collection ) . "\0" . $id ), 0, 32 );
		$hex[12] = '5';
		$variant = ( hexdec( $hex[16] ) & 0x3 ) | 0x8;
		$hex[16] = dechex( $variant );

		return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20, 12 );
	}

	/** Return Qdrant canonical metric identifier for the configured profile. */
	private function qdrant_distance(): string {
		return match ( $this->profile->distance ) {
			DistanceMetric::COSINE      => 'Cosine',
			DistanceMetric::DOT_PRODUCT => 'Dot',
		};
	}

	/**
	 * Send exactly one request through the injected transport.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $path Fixed adapter-owned path.
	 * @param array<string, mixed>|null $body Optional JSON body.
	 * @throws VectorStoreException When transport execution fails.
	 */
	private function send( string $method, string $path, ?array $body = null ): HttpResponse {
		$request = new HttpRequest(
			'qdrant',
			$method,
			$this->config->base_url() . $path,
			array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'api-key'      => $this->config->api_key(),
			),
			$body,
			self::TIMEOUT_SECONDS,
			0
		);

		try {
			return $this->transport->send( $request );
		} catch ( Throwable ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Qdrant request failed.' );
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
	 * Require a successful response without surfacing opaque provider bodies.
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
