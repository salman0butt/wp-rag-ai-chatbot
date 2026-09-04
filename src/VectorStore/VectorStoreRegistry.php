<?php
/**
 * Vector-store registry.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

use InvalidArgumentException;

/**
 * Registers vector stores and exposes only truthful optional operations.
 */
final class VectorStoreRegistry {
	/** @var array<string, VectorStore> */
	private array $stores = array();

	/**
	 * Register one stable store.
	 *
	 * @param VectorStore $store Store implementation.
	 * @throws InvalidArgumentException When ID/capabilities are invalid or duplicate.
	 */
	public function register( VectorStore $store ): void {
		$id = $store->store_id();
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $id ) ) {
			throw new InvalidArgumentException( 'Vector store ID is invalid.' );
		}
		if ( isset( $this->stores[ $id ] ) ) {
			throw new InvalidArgumentException( 'Vector store ID is already registered.' );
		}

		$capabilities = $store->capabilities();
		if (
			$capabilities->upsert !== ( $store instanceof VectorUpsertStore )
			|| $capabilities->delete !== ( $store instanceof VectorDeleteStore )
			|| $capabilities->search !== ( $store instanceof VectorSearchStore )
		) {
			throw new InvalidArgumentException( 'Vector store capabilities do not match implemented operation interfaces.' );
		}

		$this->stores[ $id ] = $store;
	}

	/**
	 * Return a registered store.
	 *
	 * @param string $id Store ID.
	 * @throws InvalidArgumentException When the store is unknown.
	 */
	public function get( string $id ): VectorStore {
		if ( ! isset( $this->stores[ $id ] ) ) {
			throw new InvalidArgumentException( 'Vector store is not registered.' );
		}

		return $this->stores[ $id ];
	}

	/**
	 * Require raw-vector upsert capability.
	 *
	 * @param string $id Store ID.
	 * @throws InvalidArgumentException When unsupported.
	 */
	public function upsert( string $id ): VectorUpsertStore {
		$store = $this->get( $id );
		if ( ! $store instanceof VectorUpsertStore ) {
			throw new InvalidArgumentException( 'Vector store does not support upsert.' );
		}

		return $store;
	}

	/**
	 * Require vector delete capability.
	 *
	 * @param string $id Store ID.
	 * @throws InvalidArgumentException When unsupported.
	 */
	public function delete( string $id ): VectorDeleteStore {
		$store = $this->get( $id );
		if ( ! $store instanceof VectorDeleteStore ) {
			throw new InvalidArgumentException( 'Vector store does not support delete.' );
		}

		return $store;
	}

	/**
	 * Require raw-vector search capability.
	 *
	 * @param string $id Store ID.
	 * @throws InvalidArgumentException When unsupported.
	 */
	public function search( string $id ): VectorSearchStore {
		$store = $this->get( $id );
		if ( ! $store instanceof VectorSearchStore ) {
			throw new InvalidArgumentException( 'Vector store does not support search.' );
		}

		return $store;
	}
}
