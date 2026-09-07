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
	/**
	 * Create one bot.
	 *
	 * @param string $name Human-readable bot name.
	 * @param bool   $enabled Whether the bot is enabled.
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id Model identifier.
	 */
	public function create( string $name, bool $enabled, string $provider_id, string $model_id ): Bot;

	/**
	 * Find one bot by stable identifier.
	 *
	 * @param BotId $id Stable bot identifier.
	 */
	public function find( BotId $id ): ?Bot;

	/**
	 * Update one bot only when its expected version is current.
	 *
	 * @param BotId  $id Stable bot identifier.
	 * @param int    $expected_version Expected optimistic version.
	 * @param string $name Human-readable bot name.
	 * @param bool   $enabled Whether the bot is enabled.
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id Model identifier.
	 */
	public function update(
		BotId $id,
		int $expected_version,
		string $name,
		bool $enabled,
		string $provider_id,
		string $model_id
	): Bot;

	/**
	 * Delete one bot by stable identifier.
	 *
	 * @param BotId $id Stable bot identifier.
	 */
	public function delete( BotId $id ): bool;

	/**
	 * List a deterministic bounded page.
	 *
	 * @param int $page One-based page number.
	 * @param int $per_page Requested page size.
	 * @return array{items:array<int,Bot>,total:int,page:int,per_page:int}
	 */
	public function list( int $page, int $per_page ): array;
}
