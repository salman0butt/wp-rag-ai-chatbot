<?php
/**
 * Qdrant adapter configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Qdrant;

use InvalidArgumentException;

/**
 * Validated administrator-owned Qdrant endpoint and secret.
 */
final class QdrantConfig {
	/** Maximum accepted API-key length. */
	private const MAX_API_KEY_LENGTH = 4096;

	/**
	 * Create validated Qdrant configuration.
	 *
	 * @param string $endpoint Administrator-owned Qdrant HTTPS origin.
	 * @param string $api_key Qdrant API key.
	 * @throws InvalidArgumentException When configuration is unsafe or invalid.
	 */
	public function __construct(
		public readonly string $endpoint,
		private readonly string $api_key
	) {
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
			throw new InvalidArgumentException( 'Qdrant endpoint must be a fixed HTTPS origin.' );
		}

		if ( '' === trim( $api_key ) || strlen( $api_key ) > self::MAX_API_KEY_LENGTH ) {
			throw new InvalidArgumentException( 'Qdrant API key is invalid.' );
		}
	}

	/**
	 * Return the API key for server-side request construction.
	 */
	public function api_key(): string {
		return $this->api_key;
	}

	/**
	 * Return endpoint without a trailing slash.
	 */
	public function base_url(): string {
		return rtrim( $this->endpoint, '/' );
	}
}
