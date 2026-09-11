<?php
/**
 * M13 Playground embedding-provider composition tests.
 *
 * @package WpRagAiChatbot
 */
declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundEmbeddingProviderResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticConfiguration;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\Providers\ProviderRegistry;

/**
 * Proves Playground semantic composition uses the persisted embedding provider without fallbacks.
 */
final class PlaygroundEmbeddingProviderResolverTest extends TestCase {
	/** Persisted embedding-provider identity must resolve the exact registered adapter. */
	public function test_resolves_exact_embedding_provider_selected_by_persisted_profile(): void {
		$generation = $this->generation_provider( 'openai-direct' );
		$embedding  = $this->embedding_provider( 'openai-direct' );
		$registry   = new ProviderRegistry();
		$registry->register( 'openai-direct', $generation, null, $embedding );
		$resolver = new PlaygroundEmbeddingProviderResolver( $registry );

		self::assertSame( $embedding, $resolver->resolve( $this->configuration( 'openai-direct' ) ) );
	}

	/** Unknown persisted provider IDs must fail closed instead of selecting another provider. */
	public function test_rejects_unknown_persisted_provider_without_fallback(): void {
		$generation = $this->generation_provider( 'openai-direct' );
		$embedding  = $this->embedding_provider( 'openai-direct' );
		$registry   = new ProviderRegistry();
		$registry->register( 'openai-direct', $generation, null, $embedding );
		$resolver = new PlaygroundEmbeddingProviderResolver( $registry );

		$this->expectException( OutOfBoundsException::class );
		$this->expectExceptionMessage( 'Embedding provider is not registered.' );
		$resolver->resolve( $this->configuration( 'missing-provider' ) );
	}

	/** Registered generation-only providers must fail closed for semantic retrieval. */
	public function test_rejects_provider_without_embedding_capability(): void {
		$registry = new ProviderRegistry();
		$registry->register( 'generation-only', $this->generation_provider( 'generation-only' ) );
		$resolver = new PlaygroundEmbeddingProviderResolver( $registry );

		$this->expectException( OutOfBoundsException::class );
		$this->expectExceptionMessage( 'Embedding provider is not registered.' );
		$resolver->resolve( $this->configuration( 'generation-only' ) );
	}

	/**
	 * Build one deterministic generation-provider fixture required by the registry contract.
	 *
	 * @param string $provider_id Provider ID exposed by the fixture.
	 */
	private function generation_provider( string $provider_id ): GenerationProvider {
		return new class( $provider_id ) implements GenerationProvider {
			/**
			 * Create the deterministic provider fixture.
			 *
			 * @param string $id Provider ID exposed by the fixture.
			 */
			public function __construct( private readonly string $id ) {
			}

			/** Return the configured provider ID. */
			public function provider_id(): string {
				return $this->id;
			}

			/** Test fixtures are available without network work. */
			public function available(): bool {
				return true;
			}

			/**
			 * Generation is outside this resolver contract.
			 *
			 * @param GenerationRequest $request Generation request that must never be executed here.
			 * @throws LogicException Always, because generation is outside resolver scope.
			 */
			public function generate( GenerationRequest $request ): GenerationResult {
				throw new LogicException( 'Generation must not run while resolving an embedding provider.' );
			}
		};
	}

	/**
	 * Build one deterministic embedding-provider fixture.
	 *
	 * @param string $provider_id Provider ID exposed by the fixture.
	 */
	private function embedding_provider( string $provider_id ): EmbeddingProvider {
		return new class( $provider_id ) implements EmbeddingProvider {
			/**
			 * Create the deterministic provider fixture.
			 *
			 * @param string $id Provider ID exposed by the fixture.
			 */
			public function __construct( private readonly string $id ) {
			}

			/** Return the configured provider ID. */
			public function provider_id(): string {
				return $this->id;
			}

			/** Test fixtures are available without network work. */
			public function available(): bool {
				return true;
			}

			/**
			 * Embedding is outside this resolver contract.
			 *
			 * @param EmbeddingRequest $request Embedding request that must never be executed here.
			 * @throws LogicException Always, because embedding is outside resolver scope.
			 */
			public function embed( EmbeddingRequest $request ): EmbeddingResult {
				throw new LogicException( 'Embedding must not run while resolving a provider.' );
			}
		};
	}

	/**
	 * Build one persisted semantic configuration.
	 *
	 * @param string $provider_id Persisted embedding provider ID.
	 */
	private function configuration( string $provider_id ): PlaygroundSemanticConfiguration {
		return new PlaygroundSemanticConfiguration(
			new EmbeddingProfile( $provider_id, 'text-embedding-test', 3, NormalizationMode::NONE ),
			DistanceMetric::COSINE,
			'local'
		);
	}
}
