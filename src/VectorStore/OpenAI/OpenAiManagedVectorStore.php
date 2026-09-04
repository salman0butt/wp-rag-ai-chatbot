<?php
/**
 * OpenAI managed vector-store adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\OpenAI;

use InvalidArgumentException;
use JsonException;
use Throwable;
use WpRagAiChatbot\Providers\Http\HttpRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\HttpTransport;
use WpRagAiChatbot\VectorStore\Managed\ManagedVectorMatch;
use WpRagAiChatbot\VectorStore\Managed\ManagedVectorSearchResult;
use WpRagAiChatbot\VectorStore\Managed\ManagedVectorStore;
use WpRagAiChatbot\VectorStore\VectorStoreCapabilities;
use WpRagAiChatbot\VectorStore\VectorStoreErrorCode;
use WpRagAiChatbot\VectorStore\VectorStoreException;
use WpRagAiChatbot\VectorStore\VectorStoreHealth;
use WpRagAiChatbot\VectorStore\VectorWriteResult;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exceptions are not rendered output.
/**
 * Maps truthful managed OpenAI vector-store operations without raw-vector emulation.
 */
final class OpenAiManagedVectorStore implements ManagedVectorStore {
	/** Fixed official OpenAI API origin. */
	private const API_BASE = 'https://api.openai.com/v1';

	/** Timeout for one bounded managed API request. */
	private const TIMEOUT_SECONDS = 10;

	/** Maximum public OpenAI vector-store search result count. */
	private const MAX_SEARCH_RESULTS = 50;

	/**
	 * Create the adapter without performing network I/O.
	 *
	 * @param OpenAiVectorStoreConfig $config Validated managed-store configuration.
	 * @param HttpTransport           $transport Single-send HTTP transport.
	 */
	public function __construct(
		private readonly OpenAiVectorStoreConfig $config,
		private readonly HttpTransport $transport
	) {
	}

	/** Return the stable store ID. */
	public function store_id(): string {
		return 'openai-managed';
	}

	/** Advertise only managed operations supported by the public API. */
	public function capabilities(): VectorStoreCapabilities {
		return VectorStoreCapabilities::managed();
	}

	/** Perform one explicit bounded vector-store retrieve request. */
	public function health(): VectorStoreHealth {
		try {
			$response = $this->send( 'GET', $this->store_path() );
			if ( ! $this->successful( $response ) ) {
				return VectorStoreHealth::unhealthy( 'OpenAI managed vector store is unavailable.' );
			}
			$data = $this->decode_json( $response->body, 'OpenAI managed vector-store response is invalid.' );
			if ( ( $data['id'] ?? null ) !== $this->config->vector_store_id || ( $data['object'] ?? null ) !== 'vector_store' ) {
				return VectorStoreHealth::unhealthy( 'OpenAI managed vector store is unavailable.' );
			}
			return VectorStoreHealth::healthy();
		} catch ( VectorStoreException ) {
			return VectorStoreHealth::unhealthy( 'OpenAI managed vector store is unavailable.' );
		}
	}

	/**
	 * Attach one existing OpenAI file to the configured managed vector store.
	 *
	 * @param string               $file_id OpenAI file ID.
	 * @param array<string, mixed> $attributes Bounded searchable attributes.
	 * @throws VectorStoreException When validation, transport, or the remote response fails.
	 */
	public function attach_file( string $file_id, array $attributes = array() ): VectorWriteResult {
		$this->assert_file_id( $file_id );
		try {
			ManagedVectorMatch::validate_attributes( $attributes );
		} catch ( InvalidArgumentException $exception ) {
			throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, $exception->getMessage() );
		}

		$body = array( 'file_id' => $file_id );
		if ( array() !== $attributes ) {
			$body['attributes'] = $attributes;
		}
		$response = $this->send( 'POST', $this->store_path() . '/files', $body );
		$this->require_success( $response, 'OpenAI managed file attachment failed.' );
		$data = $this->decode_json( $response->body, 'OpenAI managed file response is invalid.' );
		if ( ( $data['id'] ?? null ) !== $file_id || ( $data['object'] ?? null ) !== 'vector_store.file' ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'OpenAI managed file response is invalid.' );
		}

		return new VectorWriteResult( true );
	}

	/**
	 * Detach one file from the configured managed vector store.
	 *
	 * @param string $file_id OpenAI file ID.
	 * @throws VectorStoreException When validation, transport, or the remote response fails.
	 */
	public function delete_file( string $file_id ): VectorWriteResult {
		$this->assert_file_id( $file_id );
		$response = $this->send( 'DELETE', $this->store_path() . '/files/' . rawurlencode( $file_id ) );
		$this->require_success( $response, 'OpenAI managed file deletion failed.' );
		$data = $this->decode_json( $response->body, 'OpenAI managed file deletion response is invalid.' );
		if ( ( $data['id'] ?? null ) !== $file_id || true !== ( $data['deleted'] ?? null ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'OpenAI managed file deletion response is invalid.' );
		}

		return new VectorWriteResult( true );
	}

	/**
	 * Search provider-managed content with a text query.
	 *
	 * @param string $query Text query.
	 * @param int    $max_results Maximum number of managed results.
	 * @throws VectorStoreException When validation, transport, or the remote response fails.
	 */
	public function managed_search( string $query, int $max_results = 10 ): ManagedVectorSearchResult {
		$query = trim( $query );
		if ( '' === $query || strlen( $query ) > 16384 || $max_results < 1 || $max_results > self::MAX_SEARCH_RESULTS ) {
			throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, 'OpenAI managed search request is invalid.' );
		}

		$response = $this->send(
			'POST',
			$this->store_path() . '/search',
			array(
				'query'           => $query,
				'max_num_results' => $max_results,
			)
		);
		$this->require_success( $response, 'OpenAI managed vector-store search failed.' );
		$data = $this->decode_json( $response->body, 'OpenAI managed search response is invalid.' );
		$rows = $data['data'] ?? null;
		if ( ! is_array( $rows ) || ! array_is_list( $rows ) || count( $rows ) > $max_results ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'OpenAI managed search response is invalid.' );
		}

		$matches = array();
		foreach ( $rows as $row ) {
			$matches[] = $this->map_match( $row );
		}
		return new ManagedVectorSearchResult( $matches );
	}

	/**
	 * Convert one untrusted OpenAI search row into a bounded match.
	 *
	 * @param mixed $row Untrusted decoded search row.
	 * @throws VectorStoreException When the remote row is malformed or out of bounds.
	 */
	private function map_match( mixed $row ): ManagedVectorMatch {
		if ( ! is_array( $row ) || ! is_numeric( $row['score'] ?? null ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'OpenAI managed search response is invalid.' );
		}
		$file_id    = $row['file_id'] ?? null;
		$filename   = $row['filename'] ?? null;
		$attributes = $row['attributes'] ?? array();
		$content    = $row['content'] ?? null;
		if ( ! is_string( $file_id ) || ! is_string( $filename ) || ! is_array( $attributes ) || ! is_array( $content ) || ! array_is_list( $content ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'OpenAI managed search response is invalid.' );
		}

		$text = array();
		foreach ( $content as $part ) {
			if ( ! is_array( $part ) || 'text' !== ( $part['type'] ?? null ) || ! is_string( $part['text'] ?? null ) ) {
				throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'OpenAI managed search response is invalid.' );
			}
			$text[] = $part['text'];
		}

		try {
			return new ManagedVectorMatch( $file_id, $filename, (float) $row['score'], $text, $attributes );
		} catch ( InvalidArgumentException ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'OpenAI managed search response is invalid.' );
		}
	}

	/**
	 * Validate an OpenAI file ID before constructing a URL.
	 *
	 * @param string $file_id OpenAI file ID.
	 * @throws VectorStoreException When the file ID is unsafe or malformed.
	 */
	private function assert_file_id( string $file_id ): void {
		if ( 1 !== preg_match( '/^file[-_][A-Za-z0-9_-]{1,191}$/', $file_id ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::INVALID_REQUEST, 'OpenAI managed file ID is invalid.' );
		}
	}

	/** Return the fixed configured vector-store resource path. */
	private function store_path(): string {
		return '/vector_stores/' . rawurlencode( $this->config->vector_store_id );
	}

	/**
	 * Send exactly one fixed-origin OpenAI request.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $path Fixed API path.
	 * @param array<string, mixed>|null $body Optional JSON body.
	 * @throws VectorStoreException When transport execution fails.
	 */
	private function send( string $method, string $path, ?array $body = null ): HttpResponse {
		$request = new HttpRequest(
			'openai-managed',
			$method,
			self::API_BASE . $path,
			array(
				'Authorization' => 'Bearer ' . $this->config->api_key,
				'Content-Type'  => 'application/json',
			),
			$body,
			self::TIMEOUT_SECONDS,
			0
		);
		try {
			return $this->transport->send( $request );
		} catch ( Throwable ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, 'OpenAI managed vector-store request failed.' );
		}
	}

	/**
	 * Require one successful remote response without exposing the body.
	 *
	 * @param HttpResponse $response Remote response.
	 * @param string       $message Sanitized failure message.
	 * @throws VectorStoreException When the response status is not successful.
	 */
	private function require_success( HttpResponse $response, string $message ): void {
		if ( ! $this->successful( $response ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, $message );
		}
	}

	/**
	 * Return whether a response is successful.
	 *
	 * @param HttpResponse $response Remote response.
	 */
	private function successful( HttpResponse $response ): bool {
		return $response->status >= 200 && $response->status < 300;
	}

	/**
	 * Decode an untrusted JSON body into an object map.
	 *
	 * @param string $body Untrusted response body.
	 * @param string $message Sanitized invalid-response message.
	 * @return array<string, mixed>
	 * @throws VectorStoreException When JSON cannot be decoded as an object map.
	 */
	private function decode_json( string $body, string $message ): array {
		try {
			$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, $message );
		}
		if ( ! is_array( $data ) ) {
			throw new VectorStoreException( VectorStoreErrorCode::OPERATION_FAILED, $message );
		}
		return $data;
	}
}
