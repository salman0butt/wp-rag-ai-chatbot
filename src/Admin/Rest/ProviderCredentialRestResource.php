<?php
/**
 * Provider credential administration REST resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use InvalidArgumentException;
use Throwable;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSource;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\Credentials\DirectProviderCredentialConfig;

/**
 * Exposes safe credential configuration state and write-only mutations.
 */
final class ProviderCredentialRestResource {
	/**
	 * Create a provider credential administration resource.
	 *
	 * @param CredentialSourceReader $reader Runtime credential-source reader.
	 * @param CredentialStore        $store Plugin-managed credential store.
	 */
	public function __construct(
		private CredentialSourceReader $reader,
		private CredentialStore $store
	) {
	}

	/**
	 * Read non-secret configuration state for one direct provider.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return array{configured:bool,source:string}|array{error:array{code:string,message:string}}
	 */
	public function read( string $provider_id ): array {
		try {
			$resolved = ( new CredentialResolver( $this->reader, $this->store ) )->resolve( $provider_id );

			return array(
				'configured' => null !== $resolved,
				'source'     => null === $resolved ? CredentialSource::NONE->value : $resolved->source->value,
			);
		} catch ( InvalidArgumentException ) {
			return $this->invalid_provider();
		} catch ( Throwable ) {
			return $this->operation_failed();
		}
	}

	/**
	 * Store or replace one plugin-managed direct-provider credential.
	 *
	 * @param string              $provider_id Provider identifier.
	 * @param array<string,mixed> $payload Request payload.
	 * @return array{managed:bool}|array{error:array{code:string,message:string}}
	 */
	public function write( string $provider_id, array $payload ): array {
		$credential = $payload['credential'] ?? null;
		if ( ! is_string( $credential ) || '' === trim( $credential ) ) {
			return $this->invalid_request();
		}

		try {
			DirectProviderCredentialConfig::for_provider( $provider_id );
			$this->store->save( $provider_id, $credential );

			return array( 'managed' => true );
		} catch ( InvalidArgumentException ) {
			return $this->invalid_provider();
		} catch ( Throwable ) {
			return $this->operation_failed();
		}
	}

	/**
	 * Delete one plugin-managed direct-provider credential.
	 *
	 * Runtime environment or constant credentials are intentionally unaffected.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return array{managed:bool}|array{error:array{code:string,message:string}}
	 */
	public function delete( string $provider_id ): array {
		try {
			DirectProviderCredentialConfig::for_provider( $provider_id );
			$this->store->delete( $provider_id );

			return array( 'managed' => false );
		} catch ( InvalidArgumentException ) {
			return $this->invalid_provider();
		} catch ( Throwable ) {
			return $this->operation_failed();
		}
	}

	/**
	 * Return a stable invalid-provider response.
	 *
	 * @return array{error:array{code:string,message:string}}
	 */
	private function invalid_provider(): array {
		return array(
			'error' => array(
				'code'    => 'invalid_provider',
				'message' => 'Provider is not supported for managed credentials.',
			),
		);
	}

	/**
	 * Return a stable malformed-request response.
	 *
	 * @return array{error:array{code:string,message:string}}
	 */
	private function invalid_request(): array {
		return array(
			'error' => array(
				'code'    => 'invalid_request',
				'message' => 'Credential request is invalid.',
			),
		);
	}

	/**
	 * Return a constant secret-free operation failure.
	 *
	 * @return array{error:array{code:string,message:string}}
	 */
	private function operation_failed(): array {
		return array(
			'error' => array(
				'code'    => 'credential_operation_failed',
				'message' => 'Provider credential operation failed.',
			),
		);
	}
}
