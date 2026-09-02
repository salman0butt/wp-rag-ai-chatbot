<?php
/**
 * Secret value-object tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Credentials;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\Secret;

/**
 * Verifies that provider secrets do not leak through normal serialization.
 */
final class SecretTest extends TestCase {
	/**
	 * Plaintext is available only inside the explicit callback boundary.
	 */
	public function test_with_value_exposes_plaintext_only_inside_callback_and_returns_void(): void {
		$this->require_secret();

		$observed = null;
		$secret   = new Secret( 'sk-test-super-secret' );
		$result   = $secret->with_value(
			static function ( string $plaintext ) use ( &$observed ): void {
				$observed = $plaintext;
			}
		);

		self::assertNull( $result );
		self::assertSame( 'sk-test-super-secret', $observed );
	}

	/**
	 * Normal string and JSON serialization return only the redaction marker.
	 */
	public function test_string_and_json_serialization_never_expose_plaintext(): void {
		$this->require_secret();

		$secret = new Secret( 'sk-test-super-secret' );

		self::assertSame( '[REDACTED]', (string) $secret );
		self::assertSame( '"[REDACTED]"', json_encode( $secret, JSON_THROW_ON_ERROR ) );
		self::assertStringNotContainsString( 'sk-test-super-secret', print_r( $secret, true ) );
		self::assertStringContainsString( '[REDACTED]', print_r( $secret, true ) );
	}

	/**
	 * Require the intended missing-class RED before implementation.
	 */
	private function require_secret(): void {
		self::assertTrue(
			class_exists( Secret::class ),
			'Secret must exist before secret redaction behavior can pass.'
		);
	}
}
