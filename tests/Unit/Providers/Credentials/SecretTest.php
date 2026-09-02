<?php
/**
 * Secret value-object tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Credentials;

use PHPUnit\Framework\TestCase;
use Throwable;
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
		$json   = json_encode( $secret );

		self::assertSame( '[REDACTED]', (string) $secret );
		self::assertSame( '[REDACTED]', $secret->jsonSerialize() );
		self::assertSame( array( 'value' => '[REDACTED]' ), $debug );
		self::assertNotContains( 'sk-test-super-secret', $debug );
		self::assertIsString( $json );
		self::assertStringNotContainsString( 'sk-test-super-secret', $json );
	}

	/**
	 * PHP export and native serialization surfaces must never expose plaintext.
	 */
	public function test_export_and_native_serialization_never_expose_plaintext(): void {
		$this->require_secret();

		$plaintext = 'sk-test-export-super-secret';
		$secret    = new Secret( $plaintext );
		$exported  = var_export( $secret, true );

		self::assertStringNotContainsString( $plaintext, $exported );

		try {
			$serialized = serialize( $secret );
			self::assertStringNotContainsString( $plaintext, $serialized );
		} catch ( Throwable $exception ) {
			self::assertStringNotContainsString( $plaintext, $exception->getMessage() );
		}
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
