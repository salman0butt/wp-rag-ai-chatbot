<?php
/**
 * M14 public-safe widget configuration projection tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Frontend\AppearanceConfig;
use WpRagAiChatbot\Frontend\BotAppearanceRepository;
use WpRagAiChatbot\Frontend\WidgetConfig;
use WpRagAiChatbot\Frontend\WidgetConfigResolver;

/** Proves public widget configuration is explicit, bounded, and fail-closed. */
final class WidgetConfigResolverTest extends TestCase {
	/** Enabled bots expose only public identity, name, and normalized appearance. */
	public function test_resolve_projects_only_public_safe_enabled_bot_configuration(): void {
		self::assertTrue( class_exists( WidgetConfig::class ), 'M14 Task 2B requires WidgetConfig.' );
		self::assertTrue( class_exists( WidgetConfigResolver::class ), 'M14 Task 2B requires WidgetConfigResolver.' );

		$bot_id      = new BotId( '0123456789abcdef0123456789abcdef' );
		$bot         = $this->bot( $bot_id, true );
		$appearance  = AppearanceConfig::from_array( array( 'primary_color' => '#ABCDEF' ) );
		$bots        = $this->createMock( BotRepository::class );
		$appearances = $this->createMock( BotAppearanceRepository::class );

		$bots->expects( self::once() )->method( 'find' )->with( $bot_id )->willReturn( $bot );
		$appearances->expects( self::once() )->method( 'find' )->with( $bot_id )->willReturn( $appearance );

		$config = ( new WidgetConfigResolver( $bots, $appearances ) )->resolve( $bot_id );
		self::assertInstanceOf( WidgetConfig::class, $config );
		self::assertSame(
			array(
				'bot_id'     => $bot_id->value,
				'name'       => 'Support Bot',
				'appearance' => $appearance->to_array(),
			),
			$config->to_array()
		);
		self::assertArrayNotHasKey( 'provider_id', $config->to_array() );
		self::assertArrayNotHasKey( 'model_id', $config->to_array() );
	}

	/** Unknown bots fail closed without reading appearance state. */
	public function test_resolve_returns_null_for_unknown_bot(): void {
		self::assertTrue( class_exists( WidgetConfigResolver::class ), 'M14 Task 2B requires WidgetConfigResolver.' );

		$bot_id      = new BotId( '0123456789abcdef0123456789abcdef' );
		$bots        = $this->createMock( BotRepository::class );
		$appearances = $this->createMock( BotAppearanceRepository::class );
		$bots->expects( self::once() )->method( 'find' )->with( $bot_id )->willReturn( null );
		$appearances->expects( self::never() )->method( 'find' );

		self::assertNull( ( new WidgetConfigResolver( $bots, $appearances ) )->resolve( $bot_id ) );
	}

	/** Disabled bots fail closed without reading appearance state. */
	public function test_resolve_returns_null_for_disabled_bot(): void {
		self::assertTrue( class_exists( WidgetConfigResolver::class ), 'M14 Task 2B requires WidgetConfigResolver.' );

		$bot_id      = new BotId( '0123456789abcdef0123456789abcdef' );
		$bots        = $this->createMock( BotRepository::class );
		$appearances = $this->createMock( BotAppearanceRepository::class );
		$bots->expects( self::once() )->method( 'find' )->with( $bot_id )->willReturn( $this->bot( $bot_id, false ) );
		$appearances->expects( self::never() )->method( 'find' );

		self::assertNull( ( new WidgetConfigResolver( $bots, $appearances ) )->resolve( $bot_id ) );
	}

	/**
	 * Create a persisted bot aggregate for projection tests.
	 *
	 * @param BotId $id Stable bot identifier.
	 * @param bool  $enabled Whether the bot is enabled.
	 */
	private function bot( BotId $id, bool $enabled ): Bot {
		return new Bot(
			$id,
			'Support Bot',
			$enabled,
			'openai',
			'gpt-example',
			1,
			'2026-09-12 00:00:00',
			'2026-09-12 00:00:00'
		);
	}
}
