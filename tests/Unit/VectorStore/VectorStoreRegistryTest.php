<?php
/**
 * Vector-store registry tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\VectorStore\VectorStore;
use WpRagAiChatbot\VectorStore\VectorStoreCapabilities;
use WpRagAiChatbot\VectorStore\VectorStoreHealth;
use WpRagAiChatbot\VectorStore\VectorStoreRegistry;

/**
 * Verifies registry identity and truthful capability boundaries.
 */
final class VectorStoreRegistryTest extends TestCase {
	/**
	 * Duplicate IDs cannot shadow an already-registered store.
	 */
	public function test_registry_rejects_duplicate_store_ids(): void {
		$registry = new VectorStoreRegistry();
		$registry->register( $this->base_store( 'memory' ) );

		$this->expectException( InvalidArgumentException::class );
		$registry->register( $this->base_store( 'memory' ) );
	}

	/**
	 * A store cannot advertise an operation interface it does not implement.
	 */
	public function test_registry_rejects_untruthful_capabilities(): void {
		$store = new class() implements VectorStore {
			/** Return the test store ID. */
			public function store_id(): string {
				return 'lying';
			}

			/** Return intentionally untruthful capabilities. */
			public function capabilities(): VectorStoreCapabilities {
				return new VectorStoreCapabilities( true, false, false );
			}

			/** Return healthy test status. */
			public function health(): VectorStoreHealth {
				return VectorStoreHealth::healthy();
			}
		};

		$this->expectException( InvalidArgumentException::class );
		( new VectorStoreRegistry() )->register( $store );
	}

	/**
	 * Operation lookup fails before a caller can invoke an unsupported capability.
	 */
	public function test_registry_requires_operation_interface_before_use(): void {
		$registry = new VectorStoreRegistry();
		$registry->register( $this->base_store( 'readonly' ) );

		$this->expectException( InvalidArgumentException::class );
		$registry->upsert( 'readonly' );
	}

	/**
	 * Create a base store with no optional operations.
	 *
	 * @param string $id Store ID.
	 */
	private function base_store( string $id ): VectorStore {
		return new class( $id ) implements VectorStore {
			/**
			 * Create the test store.
			 *
			 * @param string $id Store ID.
			 */
			public function __construct( private readonly string $id ) {}

			/** Return the test store ID. */
			public function store_id(): string {
				return $this->id;
			}

			/** Return no optional capabilities. */
			public function capabilities(): VectorStoreCapabilities {
				return VectorStoreCapabilities::none();
			}

			/** Return healthy test status. */
			public function health(): VectorStoreHealth {
				return VectorStoreHealth::healthy();
			}
		};
	}
}
