<?php
/**
 * Runtime authenticated credential crypto capabilities.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

/**
 * Detects authenticated encryption primitives exposed by PHP.
 */
final class RuntimeCryptoCapabilities implements CryptoCapabilities {
	/**
	 * Whether Sodium XChaCha20-Poly1305 is available.
	 */
	public function sodium_available(): bool {
		return function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' )
			&& function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' )
			&& defined( 'SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES' );
	}

	/**
	 * Whether OpenSSL AES-256-GCM is available.
	 */
	public function aes_gcm_available(): bool {
		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'openssl_decrypt' )
			&& in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true );
	}
}
