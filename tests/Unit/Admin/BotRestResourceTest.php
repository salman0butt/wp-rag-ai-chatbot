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
		$bot        = $this->bot( '0123456789abcdef0123456789abcdef', 2 );
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

	/** Create maps valid settings through the repository and serializes the result. */
	public function test_create_maps_valid_settings(): void {
		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::once() )
			->method( 'create' )
			->with( 'Support Bot', true, 'openai', 'gpt-5-mini' )
			->willReturn( $this->bot( '0123456789abcdef0123456789abcdef', 1 ) );

		$response = ( new BotRestResource( $repository ) )->create(
			array(
				'name'        => 'Support Bot',
				'enabled'     => true,
				'provider_id' => 'openai',
				'model_id'    => 'gpt-5-mini',
			)
		);

		self::assertSame( 'Support Bot', $response['bot']['name'] );
		self::assertSame( 1, $response['bot']['version'] );
	}

	/** Aggregate validation failures become a stable public error. */
	public function test_create_normalizes_validation_failure(): void {
		$repository = $this->createMock( BotRepository::class );
		$repository->method( 'create' )
			->willThrowException( new InvalidArgumentException( 'Bot text field must not be blank.' ) );

		$response = ( new BotRestResource( $repository ) )->create(
			array(
				'name'        => '',
				'enabled'     => true,
				'provider_id' => 'openai',
				'model_id'    => 'gpt-5-mini',
			)
		);

		self::assertSame( 'invalid_bot', $response['error']['code'] );
	}

	/** Read distinguishes invalid identifiers from valid-but-missing records. */
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

	/** Update forwards the optimistic version and serializes only the targeted bot. */
	public function test_update_maps_expected_version(): void {
		$id         = new BotId( '0123456789abcdef0123456789abcdef' );
		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::once() )
			->method( 'update' )
			->with( $id, 2, 'Support Bot', false, 'openai', 'gpt-5-mini' )
			->willReturn( $this->bot( $id->value, 3, false ) );
		$resource = new BotRestResource( $repository );

		$response = $resource->update(
			$id->value,
			array(
				'version'     => 2,
				'name'        => 'Support Bot',
				'enabled'     => false,
				'provider_id' => 'openai',
				'model_id'    => 'gpt-5-mini',
			)
		);

		self::assertSame( 3, $response['bot']['version'] );
		self::assertFalse( $response['bot']['enabled'] );
	}

	/** Stale optimistic writes become a stable public error. */
	public function test_update_normalizes_stale_target(): void {
		$repository = $this->createMock( BotRepository::class );
		$repository->method( 'update' )
			->willThrowException( new RuntimeException( 'Bot update target is missing or stale.' ) );
		$resource = new BotRestResource( $repository );

		$response = $resource->update(
			'0123456789abcdef0123456789abcdef',
			array(
				'version'     => 2,
				'name'        => 'Support Bot',
				'enabled'     => false,
				'provider_id' => 'openai',
				'model_id'    => 'gpt-5-mini',
			)
		);

		self::assertSame( 'stale_or_missing_bot', $response['error']['code'] );
	}

	/** Delete targets exactly one valid identifier and reports missing state. */
	public function test_delete_maps_success_and_missing_state(): void {
		$id         = new BotId( '0123456789abcdef0123456789abcdef' );
		$repository = $this->createMock( BotRepository::class );
		$repository->expects( self::exactly( 2 ) )
			->method( 'delete' )
			->with( $id )
			->willReturnOnConsecutiveCalls( true, false );
		$resource = new BotRestResource( $repository );

		self::assertSame( array( 'deleted' => true ), $resource->delete( $id->value ) );
		self::assertSame( 'bot_not_found', $resource->delete( $id->value )['error']['code'] );
	}

	/**
	 * Build one deterministic bot fixture.
	 *
	 * @param string $id Bot identifier.
	 * @param int    $version Bot optimistic version.
	 * @param bool   $enabled Enabled state.
	 */
	private function bot( string $id, int $version, bool $enabled = true ): Bot {
		return new Bot(
			new BotId( $id ),
			'Support Bot',
			$enabled,
			'openai',
			'gpt-5-mini',
			$version,
			'2026-09-07 06:00:00',
			'2026-09-07 06:05:00'
		);
	}
}
