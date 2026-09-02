<?php
/**
 * Provider registry.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Maps stable provider IDs to generation and optional catalog adapters.
 */
final class ProviderRegistry {
	/**
	 * Registered generation providers keyed by stable provider ID.
	 *
	 * @var array<string, GenerationProvider>
	 */
	private array $generation = array();

	/**
	 * Registered model catalogs keyed by stable provider ID.
	 *
	 * @var array<string, ModelCatalogProvider>
	 */
	private array $catalogs = array();

	/**
	 * Register one provider.
	 *
	 * @param string                    $provider_id Stable provider identifier.
	 * @param GenerationProvider        $provider Generation adapter.
	 * @param ModelCatalogProvider|null $catalog Optional model catalog adapter.
	 * @throws InvalidArgumentException When the provider ID is duplicated or mismatched.
	 */
	public function register( string $provider_id, GenerationProvider $provider, ?ModelCatalogProvider $catalog = null ): void {
		if ( isset( $this->generation[ $provider_id ] ) ) {
			throw new InvalidArgumentException( 'Provider is already registered.' );
		}
		if ( $provider_id !== $provider->provider_id() ) {
			throw new InvalidArgumentException( 'Generation provider ID mismatch.' );
		}
		if ( null !== $catalog && $provider_id !== $catalog->provider_id() ) {
			throw new InvalidArgumentException( 'Model catalog provider ID mismatch.' );
		}

		$this->generation[ $provider_id ] = $provider;
		if ( null !== $catalog ) {
			$this->catalogs[ $provider_id ] = $catalog;
		}
	}

	/**
	 * Return a registered generation provider.
	 *
	 * @param string $provider_id Stable provider identifier.
	 * @throws OutOfBoundsException When the provider is not registered.
	 */
	public function generation( string $provider_id ): GenerationProvider {
		if ( ! isset( $this->generation[ $provider_id ] ) ) {
			throw new OutOfBoundsException( 'Unknown provider ID.' );
		}

		return $this->generation[ $provider_id ];
	}

	/**
	 * Return an optional model catalog provider.
	 *
	 * @param string $provider_id Stable provider identifier.
	 */
	public function catalog( string $provider_id ): ?ModelCatalogProvider {
		return $this->catalogs[ $provider_id ] ?? null;
	}

	/**
	 * Return provider IDs in registration order.
	 *
	 * @return string[]
	 */
	public function ids(): array {
		return array_keys( $this->generation );
	}
}
