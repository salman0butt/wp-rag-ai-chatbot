<?php
/**
 * Bot administration REST resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Bots\Bot;
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
