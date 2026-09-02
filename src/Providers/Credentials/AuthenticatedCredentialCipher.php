<?php
/**
 * Authenticated provider credential cipher.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

use JsonException;
use Throwable;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;

// phpcs:disable WordPress.Security.EscapeOutput -- Exception metadata is internal and is never rendered as output.
/**
 * Encrypts provider credentials with provider-bound authenticated encryption.
 */
final class AuthenticatedCredentialCipher implements CredentialCipher {
	private const VERSION        = 1;
	private const SODIUM_ALG     = 'xchacha20poly1305';
	private const AES_GCM_ALG    = 'aes-256-gcm';
	private const AES_NONCE_SIZE = 12;
	private const AES_TAG_SIZE   = 16;

	/**
	 * Create an authenticated credential cipher.
	 *
	 * @param CryptoCapabilities $capabilities Runtime crypto capabilities.
	 */
	public function __construct( private CryptoCapabilities $capabilities ) {
	}

	/**
	 * Encrypt provider credential plaintext.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $plaintext Credential plaintext.
	 * @throws ProviderException When authenticated encryption is unavailable or fails.
	 */
	public function encrypt( string $provider_id, string $plaintext ): string {
		try {
			if ( $this->capabilities->sodium_available() ) {
				return $this->encrypt_sodium( $provider_id, $plaintext );
			}

			if ( $this->capabilities->aes_gcm_available() ) {
				return $this->encrypt_aes_gcm( $provider_id, $plaintext );
			}
		} catch ( Throwable ) {
			throw $this->configuration_failure( $provider_id );
		}

		throw $this->configuration_failure( $provider_id );
	}

	/**
	 * Decrypt a provider-bound credential envelope.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $envelope Serialized encrypted envelope.
	 * @throws ProviderException When the envelope is invalid or cannot be authenticated.
	 */
	public function decrypt( string $provider_id, string $envelope ): string {
		try {
			$data = $this->decode_envelope( $provider_id, $envelope );

			if ( self::SODIUM_ALG === $data['alg'] ) {
				if ( ! $this->capabilities->sodium_available() ) {
					throw $this->configuration_failure( $provider_id );
				}
				return $this->decrypt_sodium( $provider_id, $data );
			}

			if ( self::AES_GCM_ALG === $data['alg'] ) {
				if ( ! $this->capabilities->aes_gcm_available() ) {
					throw $this->configuration_failure( $provider_id );
				}
				return $this->decrypt_aes_gcm( $provider_id, $data );
			}
		} catch ( ProviderException $exception ) {
			throw $exception;
		} catch ( Throwable ) {
			throw $this->configuration_failure( $provider_id );
		}

		throw $this->configuration_failure( $provider_id );
	}

	/**
	 * Encrypt with Sodium XChaCha20-Poly1305.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $plaintext Credential plaintext.
	 * @throws ProviderException When Sodium encryption cannot be performed.
	 */
	private function encrypt_sodium( string $provider_id, string $plaintext ): string {
		$nonce_size = constant( 'SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES' );
		$nonce      = random_bytes( $nonce_size );
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
			$plaintext,
			$this->aad( $provider_id ),
			$nonce,
			$this->key( $provider_id )
		);

		return $this->serialize_envelope( self::SODIUM_ALG, $nonce, $ciphertext );
	}

	/**
	 * Encrypt with OpenSSL AES-256-GCM.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $plaintext Credential plaintext.
	 * @throws ProviderException When AES-GCM encryption cannot be performed.
	 */
	private function encrypt_aes_gcm( string $provider_id, string $plaintext ): string {
		$nonce      = random_bytes( self::AES_NONCE_SIZE );
		$tag        = '';
		$ciphertext = openssl_encrypt(
			$plaintext,
			self::AES_GCM_ALG,
			$this->key( $provider_id ),
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			$this->aad( $provider_id ),
			self::AES_TAG_SIZE
		);

		if ( false === $ciphertext || self::AES_TAG_SIZE !== strlen( $tag ) ) {
			throw $this->configuration_failure( $provider_id );
		}

		return $this->serialize_envelope( self::AES_GCM_ALG, $nonce, $ciphertext, $tag );
	}

	/**
	 * Decrypt a Sodium envelope.
	 *
	 * @param string               $provider_id Provider identifier.
	 * @param array<string, mixed> $data Envelope data.
	 * @throws ProviderException When the envelope cannot be authenticated.
	 */
	private function decrypt_sodium( string $provider_id, array $data ): string {
		if ( ! $this->has_exact_keys( $data, array( 'v', 'alg', 'nonce', 'ciphertext' ) ) ) {
			throw $this->configuration_failure( $provider_id );
		}

		$nonce_size = constant( 'SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES' );
		$nonce      = $this->decode_base64( $provider_id, $data['nonce'] );
		$ciphertext = $this->decode_base64( $provider_id, $data['ciphertext'] );
		if ( strlen( $nonce ) !== $nonce_size ) {
			throw $this->configuration_failure( $provider_id );
		}

		$plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			$ciphertext,
			$this->aad( $provider_id ),
			$nonce,
			$this->key( $provider_id )
		);
		if ( false === $plaintext ) {
			throw $this->configuration_failure( $provider_id );
		}

		return $plaintext;
	}

	/**
	 * Decrypt an AES-GCM envelope.
	 *
	 * @param string               $provider_id Provider identifier.
	 * @param array<string, mixed> $data Envelope data.
	 * @throws ProviderException When the envelope cannot be authenticated.
	 */
	private function decrypt_aes_gcm( string $provider_id, array $data ): string {
		if ( ! $this->has_exact_keys( $data, array( 'v', 'alg', 'nonce', 'ciphertext', 'tag' ) ) ) {
			throw $this->configuration_failure( $provider_id );
		}

		$nonce      = $this->decode_base64( $provider_id, $data['nonce'] );
		$ciphertext = $this->decode_base64( $provider_id, $data['ciphertext'] );
		$tag        = $this->decode_base64( $provider_id, $data['tag'] );
		if ( self::AES_NONCE_SIZE !== strlen( $nonce ) || self::AES_TAG_SIZE !== strlen( $tag ) ) {
			throw $this->configuration_failure( $provider_id );
		}

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::AES_GCM_ALG,
			$this->key( $provider_id ),
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			$this->aad( $provider_id )
		);
		if ( false === $plaintext ) {
			throw $this->configuration_failure( $provider_id );
		}

		return $plaintext;
	}

	/**
	 * Decode and validate the common envelope fields.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $envelope Serialized encrypted envelope.
	 * @return array<string, mixed>
	 * @throws ProviderException When the serialized envelope is malformed or unsupported.
	 */
	private function decode_envelope( string $provider_id, string $envelope ): array {
		try {
			$data = json_decode( $envelope, true, 16, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw $this->configuration_failure( $provider_id );
		}

		if ( ! is_array( $data )
			|| self::VERSION !== ( $data['v'] ?? null )
			|| ! isset( $data['alg'] )
			|| ! is_string( $data['alg'] )
		) {
			throw $this->configuration_failure( $provider_id );
		}

		return $data;
	}

	/**
	 * Serialize a binary authenticated envelope using fixed safe fields.
	 *
	 * @param string      $algorithm Algorithm identifier.
	 * @param string      $nonce Binary nonce.
	 * @param string      $ciphertext Binary ciphertext.
	 * @param string|null $tag Optional binary AES authentication tag.
	 */
	private function serialize_envelope( string $algorithm, string $nonce, string $ciphertext, ?string $tag = null ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary cryptographic envelope transport encoding.
		$nonce_encoded = base64_encode( $nonce );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary cryptographic envelope transport encoding.
		$ciphertext_encoded = base64_encode( $ciphertext );

		if ( null === $tag ) {
			return sprintf(
				'{"v":1,"alg":"%s","nonce":"%s","ciphertext":"%s"}',
				$algorithm,
				$nonce_encoded,
				$ciphertext_encoded
			);
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary cryptographic envelope transport encoding.
		$tag_encoded = base64_encode( $tag );
		return sprintf(
			'{"v":1,"alg":"%s","nonce":"%s","ciphertext":"%s","tag":"%s"}',
			$algorithm,
			$nonce_encoded,
			$ciphertext_encoded,
			$tag_encoded
		);
	}

	/**
	 * Strictly decode one base64 envelope field.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param mixed  $value Encoded field value.
	 * @throws ProviderException When the field is not strict base64 text.
	 */
	private function decode_base64( string $provider_id, mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw $this->configuration_failure( $provider_id );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary cryptographic envelope transport decoding.
		$decoded = base64_decode( $value, true );
		if ( false === $decoded ) {
			throw $this->configuration_failure( $provider_id );
		}

		return $decoded;
	}

	/**
	 * Determine whether an envelope has exactly the expected keys.
	 *
	 * @param array<string, mixed> $data Envelope data.
	 * @param string[]             $expected Expected keys.
	 */
	private function has_exact_keys( array $data, array $expected ): bool {
		$actual = array_keys( $data );
		sort( $actual );
		sort( $expected );
		return $actual === $expected;
	}

	/**
	 * Derive a provider-bound 256-bit encryption key.
	 *
	 * @param string $provider_id Provider identifier.
	 */
	private function key( string $provider_id ): string {
		return hash_hkdf(
			'sha256',
			wp_salt( 'auth' ) . "\0" . wp_salt( 'secure_auth' ),
			32,
			'wp-rag-ai-chatbot:credential:v1:' . $provider_id,
			''
		);
	}

	/**
	 * Return provider-bound authenticated additional data.
	 *
	 * @param string $provider_id Provider identifier.
	 */
	private function aad( string $provider_id ): string {
		return 'wp-rag-ai-chatbot:' . $provider_id . ':credential:v1';
	}

	/**
	 * Return a constant, secret-free normalized configuration failure.
	 *
	 * @param string $provider_id Provider identifier.
	 */
	private function configuration_failure( string $provider_id ): ProviderException {
		return new ProviderException(
			ProviderErrorCode::CONFIGURATION,
			$provider_id,
			'Provider credential encryption configuration is invalid.'
		);
	}
}
// phpcs:enable WordPress.Security.EscapeOutput
