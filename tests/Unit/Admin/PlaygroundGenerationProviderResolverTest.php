<?php
/**
 * M13 Playground generation-provider composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundGenerationProviderResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalConfiguration;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\Providers\ProviderRegistry;

/**
 * Proves Playground generation composition uses the persisted bot provider without fallbacks.
 */
final class PlaygroundGenerationProviderResolverTest extends TestCase {
	/** Persisted bot provider selection must resolve the exact registered generation adapter. */
	public function test_resolves_exact_generation_provider_selected_by_persisted_bot(): void {
		$provider = $this->provider( 'openai-direct' );
		$registry = new ProviderRegistry();
		$registry->register( 'openai-direct', $provider );
		$resolver = new PlaygroundGenerationProviderResolver( $registry );

		self::assertSame( $provider, $resolver->resolve( $this->configuration( 'openai-direct' ) ) );
	}

	/** Unknown persisted provider IDs must fail closed instead of selecting another provider. */
	public function test_rejects_unknown_persisted_provider_without_fallback(): void {
		$registered = $this->provider( 'openai-direct' );
		$registry   = new ProviderRegistry();
		$registry->register( 'openai-direct', $registered );
		$resolver = new PlaygroundGenerationProviderResolver( $registry );

		$this->expectException( OutOfBoundsException::class );
		$this->expectExceptionMessage( 'Unknown provider ID.' );
		$resolver->resolve( $this->configuration( 'missing-provider' ) );
	}

	/**
	 * Build one deterministic generation-provider fixture.
	 *
	 * @param string $provider_id Provider ID exposed by the fixture.
	 */
	private function provider( string $provider_id ): GenerationProvider {
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
				throw new LogicException( 'Generation must not run while resolving a provider.' );
			}
		};
	}

	/**
	 * Build one closed persisted Playground configuration.
	 *
	 * @param string $provider_id Persisted provider ID selected by the bot.
	 */
	private function configuration( string $provider_id ): PlaygroundConfiguration {
		$now    = new DateTimeImmutable( '2026-09-10T10:00:00+00:00' );
		$source = new KnowledgeSourceRecord(
			7,
			'wordpress-posts',
			'wordpress_posts',
			null,
			'WordPress Posts',
			null,
			'indexed',
			array(),
			null,
			$now,
			$now,
			$now
		);
		$bot    = new Bot(
			new BotId( 'support-bot' ),
			'Support',
			true,
			$provider_id,
			'gpt-test',
			1,
			'2026-09-10 10:00:00',
			'2026-09-10 10:00:00'
		);

		return new PlaygroundConfiguration(
			$bot,
			new PlaygroundRetrievalConfiguration( $source, 'production-rag' )
		);
	}
}
