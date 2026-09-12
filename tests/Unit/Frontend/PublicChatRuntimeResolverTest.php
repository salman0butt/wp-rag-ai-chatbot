<?php
/**
 * Public chat persisted runtime resolution tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Frontend\PublicChatRuntimeResolver;

/**
 * Specifies server-owned bot runtime resolution for public chat.
 */
final class PublicChatRuntimeResolverTest extends TestCase {
	/** Enabled persisted bots are the authority for provider/model selection. */
	public function test_resolves_enabled_persisted_bot_without_request_runtime_overrides(): void {
		self::assertTrue( class_exists( PublicChatRuntimeResolver::class ), 'PublicChatRuntimeResolver is missing.' );

		$bot_id = new BotId( '0123456789abcdef0123456789abcdef' );
		$bot    = new Bot(
			$bot_id,
			'Support bot',
			true,
			'openai',
			'gpt-4.1-mini',
			1,
			'2026-09-12 00:00:00',
			'2026-09-12 00:00:00'
		);

		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::once() )
			->method( 'find' )
			->with( self::callback( static fn ( BotId $id ): bool => $bot_id->value === $id->value ) )
			->willReturn( $bot );

		self::assertSame( $bot, ( new PublicChatRuntimeResolver( $repository ) )->resolve( $bot_id->value ) );
	}

	/** Missing bots fail closed before provider/retrieval work. */
	public function test_missing_bot_fails_closed(): void {
		self::assertTrue( class_exists( PublicChatRuntimeResolver::class ), 'PublicChatRuntimeResolver is missing.' );

		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::once() )->method( 'find' )->willReturn( null );

		$this->expectException( RuntimeException::class );
		( new PublicChatRuntimeResolver( $repository ) )->resolve( '0123456789abcdef0123456789abcdef' );
	}

	/** Disabled bots fail closed instead of reaching paid runtime work. */
	public function test_disabled_bot_fails_closed(): void {
		self::assertTrue( class_exists( PublicChatRuntimeResolver::class ), 'PublicChatRuntimeResolver is missing.' );

		$bot = new Bot(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			'Disabled bot',
			false,
			'openai',
			'gpt-4.1-mini',
			1,
			'2026-09-12 00:00:00',
			'2026-09-12 00:00:00'
		);

		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::once() )->method( 'find' )->willReturn( $bot );

		$this->expectException( RuntimeException::class );
		( new PublicChatRuntimeResolver( $repository ) )->resolve( $bot->id->value );
	}
}
