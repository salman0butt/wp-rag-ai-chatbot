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
	 * Normal string, JSON, and debug serialization expose only redacted data.
	 */
	public function test_string_json_and_debug_serialization_never_expose_plaintext(): void {
		$this->require_secret();

		$secret = new Secret( 'sk-test-super-secret' );
		$debug  = $secret->__debugInfo();

		self::assertSame( '[REDACTED]', (string) $secret );
		self::assertSame( '[REDACTED]', $secret->jsonSerialize() );
		self::assertSame( array( 'value' => '[REDACTED]' ), $debug );
		self::assertNotContains( 'sk-test-super-secret', $debug, true );
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
