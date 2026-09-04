<?php
/**
 * Provider registry embedding capability tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\Providers\ProviderRegistry;

/**
 * Verifies embedding capability registration stays optional and ID-safe.
 */
final class ProviderRegistryEmbeddingTest extends TestCase {
	/**
	 * A provider can expose generation and embedding under one stable ID.
	 */
	public function test_registry_returns_registered_embedding_capability(): void {
		$generation = $this->generation_provider( 'provider-a' );
		$embedding  = $this->embedding_provider( 'provider-a' );
		$registry   = new ProviderRegistry();

		$registry->register( 'provider-a', $generation, null, $embedding );

		self::assertSame( $embedding, $registry->embedding( 'provider-a' ) );
	}

	/**
	 * Existing generation-only providers do not gain a fake embedding capability.
	 */
	public function test_generation_only_provider_returns_null_embedding_capability(): void {
		$registry = new ProviderRegistry();
		$registry->register( 'provider-a', $this->generation_provider( 'provider-a' ) );

		self::assertNull( $registry->embedding( 'provider-a' ) );
	}

	/**
	 * Capability IDs cannot be registered under a different provider identity.
	 */
	public function test_registry_rejects_embedding_provider_id_mismatch(): void {
		$registry = new ProviderRegistry();

		$this->expectException( InvalidArgumentException::class );
		$registry->register(
			'provider-a',
			$this->generation_provider( 'provider-a' ),
			null,
			$this->embedding_provider( 'provider-b' )
		);
	}

	/**
	 * Build a generation provider test double.
	 */
	private function generation_provider( string $provider_id ): GenerationProvider {
		return new class( $provider_id ) implements GenerationProvider {
			public function __construct( private readonly string $id ) {
			}

			public function provider_id(): string {
				return $this->id;
			}

			public function available(): bool {
				return true;
			}

			public function generate( GenerationRequest $request ): GenerationResult {
				throw new \LogicException( 'Not used by this test.' );
			}
		};
	}

	/**
	 * Build an embedding provider test double.
	 */
	private function embedding_provider( string $provider_id ): EmbeddingProvider {
		return new class( $provider_id ) implements EmbeddingProvider {
			public function __construct( private readonly string $id ) {
			}

			public function provider_id(): string {
				return $this->id;
			}

			public function available(): bool {
				return true;
			}

			public function embed( EmbeddingRequest $request ): EmbeddingResult {
				throw new \LogicException( 'Not used by this test.' );
			}
		};
	}
}
