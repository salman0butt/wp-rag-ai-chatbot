<?php
/**
 * Provider diagnostic secret redactor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Security;

/**
 * Removes credential material before provider diagnostics are exposed.
 */
final class SecretRedactor {
	private const REDACTED         = '[REDACTED]';
	private const TRUNCATED        = '[TRUNCATED]';
	private const BODY_BYTE_LIMIT  = 2048;

	/**
	 * Sanitize provider diagnostic text.
	 *
	 * @param string $text Diagnostic text.
	 * @param array<mixed> $known_secrets Known plaintext secrets.
	 */
	public function sanitize( string $text, array $known_secrets = array() ): string {
		$sanitized = preg_replace(
			'/^(\s*authorization\s*:\s*bearer\s+)[^\r\n]*$/im',
			'$1' . self::REDACTED,
			$text
		);
		$sanitized = is_string( $sanitized ) ? $sanitized : $text;

		$header_sanitized = preg_replace(
			'/^(\s*(?:api-key|x-api-key)\s*:\s*)[^\r\n]*$/im',
			'$1' . self::REDACTED,
			$sanitized
		);
		$sanitized        = is_string( $header_sanitized ) ? $header_sanitized : $sanitized;

		foreach ( $known_secrets as $secret ) {
			if ( ! is_string( $secret ) || '' === trim( $secret ) ) {
				continue;
			}

			$sanitized = str_replace( $secret, self::REDACTED, $sanitized );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a raw provider body within the diagnostic byte limit.
	 *
	 * @param string $body Raw provider body.
	 * @param array<mixed> $known_secrets Known plaintext secrets.
	 */
	public function sanitize_body( string $body, array $known_secrets = array() ): string {
		$truncated = strlen( $body ) > self::BODY_BYTE_LIMIT;
		$limited   = $truncated ? substr( $body, 0, self::BODY_BYTE_LIMIT ) : $body;
		$sanitized = $this->sanitize( $limited, $known_secrets );

		return $truncated ? $sanitized . self::TRUNCATED : $sanitized;
	}
}
