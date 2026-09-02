<?php
/**
 * WordPress provider credential store.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

use InvalidArgumentException;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;

// phpcs:disable WordPress.Security.EscapeOutput -- Exception metadata is internal and is never rendered as output.
/**
 * Stores plugin-managed credentials as encrypted, non-autoloaded options.
 */
final class WordPressCredentialStore implements CredentialStore {
	/**
	 * Create a WordPress credential store.
	 *
	 * @param CredentialCipher $cipher Provider-bound credential cipher.
	 */
	public function __construct( private CredentialCipher $cipher ) {
	}

	/**
	 * Load a provider secret when configured.
	 *
	 * @param string $provider_id Provider identifier.
	 * @throws InvalidArgumentException When the provider is unsupported.
	 * @throws ProviderException When stored credential data is invalid.
	 */
	public function load( string $provider_id ): ?Secret {
		$config   = DirectProviderCredentialConfig::for_provider( $provider_id );
		$envelope = get_option( $config->option_name, null );

		if ( null === $envelope ) {
			return null;
		}

		if ( ! is_string( $envelope ) ) {
			throw $this->configuration_failure( $provider_id );
		}

		return new Secret( $this->cipher->decrypt( $provider_id, $envelope ) );
	}

	/**
	 * Save provider plaintext through authenticated encrypted storage.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $plaintext Plaintext credential.
	 * @throws InvalidArgumentException When the provider is unsupported.
	 * @throws ProviderException When the credential cannot be stored safely.
	 */
	public function save( string $provider_id, string $plaintext ): void {
		$config = DirectProviderCredentialConfig::for_provider( $provider_id );

		if ( '' === trim( $plaintext ) ) {
			delete_option( $config->option_name );
			return;
		}

		$envelope = $this->cipher->encrypt( $provider_id, $plaintext );
		$current  = get_option( $config->option_name, null );

		if ( null === $current ) {
			if ( ! add_option( $config->option_name, $envelope, '', false ) ) {
				throw $this->configuration_failure( $provider_id );
			}
			return;
		}

		if ( update_option( $config->option_name, $envelope, false ) ) {
			return;
		}

		if ( get_option( $config->option_name, null ) === $envelope ) {
			return;
		}

		throw $this->configuration_failure( $provider_id );
	}

	/**
	 * Delete a plugin-managed provider credential.
	 *
	 * @param string $provider_id Provider identifier.
	 * @throws InvalidArgumentException When the provider is unsupported.
	 */
	public function delete( string $provider_id ): void {
		$config = DirectProviderCredentialConfig::for_provider( $provider_id );
		delete_option( $config->option_name );
	}

	/**
	 * Return a constant, secret-free storage configuration failure.
	 *
	 * @param string $provider_id Provider identifier.
	 */
	private function configuration_failure( string $provider_id ): ProviderException {
		return new ProviderException(
			ProviderErrorCode::CONFIGURATION,
			$provider_id,
			'Provider credential storage configuration is invalid.'
		);
	}
}
