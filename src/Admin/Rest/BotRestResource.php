<?php
/**
 * Bot administration REST resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use InvalidArgumentException;
use RuntimeException;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;

/**
 * Repository-backed bot REST resource boundary.
 */
final class BotRestResource {
	/**
	 * Create the bot REST resource.
	 *
	 * @param BotRepository $repository Bot persistence boundary.
	 */
	public function __construct( private readonly BotRepository $repository ) {
	}

	/**
	 * Return one deterministic bot page for REST serialization.
	 *
	 * @param int $page Requested page number.
	 * @param int $per_page Requested page size.
	 * @return array{items:list<array{id:string,name:string,enabled:bool,provider_id:string,model_id:string,version:int,created_at:string,updated_at:string}>,total:int,page:int,per_page:int}
	 */
	public function list( int $page, int $per_page ): array {
		$result = $this->repository->list( $page, $per_page );

		return array(
			'items'    => array_values( array_map( self::serialize( ... ), $result['items'] ) ),
			'total'    => $result['total'],
			'page'     => $result['page'],
			'per_page' => $result['per_page'],
		);
	}

	/**
	 * Create a bot from the bounded M12 settings contract.
	 *
	 * @param array{name:string,enabled:bool,provider_id:string,model_id:string} $payload Bot settings.
	 * @return array{bot:array{id:string,name:string,enabled:bool,provider_id:string,model_id:string,version:int,created_at:string,updated_at:string}}|array{error:array{code:string,message:string}}
	 */
	public function create( array $payload ): array {
		try {
			$bot = $this->repository->create(
				$payload['name'],
				$payload['enabled'],
				$payload['provider_id'],
				$payload['model_id']
			);
		} catch ( InvalidArgumentException ) {
			return self::error( 'invalid_bot', 'Bot settings are invalid.' );
		}

		return array( 'bot' => self::serialize( $bot ) );
	}

	/**
	 * Read one bot by its deterministic identifier.
	 *
	 * @param string $id Bot identifier.
	 * @return array{bot:array{id:string,name:string,enabled:bool,provider_id:string,model_id:string,version:int,created_at:string,updated_at:string}}|array{error:array{code:string,message:string}}
	 */
	public function read( string $id ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}

		$bot = $this->repository->find( $bot_id );
		if ( null === $bot ) {
			return self::error( 'bot_not_found', 'Bot was not found.' );
		}

		return array( 'bot' => self::serialize( $bot ) );
	}

	/**
	 * Update one bot using optimistic concurrency.
	 *
	 * @param string $id Bot identifier.
	 * @param array{version:int,name:string,enabled:bool,provider_id:string,model_id:string} $payload Bot settings.
	 * @return array{bot:array{id:string,name:string,enabled:bool,provider_id:string,model_id:string,version:int,created_at:string,updated_at:string}}|array{error:array{code:string,message:string}}
	 */
	public function update( string $id, array $payload ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}

		try {
			$bot = $this->repository->update(
				$bot_id,
				$payload['version'],
				$payload['name'],
				$payload['enabled'],
				$payload['provider_id'],
				$payload['model_id']
			);
		} catch ( InvalidArgumentException ) {
			return self::error( 'invalid_bot', 'Bot settings are invalid.' );
		} catch ( RuntimeException ) {
			return self::error( 'stale_or_missing_bot', 'Bot update target is missing or stale.' );
		}

		return array( 'bot' => self::serialize( $bot ) );
	}

	/**
	 * Delete exactly one bot by identifier.
	 *
	 * @param string $id Bot identifier.
	 * @return array{deleted:true}|array{error:array{code:string,message:string}}
	 */
	public function delete( string $id ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}

		if ( ! $this->repository->delete( $bot_id ) ) {
			return self::error( 'bot_not_found', 'Bot was not found.' );
		}

		return array( 'deleted' => true );
	}

	/**
	 * Parse one bot identifier without leaking aggregate validation exceptions.
	 *
	 * @param string $id Bot identifier.
	 */
	private static function parse_id( string $id ): ?BotId {
		try {
			return new BotId( $id );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Build a stable public error payload.
	 *
	 * @param string $code Stable machine-readable code.
	 * @param string $message Stable human-readable message.
	 * @return array{error:array{code:string,message:string}}
	 */
	private static function error( string $code, string $message ): array {
		return array(
			'error' => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}

	/**
	 * Serialize only the bounded M12 bot settings contract.
	 *
	 * @param Bot $bot Bot aggregate to serialize.
	 * @return array{id:string,name:string,enabled:bool,provider_id:string,model_id:string,version:int,created_at:string,updated_at:string}
	 */
	private static function serialize( Bot $bot ): array {
		return array(
			'id'          => $bot->id->value,
			'name'        => $bot->name,
			'enabled'     => $bot->enabled,
			'provider_id' => $bot->provider_id,
			'model_id'    => $bot->model_id,
			'version'     => $bot->version,
			'created_at'  => $bot->created_at,
			'updated_at'  => $bot->updated_at,
		);
	}
}
