<?php
/**
 * Pinecone raw-vector adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Pinecone;

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
 * Maps the portable raw-vector contracts to Pinecone's HTTP API.
 */
final class PineconeVectorStore implements VectorUpsertStore, VectorDeleteStore, VectorSearchStore {
	/** HTTP timeout for one bounded adapter request. */
	private const TIMEOUT_SECONDS = 10;

	/** Reserved metadata key containing the compatibility fingerprint. */
	private const METADATA_FINGERPRINT = '_wp_rag_fingerprint';

	/**
	 * Create the Pinecone adapter without performing network I/O.
	 *
	 * @param PineconeConfig     $config Validated remote configuration.
	 * @param VectorIndexProfile $profile Required vector index profile.
	 * @param HttpTransport      $transport Single-send HTTP transport.
	 */
	public function __construct(
		private readonly PineconeConfig $config,
		private readonly VectorIndexProfile $profile,
		private readonly HttpTransport $transport
	) {
	}

	/** Return the stable store ID. */
	public function store_id(): string {
		return 'pinecone';
	}

	/** Return truthful raw-vector capabilities. */
	public function capabilities(): VectorStoreCapabilities {
		return VectorStoreCapabilities::all();
	}

	/** Perform one explicit bounded Pinecone health request. */
	public function health(): VectorStoreHealth {
		try {
			$this->inspect_index();
			return VectorStoreHealth::healthy();
		} catch ( VectorStoreException ) {
			return VectorStoreHealth::unhealthy( 'Pinecone is unavailable.' );
		}
	}

	/**
	 * Insert or replace one plugin stable vector record.
	 *
	 * @param VectorRecord $record Record to write.
	 * @throws VectorStoreException When profile validation, transport, or the remote operation fails.
	 */
	public function upsert( VectorRecord $record ): VectorWriteResult {
		$this->assert_collection_profile( $record->collection );
		$this->assert_remote_profile();

		$metadata = array_merge(
			array( self::METADATA_FINGERPRINT => $record->compatibility_fingerprint ),
			$record->metadata
		);
		$response = $this->send_data(
			'POST',
			'/vectors/upsert',
			array(
				'namespace' => $this->namespace_id( $record->collection ),
				'vectors'   => array(
					array(
						'id'       => $record->id,
						'values'   => $record->values,
						'metadata' => $metadata,
					),
				),
			)
		);
		$this->require_success( $response, 'Pinecone vector upsert failed.' );

		return new VectorWriteResult( true );
	}

	/**
	 * Delete one plugin stable ID from one compatibility-isolated namespace.
	 *
	 * @param VectorCollection $collection Collection boundary.
	 * @param string           $id Plugin stable vector ID.
	 * @throws VectorStoreException When the ID/profile, transport, or remote operation is invalid.
	 */
	public function delete( VectorCollection $collection, string $id ): VectorWriteResult {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,191}$/', $id ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, 'Pinecone vector record ID is invalid.' );
		}

		$this->assert_collection_profile( $collection );
		$this->assert_remote_profile();
		$response = $this->send_data(
			'POST',
			'/vectors/delete',
			array(
				'ids'       => array( $id ),
				'namespace' => $this->namespace_id( $collection ),
			)
		);
		$this->require_success( $response, 'Pinecone vector delete failed.' );

		return new VectorWriteResult( true );
	}

	/**
	 * Search one compatibility-isolated Pinecone namespace.
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

		$this->assert_remote_profile();
		$response = $this->send_data(
			'POST',
			'/query',
			array(
				'namespace'       => $this->namespace_id( $request->collection ),
				'vector'          => $request->vector,
				'topK'            => $request->top_k,
				'includeMetadata' => true,
				'includeValues'   => false,
				'filter'          => array( '$and' => $conditions ),
			)
		);
		$this->require_success( $response, 'Pinecone vector search failed.' );

		$data    = $this->decode_json( $response->body, 'Pinecone query response is invalid.' );
		$matches = $data['matches'] ?? null;
		if ( ! is_array( $matches ) || ! array_is_list( $matches ) || count( $matches ) > $request->top_k ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Pinecone query response is invalid.' );
		}

		$portable_matches = array();
		foreach ( $matches as $match ) {
			$portable_matches[] = $this->match_from_response( $match, $request->compatibility_fingerprint );
		}
		usort(
			$portable_matches,
			static function ( VectorMatch $left, VectorMatch $right ): int {
				$score_order = $right->score <=> $left->score;
				return 0 !== $score_order ? $score_order : strcmp( $left->id, $right->id );
			}
		);

		return new VectorSearchResult( $portable_matches );
	}

	/**
	 * Fail before network execution when a logical collection does not match adapter configuration.
	 *
	 * @param VectorCollection $collection Collection to validate.
	 * @throws VectorStoreException When the configured profile differs.
	 */
	private function assert_collection_profile( VectorCollection $collection ): void {
		if ( ! hash_equals( $this->profile->fingerprint(), $collection->profile->fingerprint() ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::INCOMPATIBLE_PROFILE, 'Pinecone vector collection profile is incompatible.' );
		}
	}

	/**
	 * Verify Pinecone index dimensions, metric, and data-plane host before operations.
	 *
	 * @throws VectorStoreException When the index is unavailable, malformed, or incompatible.
	 */
	private function assert_remote_profile(): void {
		$data      = $this->inspect_index();
		$name      = $data['name'] ?? null;
		$dimension = $data['dimension'] ?? null;
		$metric    = $data['metric'] ?? null;
		$host      = $data['host'] ?? null;
		if (
			! is_string( $name ) ||
			! is_int( $dimension ) ||
			! is_string( $metric ) ||
			! is_string( $host ) ||
			$this->config->index_name !== $name ||
			$this->profile->embedding->dimensions !== $dimension ||
			$this->pinecone_metric() !== strtolower( $metric ) ||
			$this->config->data_host() !== strtolower( $host )
		) {
			throw new VectorStoreException( VectorStoreErrorCode::INCOMPATIBLE_PROFILE, 'Pinecone index profile is incompatible.' );
		}
	}

	/**
	 * Inspect the configured Pinecone index through one fixed control-plane request.
	 *
	 * @return array<string, mixed>
	 * @throws VectorStoreException When the index cannot be inspected safely.
	 */
	private function inspect_index(): array {
		$response = $this->send_url( 'GET', $this->config->index_description_url() );
		if ( 404 === $response->status ) {
			throw new VectorStoreException( VectorStoreErrorCode::UNAVAILABLE, 'Pinecone index is unavailable.' );
		}
		$this->require_success( $response, 'Pinecone index could not be inspected.' );
		return $this->decode_json( $response->body, 'Pinecone index response is invalid.' );
	}

	/**
	 * Map the portable filter AST to bounded Pinecone conditions.
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

		throw new VectorStoreException( VectorStoreErrorCode::UNSUPPORTED_CAPABILITY, 'Pinecone vector filter is unsupported.' );
	}

	/**
	 * Convert one untrusted Pinecone query match into a portable match.
	 *
	 * @param mixed  $match Untrusted decoded match.
	 * @param string $expected_fingerprint Expected compatibility fingerprint.
	 * @throws VectorStoreException When the match is malformed or incompatible.
	 */
	private function match_from_response( mixed $match, string $expected_fingerprint ): VectorMatch {
		if (
			! is_array( $match ) ||
			! isset( $match['id'] ) ||
			! is_string( $match['id'] ) ||
			! isset( $match['score'] ) ||
			! is_numeric( $match['score'] ) ||
			! isset( $match['metadata'] ) ||
			! is_array( $match['metadata'] )
		) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Pinecone query response is invalid.' );
		}

		$metadata    = $match['metadata'];
		$fingerprint = $metadata[ self::METADATA_FINGERPRINT ] ?? null;
		if ( ! is_string( $fingerprint ) || ! hash_equals( $expected_fingerprint, $fingerprint ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Pinecone query response is invalid.' );
		}
		unset( $metadata[ self::METADATA_FINGERPRINT ] );

		try {
			return new VectorMatch( $match['id'], (float) $match['score'], $metadata );
		} catch ( InvalidArgumentException ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Pinecone query response is invalid.' );
		}
	}

	/**
	 * Return a profile-isolated Pinecone namespace.
	 *
	 * @param VectorCollection $collection Logical collection.
	 */
	private function namespace_id( VectorCollection $collection ): string {
		return $collection->id . '-' . substr( $collection->profile->fingerprint(), 0, 16 );
	}

	/** Return Pinecone canonical metric identifier for the configured profile. */
	private function pinecone_metric(): string {
		return match ( $this->profile->distance ) {
			DistanceMetric::COSINE      => 'cosine',
			DistanceMetric::DOT_PRODUCT => 'dotproduct',
		};
	}

	/**
	 * Send one data-plane request through the injected transport.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $path Fixed adapter-owned path.
	 * @param array<string, mixed>|null $body Optional JSON body.
	 * @throws VectorStoreException When transport execution fails.
	 */
	private function send_data( string $method, string $path, ?array $body = null ): HttpResponse {
		return $this->send_url( $method, $this->config->base_url() . $path, $body );
	}

	/**
	 * Send exactly one request through the injected transport.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $url Adapter-owned fixed URL.
	 * @param array<string, mixed>|null $body Optional JSON body.
	 * @throws VectorStoreException When transport execution fails.
	 */
	private function send_url( string $method, string $url, ?array $body = null ): HttpResponse {
		$request = new HttpRequest(
			'pinecone',
			$method,
			$url,
			array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'Api-Key'      => $this->config->api_key(),
			),
			$body,
			self::TIMEOUT_SECONDS,
			0
		);

		try {
			return $this->transport->send( $request );
		} catch ( Throwable ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'Pinecone request failed.' );
		}
	}

	/** Determine whether an HTTP response is successful. */
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
