<?php
/**
 * M13 persisted Playground bot configuration resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\MockObject\MockObject;
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
		$bot      = $this->bot( true );
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
		$bot      = $this->bot( false );
		$resolver = new PlaygroundBotConfigurationResolver( $this->repository( $bot ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Playground bot configuration is disabled.' );
		$resolver->resolve( $bot->id->value );
	}

	/** Build one persisted bot fixture. */
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

	/** Build a repository double returning one selected bot. */
	private function repository( ?Bot $bot ): BotRepository&MockObject {
		$repository = $this->createMock( BotRepository::class );
		$repository->method( 'find' )->willReturn( $bot );
		return $repository;
	}
}
