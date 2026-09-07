<?php
/**
 * Bot repository contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Bots;

/**
 * Persists independently addressable M12 bot configurations.
 */
interface BotRepository {
	/** Create one bot. */
	public function create( string $name, bool $enabled, string $provider_id, string $model_id ): Bot;

	/** Find one bot by stable identifier. */
	public function find( BotId $id ): ?Bot;

	/** Update one bot only when its expected version is current. */
	public function update(
		BotId $id,
		int $expected_version,
		string $name,
		bool $enabled,
		string $provider_id,
		string $model_id
	): Bot;

	/** Delete one bot by stable identifier. */
	public function delete( BotId $id ): bool;

	/**
	 * List a deterministic bounded page.
	 *
	 * @return array{items:array<int,Bot>,total:int,page:int,per_page:int}
	 */
	public function list( int $page, int $per_page ): array;
}
