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
use WpRagAiChatbot\VectorStore\Managed\ManagedVectorSearchResult;
use WpRagAiChatbot\VectorStore\Managed\ManagedVectorStore;
use WpRagAiChatbot\VectorStore\VectorStore;
use WpRagAiChatbot\VectorStore\VectorStoreCapabilities;
use WpRagAiChatbot\VectorStore\VectorStoreHealth;
use WpRagAiChatbot\VectorStore\VectorStoreRegistry;
use WpRagAiChatbot\VectorStore\VectorWriteResult;

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
	 * Managed flags cannot be advertised without implementing the managed contract.
	 */
	public function test_registry_rejects_untruthful_managed_capabilities(): void {
		$store = new class() implements VectorStore {
			/** Return the test store ID. */
			public function store_id(): string {
				return 'managed-liar';
			}

			/** Advertise managed capabilities without the matching interface. */
			public function capabilities(): VectorStoreCapabilities {
				return VectorStoreCapabilities::managed();
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
	 * Managed operation lookup returns only stores implementing the managed contract.
	 */
	public function test_registry_requires_managed_interface_before_use(): void {
		$store = new class() implements ManagedVectorStore {
			/** Return the test store ID. */
			public function store_id(): string {
				return 'managed';
			}

			/** Return truthful managed capabilities. */
			public function capabilities(): VectorStoreCapabilities {
				return VectorStoreCapabilities::managed();
			}

			/** Return healthy test status. */
			public function health(): VectorStoreHealth {
				return VectorStoreHealth::healthy();
			}

			/**
			 * Attach a test file without persistence.
			 *
			 * @param string               $file_id Provider file ID.
			 * @param array<string, mixed> $attributes Searchable file attributes.
			 */
			public function attach_file( string $file_id, array $attributes = array() ): VectorWriteResult {
				return new VectorWriteResult( false );
			}

			/**
			 * Delete a test file without persistence.
			 *
			 * @param string $file_id Provider file ID.
			 */
			public function delete_file( string $file_id ): VectorWriteResult {
				return new VectorWriteResult( false );
			}

			/**
			 * Return an empty managed search result.
			 *
			 * @param string $query Text query.
			 * @param int    $max_results Maximum result count.
			 */
			public function managed_search( string $query, int $max_results = 10 ): ManagedVectorSearchResult {
				return new ManagedVectorSearchResult( array() );
			}
		};

		$registry = new VectorStoreRegistry();
		$registry->register( $store );

		self::assertSame( $store, $registry->managed( 'managed' ) );
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
