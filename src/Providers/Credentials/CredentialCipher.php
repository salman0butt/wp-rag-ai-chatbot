<?php
/**
 * Provider credential cipher contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

/**
 * Encrypts and decrypts provider-bound credential envelopes.
 */
interface CredentialCipher {
	/**
	 * Encrypt provider credential plaintext.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $plaintext Credential plaintext.
	 */
	public function encrypt( string $provider_id, string $plaintext ): string;

	/**
	 * Decrypt a provider-bound credential envelope.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $envelope Serialized encrypted envelope.
	 */
	public function decrypt( string $provider_id, string $envelope ): string;
}
