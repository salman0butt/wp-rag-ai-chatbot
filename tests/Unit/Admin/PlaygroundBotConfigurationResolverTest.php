<?php
/**
 * M13 persisted Playground bot configuration resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Admin\Rest\PlaygroundBotConfigurationResolver;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;

/**
 * Proves Playground execution selects an explicit persisted bot configuration.
 */
final class PlaygroundBotConfigurationResolverTest extends TestCase {
	/** Resolver must return the exact enabled persisted bot selected by canonical ID. */
	public function test_resolves_enabled_bot_by_explicit_id(): void {
		$bot = $this->bot( true );
		$resolver = new PlaygroundBotConfigurationResolver( $this->repository( $bot ) );

		$resolved = $resolver->resolve( $bot->id->value );

		self::assertSame( $bot, $resolved );
		self::assertSame( 'openai', $resolved->provider_id );
		self::assertSame( 'gpt-4.1-mini', $resolved->model_id );
	}

	/** Missing persisted selectors must not silently fall back to another bot. */
	public function test_rejects_missing_bot(): void {
		$resolver = new PlaygroundBotConfigurationResolver( $this->repository( null ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Playground bot configuration was not found.' );
		$resolver->resolve( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' );
	}

	/** Disabled persisted bots must not be executed through the administrator playground. */
	public function test_rejects_disabled_bot(): void {
		$bot = $this->bot( false );
		$resolver = new PlaygroundBotConfigurationResolver( $this->repository( $bot ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Playground bot configuration is disabled.' );
		$resolver->resolve( $bot->id->value );
	}

	private function bot( bool $enabled ): Bot {
		return new Bot(
			new BotId( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
			'Production Bot',
			$enabled,
			'openai',
			'gpt-4.1-mini',
			1,
			'2026-09-10 10:00:00',
			'2026-09-10 10:00:00'
		);
	}

	private function repository( ?Bot $bot ): BotRepository {
		return new class( $bot ) implements BotRepository {
			public function __construct( private readonly ?Bot $bot ) {
			}

			public function create( string $name, bool $enabled, string $provider_id, string $model_id ): Bot {
				throw new RuntimeException( 'Not used.' );
			}

			public function find( BotId $id ): ?Bot {
				if ( null === $this->bot || $this->bot->id->value !== $id->value ) {
					return null;
				}
				return $this->bot;
			}

			public function update(
				BotId $id,
				int $expected_version,
				string $name,
				bool $enabled,
				string $provider_id,
				string $model_id
			): Bot {
				throw new RuntimeException( 'Not used.' );
			}

			public function delete( BotId $id ): bool {
				throw new RuntimeException( 'Not used.' );
			}

			public function list( int $page, int $per_page ): array {
				throw new RuntimeException( 'Not used.' );
			}
		};
	}
}
