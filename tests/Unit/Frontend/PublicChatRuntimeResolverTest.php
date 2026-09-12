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
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\BotRetrievalBindingRepository;
use WpRagAiChatbot\Frontend\PublicChatRuntime;
use WpRagAiChatbot\Frontend\PublicChatRuntimeResolver;

/**
 * Specifies server-owned bot runtime resolution for public chat.
 */
final class PublicChatRuntimeResolverTest extends TestCase {
	/** Enabled bot plus persisted retrieval binding form one trusted runtime authority. */
	public function test_resolves_enabled_persisted_runtime_without_request_runtime_overrides(): void {
		self::assertTrue( class_exists( PublicChatRuntimeResolver::class ), 'PublicChatRuntimeResolver is missing.' );
		self::assertTrue( class_exists( PublicChatRuntime::class ), 'PublicChatRuntime is missing.' );

		$bot_id  = new BotId( '0123456789abcdef0123456789abcdef' );
		$bot     = new Bot(
			$bot_id,
			'Support bot',
			true,
			'openai',
			'gpt-4.1-mini',
			1,
			'2026-09-12 00:00:00',
			'2026-09-12 00:00:00'
		);
		$binding = new BotRetrievalBinding( 42, 'support-en-v1' );

		$bots = $this->createMock( BotRepository::class );
		$bots->expects( self::once() )
			->method( 'find' )
			->with( self::callback( static fn ( BotId $id ): bool => $bot_id->value === $id->value ) )
			->willReturn( $bot );

		$bindings = $this->createMock( BotRetrievalBindingRepository::class );
		$bindings->expects( self::once() )
			->method( 'find' )
			->with( $bot_id )
			->willReturn( $binding );

		$runtime = ( new PublicChatRuntimeResolver( $bots, $bindings ) )->resolve( $bot_id->value );

		self::assertSame( $bot, $runtime->bot );
		self::assertSame( $binding, $runtime->retrieval );
	}

	/** Missing bots fail closed before retrieval or provider work. */
	public function test_missing_bot_fails_closed_before_retrieval_resolution(): void {
		self::assertTrue( class_exists( PublicChatRuntimeResolver::class ), 'PublicChatRuntimeResolver is missing.' );

		$bots = $this->createMock( BotRepository::class );
		$bots->expects( self::once() )->method( 'find' )->willReturn( null );

		$bindings = $this->createMock( BotRetrievalBindingRepository::class );
		$bindings->expects( self::never() )->method( 'find' );

		$this->expectException( RuntimeException::class );
		( new PublicChatRuntimeResolver( $bots, $bindings ) )->resolve( '0123456789abcdef0123456789abcdef' );
	}

	/** Disabled bots fail closed before retrieval or paid runtime work. */
	public function test_disabled_bot_fails_closed_before_retrieval_resolution(): void {
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

		$bots = $this->createMock( BotRepository::class );
		$bots->expects( self::once() )->method( 'find' )->willReturn( $bot );

		$bindings = $this->createMock( BotRetrievalBindingRepository::class );
		$bindings->expects( self::never() )->method( 'find' );

		$this->expectException( RuntimeException::class );
		( new PublicChatRuntimeResolver( $bots, $bindings ) )->resolve( $bot->id->value );
	}

	/** Enabled bots without trusted persisted retrieval authority fail closed. */
	public function test_missing_retrieval_binding_fails_closed(): void {
		self::assertTrue( class_exists( PublicChatRuntimeResolver::class ), 'PublicChatRuntimeResolver is missing.' );

		$bot = new Bot(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			'Support bot',
			true,
			'openai',
			'gpt-4.1-mini',
			1,
			'2026-09-12 00:00:00',
			'2026-09-12 00:00:00'
		);

		$bots = $this->createMock( BotRepository::class );
		$bots->expects( self::once() )->method( 'find' )->willReturn( $bot );

		$bindings = $this->createMock( BotRetrievalBindingRepository::class );
		$bindings->expects( self::once() )->method( 'find' )->with( $bot->id )->willReturn( null );

		$this->expectException( RuntimeException::class );
		( new PublicChatRuntimeResolver( $bots, $bindings ) )->resolve( $bot->id->value );
	}
}
