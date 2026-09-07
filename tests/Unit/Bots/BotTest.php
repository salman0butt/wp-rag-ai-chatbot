<?php
/**
 * M12 bot aggregate tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Bots;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;

/**
 * Defines the bounded bot configuration aggregate required by M12.
 */
final class BotTest extends TestCase {
	/** A valid bot preserves only the M12 configuration fields. */
	public function test_valid_bot_preserves_configuration_and_version(): void {
		self::assertTrue( class_exists( Bot::class ), 'M12 Task 2 requires Bot.' );
		self::assertTrue( class_exists( BotId::class ), 'M12 Task 2 requires BotId.' );

		$bot = new Bot(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			'Support Bot',
			true,
			'openai',
			'gpt-5-mini',
			3,
			'2026-09-07 06:00:00',
			'2026-09-07 06:05:00'
		);

		self::assertSame( '0123456789abcdef0123456789abcdef', $bot->id->value );
		self::assertSame( 'Support Bot', $bot->name );
		self::assertTrue( $bot->enabled );
		self::assertSame( 'openai', $bot->provider_id );
		self::assertSame( 'gpt-5-mini', $bot->model_id );
		self::assertSame( 3, $bot->version );
	}

	/** Bot identifiers must be canonical stable lowercase hexadecimal values. */
	public function test_bot_id_rejects_non_canonical_values(): void {
		self::assertTrue( class_exists( BotId::class ), 'M12 Task 2 requires BotId.' );

		$this->expectException( InvalidArgumentException::class );
		new BotId( 'not-a-stable-id' );
	}

	/** Human names are required. */
	public function test_bot_rejects_blank_name(): void {
		self::assertTrue( class_exists( Bot::class ), 'M12 Task 2 requires Bot.' );

		$this->expectException( InvalidArgumentException::class );
		new Bot(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			'   ',
			true,
			'openai',
			'gpt-5-mini',
			1,
			'2026-09-07 06:00:00',
			'2026-09-07 06:00:00'
		);
	}

	/** Provider/model selections are mandatory persisted bot configuration. */
	public function test_bot_rejects_blank_provider_or_model(): void {
		self::assertTrue( class_exists( Bot::class ), 'M12 Task 2 requires Bot.' );

		$this->expectException( InvalidArgumentException::class );
		new Bot(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			'Support Bot',
			true,
			'',
			'gpt-5-mini',
			1,
			'2026-09-07 06:00:00',
			'2026-09-07 06:00:00'
		);
	}

	/** Version zero cannot represent a persisted bot. */
	public function test_bot_rejects_non_positive_version(): void {
		self::assertTrue( class_exists( Bot::class ), 'M12 Task 2 requires Bot.' );

		$this->expectException( InvalidArgumentException::class );
		new Bot(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			'Support Bot',
			true,
			'openai',
			'gpt-5-mini',
			0,
			'2026-09-07 06:00:00',
			'2026-09-07 06:00:00'
		);
	}
}
