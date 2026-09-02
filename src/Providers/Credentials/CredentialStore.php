<?php
/**
 * Provider credential persistence contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

/**
 * Persists plugin-managed provider credentials behind the Secret boundary.
 */
interface CredentialStore {
	/**
	 * Load a provider secret when configured.
	 *
	 * @param string $provider_id Provider identifier.
	 */
	public function load( string $provider_id ): ?Secret;

	/**
	 * Save provider plaintext through the store's secure implementation.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $plaintext Plaintext credential.
	 */
	public function save( string $provider_id, string $plaintext ): void;

	/**
	 * Delete a plugin-managed provider credential.
	 *
	 * @param string $provider_id Provider identifier.
	 */
	public function delete( string $provider_id ): void;
}
