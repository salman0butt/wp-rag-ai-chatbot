<?php
/**
 * Authenticated provider credential cipher tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Credentials;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\AuthenticatedCredentialCipher;
use WpRagAiChatbot\Providers\Credentials\CredentialCipher;
use WpRagAiChatbot\Providers\Credentials\CryptoCapabilities;
use WpRagAiChatbot\Providers\Credentials\RuntimeCryptoCapabilities;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Verifies authenticated encryption and fail-closed credential envelopes.
 */
final class AuthenticatedCredentialCipherTest extends TestCase {
	/**
	 * Start Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_salt' )->alias(
			static fn ( string $scheme ): string => 'unit-test-' . $scheme . '-salt'
		);
	}

	/**
	 * Tear Brain Monkey down after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Sodium is preferred and produces a provider-bound authenticated envelope.
	 */
	public function test_sodium_round_trip_when_available(): void {
		$this->require_cipher_contracts();
		if ( ! function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			self::markTestSkipped( 'Sodium is unavailable in this PHP runtime.' );
		}

		$capabilities = $this->capabilities( true, true );
		$cipher       = new AuthenticatedCredentialCipher( $capabilities );
		$envelope     = $cipher->encrypt( ProviderIds::OPENAI_DIRECT, 'sk-sodium-secret' );
		$data         = $this->decode_envelope( $envelope );

		self::assertSame( 1, $data['v'] );
		self::assertSame( 'xchacha20poly1305', $data['alg'] );
		self::assertArrayHasKey( 'nonce', $data );
		self::assertArrayHasKey( 'ciphertext', $data );
		self::assertArrayNotHasKey( 'tag', $data );
		self::assertStringNotContainsString( 'sk-sodium-secret', $envelope );
		self::assertSame( 'sk-sodium-secret', $cipher->decrypt( ProviderIds::OPENAI_DIRECT, $envelope ) );
	}

	/**
	 * AES-256-GCM is used when Sodium is disabled and still round-trips safely.
	 */
	public function test_forced_aes_gcm_round_trip(): void {
		$this->require_cipher_contracts();
		if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			self::markTestSkipped( 'AES-256-GCM is unavailable in this PHP runtime.' );
		}

		$cipher   = new AuthenticatedCredentialCipher( $this->capabilities( false, true ) );
		$envelope = $cipher->encrypt( ProviderIds::OPENROUTER_DIRECT, 'or-aes-secret' );
		$data     = $this->decode_envelope( $envelope );

		self::assertSame( 'aes-256-gcm', $data['alg'] );
		self::assertArrayHasKey( 'tag', $data );
		self::assertStringNotContainsString( 'or-aes-secret', $envelope );
		self::assertSame( 'or-aes-secret', $cipher->decrypt( ProviderIds::OPENROUTER_DIRECT, $envelope ) );
	}

	/**
	 * Authenticated ciphertext cannot be modified without a configuration failure.
	 */
	public function test_tampered_ciphertext_is_rejected(): void {
		$this->require_cipher_contracts();
		$cipher   = $this->available_cipher();
		$envelope = $cipher->encrypt( ProviderIds::OPENAI_DIRECT, 'tamper-secret' );

		$this->expect_configuration_exception();
		$cipher->decrypt( ProviderIds::OPENAI_DIRECT, $this->tamper_ciphertext( $envelope ) );
	}

	/**
	 * Provider-specific KDF/AAD context prevents cross-provider credential reuse.
	 */
	public function test_provider_context_mismatch_is_rejected(): void {
		$this->require_cipher_contracts();
		$cipher   = $this->available_cipher();
		$envelope = $cipher->encrypt( ProviderIds::OPENAI_DIRECT, 'provider-bound-secret' );

		$this->expect_configuration_exception();
		$cipher->decrypt( ProviderIds::OPENROUTER_DIRECT, $envelope );
	}

	/**
	 * Malformed, unsupported, and unauthenticated envelopes fail closed.
	 */
	public function test_malformed_envelopes_are_rejected(): void {
		$this->require_cipher_contracts();
		$cipher = $this->available_cipher();

		foreach ( array(
			'not-json',
			'{"v":2,"alg":"aes-256-gcm","nonce":"AA==","ciphertext":"AA==","tag":"AA=="}',
			'{"v":1,"alg":"unknown","nonce":"AA==","ciphertext":"AA=="}',
			'{"v":1,"alg":"aes-256-gcm","nonce":"%%%","ciphertext":"AA==","tag":"AA=="}',
		) as $envelope ) {
			try {
				$cipher->decrypt( ProviderIds::OPENAI_DIRECT, $envelope );
				self::fail( 'Malformed credential envelope must fail closed.' );
			} catch ( ProviderException $exception ) {
				self::assertSame( ProviderErrorCode::CONFIGURATION, $exception->error_code );
			}
		}
	}

	/**
	 * Encryption fails closed when neither authenticated backend is available.
	 */
	public function test_no_crypto_backend_fails_closed(): void {
		$this->require_cipher_contracts();
		$cipher = new AuthenticatedCredentialCipher( $this->capabilities( false, false ) );

		$this->expect_configuration_exception();
		$cipher->encrypt( ProviderIds::OPENAI_DIRECT, 'must-not-store-plaintext' );
	}

	/**
	 * Return a mock crypto-capability boundary.
	 *
	 * @param bool $sodium Sodium capability.
	 * @param bool $aes_gcm AES-GCM capability.
	 */
	private function capabilities( bool $sodium, bool $aes_gcm ): CryptoCapabilities {
		$capabilities = $this->createMock( CryptoCapabilities::class );
		$capabilities->method( 'sodium_available' )->willReturn( $sodium );
		$capabilities->method( 'aes_gcm_available' )->willReturn( $aes_gcm );
		return $capabilities;
	}

	/**
	 * Return a cipher using one authenticated backend available in the runtime.
	 */
	private function available_cipher(): AuthenticatedCredentialCipher {
		if ( function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			return new AuthenticatedCredentialCipher( $this->capabilities( true, false ) );
		}

		if ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			return new AuthenticatedCredentialCipher( $this->capabilities( false, true ) );
		}

		self::markTestSkipped( 'No authenticated crypto backend is available in this PHP runtime.' );
	}

	/**
	 * Decode an envelope for structural assertions.
	 *
	 * @param string $envelope Serialized envelope.
	 * @return array<string, mixed>
	 */
	private function decode_envelope( string $envelope ): array {
		$data = json_decode( $envelope, true, 512, JSON_THROW_ON_ERROR );
		self::assertIsArray( $data );
		return $data;
	}

	/**
	 * Modify authenticated ciphertext while preserving valid JSON/base64 shape.
	 *
	 * @param string $envelope Serialized envelope.
	 */
	private function tamper_ciphertext( string $envelope ): string {
		$data       = $this->decode_envelope( $envelope );
		$encoded    = (string) $data['ciphertext'];
		$first      = 'A' === $encoded[0] ? 'B' : 'A';
		$tampered   = $first . substr( $encoded, 1 );
		$needle     = '"ciphertext":"' . $encoded . '"';
		$replacement = '"ciphertext":"' . $tampered . '"';
		return str_replace( $needle, $replacement, $envelope );
	}

	/**
	 * Configure the expected normalized configuration error.
	 */
	private function expect_configuration_exception(): void {
		$this->expectException( ProviderException::class );
		$this->expectExceptionMessage( 'Provider credential encryption configuration is invalid.' );
	}

	/**
	 * Require the intended missing-contract RED before cipher implementation.
	 */
	private function require_cipher_contracts(): void {
		foreach ( array(
			CryptoCapabilities::class,
			RuntimeCryptoCapabilities::class,
			CredentialCipher::class,
			AuthenticatedCredentialCipher::class,
		) as $class_name ) {
			self::assertTrue(
				class_exists( $class_name ) || interface_exists( $class_name ),
				sprintf( '%s must exist before authenticated credential cipher behavior can pass.', $class_name )
			);
		}
	}
}
