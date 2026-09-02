<?php
/**
 * Authenticated credential crypto capability contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

/**
 * Reports authenticated encryption backends available to the runtime.
 */
interface CryptoCapabilities {
	/**
	 * Whether Sodium XChaCha20-Poly1305 is available.
	 */
	public function sodium_available(): bool;

	/**
	 * Whether OpenSSL AES-256-GCM is available.
	 */
	public function aes_gcm_available(): bool;
}
