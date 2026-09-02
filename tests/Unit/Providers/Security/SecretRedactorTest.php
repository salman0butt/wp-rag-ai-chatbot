<?php
/**
 * Provider secret redactor tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Security;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Security\SecretRedactor;

/**
 * Verifies provider secret sanitization before diagnostics are exposed.
 */
final class SecretRedactorTest extends TestCase {
	/**
	 * Exact known secrets are removed everywhere they occur.
	 */
	public function test_sanitize_redacts_exact_known_secrets(): void {
		$this->require_redactor();
		$redactor = new SecretRedactor();

		$sanitized = $redactor->sanitize(
			'key=super-secret and repeated=super-secret',
			array( 'super-secret' )
		);

		self::assertSame( 'key=[REDACTED] and repeated=[REDACTED]', $sanitized );
	}

	/**
	 * Credential-bearing headers are recognized without case sensitivity.
	 */
	public function test_sanitize_redacts_mixed_case_credential_headers(): void {
		$this->require_redactor();
		$redactor = new SecretRedactor();
		$input    = "aUtHoRiZaTiOn: bEaReR bearer-secret\nApI-KeY: api-secret\nX-aPi-KeY: x-secret";

		$sanitized = $redactor->sanitize( $input );

		self::assertSame(
			"aUtHoRiZaTiOn: bEaReR [REDACTED]\nApI-KeY: [REDACTED]\nX-aPi-KeY: [REDACTED]",
			$sanitized
		);
	}

	/**
	 * Known-secret redaction also applies to response or request bodies.
	 */
	public function test_sanitize_body_redacts_secrets_without_truncating_short_body(): void {
		$this->require_redactor();
		$redactor = new SecretRedactor();

		self::assertSame(
			'{"error":"token [REDACTED] rejected"}',
			$redactor->sanitize_body( '{"error":"token body-secret rejected"}', array( 'body-secret' ) )
		);
	}

	/**
	 * Long bodies are byte-limited and visibly marked as truncated.
	 */
	public function test_sanitize_body_limits_raw_body_to_2048_bytes(): void {
		$this->require_redactor();
		$redactor = new SecretRedactor();
		$prefix   = str_repeat( 'a', 2048 );

		self::assertSame(
			$prefix . '[TRUNCATED]',
			$redactor->sanitize_body( $prefix . 'discarded-tail' )
		);
	}

	/**
	 * Secrets occurring only after the body limit can never leak through diagnostics.
	 */
	public function test_sanitize_body_never_exposes_secret_after_byte_limit(): void {
		$this->require_redactor();
		$redactor = new SecretRedactor();
		$secret   = 'secret-after-limit';
		$body     = str_repeat( 'b', 2048 ) . $secret;

		$sanitized = $redactor->sanitize_body( $body, array( $secret ) );

		self::assertStringNotContainsString( $secret, $sanitized );
		self::assertSame( str_repeat( 'b', 2048 ) . '[TRUNCATED]', $sanitized );
	}

	/**
	 * Blank known-secret entries do not alter otherwise safe text.
	 */
	public function test_sanitize_ignores_blank_known_secret_entries(): void {
		$this->require_redactor();
		$redactor = new SecretRedactor();

		self::assertSame( 'safe text', $redactor->sanitize( 'safe text', array( '', '   ' ) ) );
	}

	/**
	 * Require the intended missing-class RED before redactor implementation.
	 */
	private function require_redactor(): void {
		self::assertTrue(
			class_exists( SecretRedactor::class ),
			'SecretRedactor must exist before redaction behavior can pass.'
		);
	}
}
