<?php
/**
 * M12 bot REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\BotRestResource;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;

/**
 * Defines stable repository-backed bot REST mapping behavior.
 */
final class BotRestResourceTest extends TestCase {
	/** List responses serialize only bounded M12 bot fields and pagination. */
	public function test_list_serializes_repository_page(): void {
		$bot = new Bot(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			'Support Bot',
			true,
			'openai',
			'gpt-5-mini',
			2,
			'2026-09-07 06:00:00',
			'2026-09-07 06:05:00'
		);
		$repository = $this->createMock( BotRepository::class );
		$repository->method( 'list' )->willReturn(
			array(
				'items'    => array( $bot ),
				'total'    => 26,
				'page'     => 2,
				'per_page' => 25,
			)
		);

		$response = ( new BotRestResource( $repository ) )->list( 2, 25 );

		self::assertSame( 26, $response['total'] );
		self::assertSame( 2, $response['page'] );
		self::assertSame( 25, $response['per_page'] );
		self::assertSame( '0123456789abcdef0123456789abcdef', $response['items'][0]['id'] );
		self::assertSame( 'Support Bot', $response['items'][0]['name'] );
		self::assertTrue( $response['items'][0]['enabled'] );
		self::assertSame( 'openai', $response['items'][0]['provider_id'] );
		self::assertSame( 'gpt-5-mini', $response['items'][0]['model_id'] );
		self::assertSame( 2, $response['items'][0]['version'] );
		self::assertSame( '2026-09-07 06:00:00', $response['items'][0]['created_at'] );
		self::assertSame( '2026-09-07 06:05:00', $response['items'][0]['updated_at'] );
	}
}
