<?php
/**
 * M12 bot REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
		$bot = $this->bot( '0123456789abcdef0123456789abcdef', 'Support Bot', 2 );
		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::once() )
			->method( 'list' )
			->with( 2, 25 )
			->willReturn(
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
		self::assertSame(
			array(
				'id'          => '0123456789abcdef0123456789abcdef',
				'name'        => 'Support Bot',
				'enabled'     => true,
				'provider_id' => 'openai',
				'model_id'    => 'gpt-5-mini',
				'version'     => 2,
				'created_at'  => '2026-09-07 06:00:00',
				'updated_at'  => '2026-09-07 06:05:00',
			),
			$response['items'][0]
		);
	}

	/** Create maps valid input and normalizes invalid aggregate input. */
	public function test_create_maps_valid_input_and_normalizes_validation_errors(): void {
		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::once() )
			->method( 'create' )
			->with( 'Support Bot', true, 'openai', 'gpt-5-mini' )
			->willReturn( $this->bot( '0123456789abcdef0123456789abcdef', 'Support Bot', 1 ) );
		$resource = new BotRestResource( $repository );

		$created = $resource->create(
			array(
				'name'        => 'Support Bot',
				'enabled'     => true,
				'provider_id' => 'openai',
				'model_id'    => 'gpt-5-mini',
			)
		);
		$invalid = $resource->create(
			array(
				'name'        => '',
				'enabled'     => true,
				'provider_id' => 'openai',
				'model_id'    => 'gpt-5-mini',
			)
		);

		self::assertSame( 'Support Bot', $created['bot']['name'] );
		self::assertSame( 'invalid_bot', $invalid['error']['code'] );
	}

	/** Read distinguishes invalid identifiers from valid-but-missing identifiers. */
	public function test_read_normalizes_invalid_and_missing_ids(): void {
		$repository = $this->createMock( BotRepository::class );
		$repository->method( 'find' )->willReturn( null );
		$resource = new BotRestResource( $repository );

		self::assertSame( 'invalid_bot_id', $resource->read( 'bad-id' )['error']['code'] );
		self::assertSame(
			'bot_not_found',
			$resource->read( str_repeat( 'a', 32 ) )['error']['code']
		);
	}

	/** Update preserves optimistic isolation and normalizes stale targets. */
	public function test_update_maps_expected_version_and_normalizes_stale_target(): void {
		$id = new BotId( '0123456789abcdef0123456789abcdef' );
		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::exactly( 2 ) )
			->method( 'update' )
			->with( $id, 2, 'Support Bot', false, 'openai', 'gpt-5-mini' )
			->willReturnOnConsecutiveCalls(
				$this->bot( $id->value, 'Support Bot', 3, false ),
				$this->throwException( new RuntimeException( 'Bot update target is missing or stale.' ) )
			);
		$resource = new BotRestResource( $repository );
		$payload  = array(
			'version'     => 2,
			'name'        => 'Support Bot',
			'enabled'     => false,
			'provider_id' => 'openai',
			'model_id'    => 'gpt-5-mini',
		);

		$updated = $resource->update( $id->value, $payload );
		$stale   = $resource->update( $id->value, $payload );

		self::assertSame( 3, $updated['bot']['version'] );
		self::assertFalse( $updated['bot']['enabled'] );
		self::assertSame( 'stale_or_missing_bot', $stale['error']['code'] );
	}

	/** Delete reports deterministic missing state and never widens the target. */
	public function test_delete_targets_exact_id_and_reports_missing_state(): void {
		$id = new BotId( '0123456789abcdef0123456789abcdef' );
		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::exactly( 2 ) )
			->method( 'delete' )
			->with( $id )
			->willReturnOnConsecutiveCalls( true, false );
		$resource = new BotRestResource( $repository );

		self::assertSame( array( 'deleted' => true ), $resource->delete( $id->value ) );
		self::assertSame(
			'bot_not_found',
			$resource->delete( $id->value )['error']['code']
		);
	}

	/**
	 * Build one deterministic bot fixture.
	 *
	 * @param string $id Bot identifier.
	 * @param string $name Bot name.
	 * @param int    $version Bot version.
	 * @param bool   $enabled Enabled state.
	 */
	private function bot( string $id, string $name, int $version, bool $enabled = true ): Bot {
		return new Bot(
			new BotId( $id ),
			$name,
			$enabled,
			'openai',
			'gpt-5-mini',
			$version,
			'2026-09-07 06:00:00',
			'2026-09-07 06:05:00'
		);
	}
}
