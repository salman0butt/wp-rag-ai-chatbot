<?php
/**
 * Bot REST resource behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Admin\Rest\BotRestResource;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;

/**
 * Defines the stable, repository-backed contract used by WordPress REST callbacks.
 */
final class BotRestResourceTest extends TestCase {
	public function test_list_serializes_deterministic_repository_page(): void {
		$repository = new InMemoryBotRepository();
		$repository->create( 'Alpha', true, 'openai', 'gpt-5' );
		$repository->create( 'Beta', false, 'openrouter', 'model-b' );

		$response = ( new BotRestResource( $repository ) )->list( 1, 1 );

		self::assertSame( 2, $response['total'] );
		self::assertSame( 1, $response['page'] );
		self::assertSame( 1, $response['per_page'] );
		self::assertSame( 'Alpha', $response['items'][0]['name'] );
		self::assertArrayNotHasKey( 'secret', $response['items'][0] );
	}

	public function test_create_rejects_blank_required_fields_with_stable_error(): void {
		$response = ( new BotRestResource( new InMemoryBotRepository() ) )->create(
			array( 'name' => '', 'enabled' => true, 'provider_id' => 'openai', 'model_id' => 'gpt-5' )
		);

		self::assertSame( 'invalid_bot', $response['error']['code'] );
	}

	public function test_update_targets_only_requested_bot_and_increments_version(): void {
		$repository = new InMemoryBotRepository();
		$alpha      = $repository->create( 'Alpha', true, 'openai', 'gpt-5' );
		$beta       = $repository->create( 'Beta', true, 'openai', 'gpt-5' );
		$resource   = new BotRestResource( $repository );

		$response = $resource->update(
			$alpha->id->value,
			array( 'version' => 1, 'name' => 'Alpha 2', 'enabled' => false, 'provider_id' => 'openai', 'model_id' => 'gpt-5' )
		);

		self::assertSame( 'Alpha 2', $response['bot']['name'] );
		self::assertSame( 2, $response['bot']['version'] );
		self::assertSame( 'Beta', $repository->find( $beta->id )?->name );
	}

	public function test_stale_update_fails_deterministically(): void {
		$repository = new InMemoryBotRepository();
		$bot        = $repository->create( 'Alpha', true, 'openai', 'gpt-5' );
		$resource   = new BotRestResource( $repository );
		$resource->update( $bot->id->value, array( 'version' => 1, 'name' => 'Alpha 2', 'enabled' => true, 'provider_id' => 'openai', 'model_id' => 'gpt-5' ) );

		$response = $resource->update( $bot->id->value, array( 'version' => 1, 'name' => 'Alpha 3', 'enabled' => true, 'provider_id' => 'openai', 'model_id' => 'gpt-5' ) );

		self::assertSame( 'stale_or_missing_bot', $response['error']['code'] );
	}

	public function test_missing_and_invalid_ids_fail_without_touching_other_bots(): void {
		$repository = new InMemoryBotRepository();
		$other      = $repository->create( 'Other', true, 'openai', 'gpt-5' );
		$resource   = new BotRestResource( $repository );

		self::assertSame( 'invalid_bot_id', $resource->read( 'bad-id' )['error']['code'] );
		self::assertSame( 'bot_not_found', $resource->read( str_repeat( 'a', 32 ) )['error']['code'] );
		self::assertSame( 'Other', $repository->find( $other->id )?->name );
	}
}

/**
 * Minimal deterministic repository fake for resource-contract tests.
 */
final class InMemoryBotRepository implements BotRepository {
	/** @var array<string,Bot> */
	private array $bots = array();
	private int $next = 1;

	public function create( string $name, bool $enabled, string $provider_id, string $model_id ): Bot {
		if ( '' === trim( $name ) || '' === trim( $provider_id ) || '' === trim( $model_id ) ) {
			throw new \InvalidArgumentException( 'Bot text field must not be blank.' );
		}
		$id  = new BotId( str_pad( dechex( $this->next++ ), 32, '0', STR_PAD_LEFT ) );
		$bot = new Bot( $id, $name, $enabled, $provider_id, $model_id, 1, '2026-09-07 00:00:00', '2026-09-07 00:00:00' );
		$this->bots[ $id->value ] = $bot;
		return $bot;
	}

	public function find( BotId $id ): ?Bot {
		return $this->bots[ $id->value ] ?? null;
	}

	public function update( BotId $id, int $expected_version, string $name, bool $enabled, string $provider_id, string $model_id ): Bot {
		$current = $this->find( $id );
		if ( null === $current || $current->version !== $expected_version ) {
			throw new RuntimeException( 'Bot update target is missing or stale.' );
		}
		$bot = new Bot( $id, $name, $enabled, $provider_id, $model_id, $current->version + 1, $current->created_at, '2026-09-07 00:00:01' );
		$this->bots[ $id->value ] = $bot;
		return $bot;
	}

	public function delete( BotId $id ): bool {
		if ( ! isset( $this->bots[ $id->value ] ) ) {
			return false;
		}
		unset( $this->bots[ $id->value ] );
		return true;
	}

	public function list( int $page, int $per_page ): array {
		$items = array_values( $this->bots );
		return array(
			'items' => array_slice( $items, ( $page - 1 ) * $per_page, $per_page ),
			'total' => count( $items ),
			'page' => $page,
			'per_page' => $per_page,
		);
	}
}
