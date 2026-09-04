<?php
/**
 * Pinecone adapter configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Pinecone;

use InvalidArgumentException;

/**
 * Validated administrator-owned Pinecone data-plane endpoint and secret.
 */
final class PineconeConfig {
	/** Maximum accepted API-key length. */
	private const MAX_API_KEY_LENGTH = 4096;

	/**
	 * Create validated Pinecone configuration.
	 *
	 * @param string $endpoint Administrator-owned Pinecone HTTPS data-plane origin.
	 * @param string $api_key Pinecone API key.
	 * @param string $index_name Pinecone index name used for fixed control-plane inspection.
	 * @throws InvalidArgumentException When configuration is unsafe or invalid.
	 */
	public function __construct(
		public readonly string $endpoint,
		private readonly string $api_key,
		public readonly string $index_name
	) {
		// This value object is exercised outside a bootstrapped WordPress runtime.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Native parsing preserves the pure configuration boundary.
		$parts = parse_url( $endpoint );
		if (
			false === filter_var( $endpoint, FILTER_VALIDATE_URL ) ||
			! is_array( $parts ) ||
			'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ||
			'' === (string) ( $parts['host'] ?? '' ) ||
			isset( $parts['user'] ) ||
			isset( $parts['pass'] ) ||
			isset( $parts['query'] ) ||
			isset( $parts['fragment'] ) ||
			( isset( $parts['path'] ) && '' !== $parts['path'] && '/' !== $parts['path'] )
		) {
			throw new InvalidArgumentException( 'Pinecone endpoint must be a fixed HTTPS origin.' );
		}

		if ( '' === trim( $api_key ) || strlen( $api_key ) > self::MAX_API_KEY_LENGTH ) {
			throw new InvalidArgumentException( 'Pinecone API key is invalid.' );
		}

		if ( 1 !== preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,43}[a-z0-9])?$/', $index_name ) ) {
			throw new InvalidArgumentException( 'Pinecone index name is invalid.' );
		}
	}

	/** Return the API key for server-side request construction. */
	public function api_key(): string {
		return $this->api_key;
	}

	/** Return data-plane endpoint without a trailing slash. */
	public function base_url(): string {
		return rtrim( $this->endpoint, '/' );
	}

	/** Return fixed Pinecone control-plane index description URL. */
	public function index_description_url(): string {
		return 'https://api.pinecone.io/indexes/' . rawurlencode( $this->index_name );
	}

	/** Return configured data-plane host for remote identity verification. */
	public function data_host(): string {
		// Constructor validation guarantees a host is present.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Native parsing preserves the pure configuration boundary.
		$host = parse_url( $this->endpoint, PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}
}
