<?php
/**
 * Chroma adapter configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Chroma;

use InvalidArgumentException;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exceptions are not rendered output.
/**
 * Validated administrator-owned Chroma endpoint, scope, and optional token.
 */
final class ChromaConfig {
	/** Maximum accepted authentication token length. */
	private const MAX_TOKEN_LENGTH = 4096;

	/** Maximum accepted tenant/database component length. */
	private const MAX_SCOPE_LENGTH = 191;

	/**
	 * Create validated Chroma configuration.
	 *
	 * @param string      $endpoint Administrator-owned Chroma HTTPS origin.
	 * @param string      $tenant Chroma tenant name.
	 * @param string      $database Chroma database name.
	 * @param string|null $token Optional server-side Chroma token.
	 * @throws InvalidArgumentException When configuration is unsafe or invalid.
	 */
	public function __construct(
		public readonly string $endpoint,
		public readonly string $tenant,
		public readonly string $database,
		private readonly ?string $token = null
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
			throw new InvalidArgumentException( 'Chroma endpoint must be a fixed HTTPS origin.' );
		}

		$this->assert_scope_component( $tenant, 'tenant' );
		$this->assert_scope_component( $database, 'database' );

		if ( null !== $token && ( '' === trim( $token ) || strlen( $token ) > self::MAX_TOKEN_LENGTH ) ) {
			throw new InvalidArgumentException( 'Chroma token is invalid.' );
		}
	}

	/** Return endpoint without a trailing slash. */
	public function base_url(): string {
		return rtrim( $this->endpoint, '/' );
	}

	/** Return the optional token for server-side request construction. */
	public function token(): ?string {
		return $this->token;
	}

	/**
	 * Require one safe path scope component.
	 *
	 * @param string $value Scope value.
	 * @param string $name Scope label for normalized diagnostics.
	 * @throws InvalidArgumentException When the component is unsafe.
	 */
	private function assert_scope_component( string $value, string $name ): void {
		if (
			'' === $value ||
			strlen( $value ) > self::MAX_SCOPE_LENGTH ||
			1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value )
		) {
			throw new InvalidArgumentException( sprintf( 'Chroma %s is invalid.', $name ) );
		}
	}
}
