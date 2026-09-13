<?php
/**
 * Public widget mount contract tests.
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
use WpRagAiChatbot\Frontend\PublicWidgetMount;
use WpRagAiChatbot\Frontend\WidgetConfigResolver;

/**
 * Verifies the closed public shortcode/mount attribute boundary.
 */
final class PublicWidgetMountTest extends TestCase {
	private const BOT_ID = '0123456789abcdef0123456789abcdef';

	/** Enabled bot identifiers resolve through the existing public-safe widget projection. */
	public function test_valid_bot_attribute_resolves_existing_public_widget_projection(): void {
		$bot_id      = new BotId( self::BOT_ID );
		$bots        = $this->createMock( BotRepository::class );
		$appearances = $this->createMock( BotAppearanceRepository::class );
		$bot         = new Bot(
			$bot_id,
			'Support',
			true,
			'openai',
			'gpt-5',
			1,
			'2026-09-12 00:00:00',
			'2026-09-12 00:00:00'
		);

		$bots->expects( self::once() )
			->method( 'find' )
			->with( self::callback( static fn ( BotId $id ): bool => self::BOT_ID === $id->value ) )
			->willReturn( $bot );
		$appearances->expects( self::once() )
			->method( 'find' )
			->with( self::callback( static fn ( BotId $id ): bool => self::BOT_ID === $id->value ) )
			->willReturn( AppearanceConfig::defaults() );

		$mount  = new PublicWidgetMount( new WidgetConfigResolver( $bots, $appearances ) );
		$config = $mount->resolve( array( 'bot' => self::BOT_ID ) );

		self::assertNotNull( $config );
		self::assertSame( self::BOT_ID, $config->bot_id );
		self::assertSame( 'Support', $config->name );
		self::assertSame( AppearanceConfig::defaults()->to_array(), $config->appearance->to_array() );
	}

	/** Invalid or missing bot identifiers fail closed before repository access. */
	public function test_missing_or_invalid_bot_attribute_fails_closed(): void {
		$bots        = $this->createMock( BotRepository::class );
		$appearances = $this->createMock( BotAppearanceRepository::class );
		$bots->expects( self::never() )->method( 'find' );
		$appearances->expects( self::never() )->method( 'find' );
		$mount = new PublicWidgetMount( new WidgetConfigResolver( $bots, $appearances ) );

		self::assertNull( $mount->resolve( array() ) );
		self::assertNull( $mount->resolve( array( 'bot' => 'not-a-bot-id' ) ) );
	}

	/** Runtime/provider override-like shortcode attributes are rejected rather than becoming browser authority. */
	public function test_unknown_runtime_override_attributes_fail_closed(): void {
		$bots        = $this->createMock( BotRepository::class );
		$appearances = $this->createMock( BotAppearanceRepository::class );
		$bots->expects( self::never() )->method( 'find' );
		$appearances->expects( self::never() )->method( 'find' );
		$mount = new PublicWidgetMount( new WidgetConfigResolver( $bots, $appearances ) );

		self::assertNull(
			$mount->resolve(
				array(
					'bot'             => self::BOT_ID,
					'provider'        => 'attacker-provider',
					'model'           => 'attacker-model',
					'retrieval_limit' => 999,
				)
			)
		);
	}
}
