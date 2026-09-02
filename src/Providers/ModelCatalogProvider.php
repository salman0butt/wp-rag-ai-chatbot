<?php
/**
 * Model catalog provider contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

/**
 * Provider-neutral model discovery boundary.
 */
interface ModelCatalogProvider {
	/**
	 * Return the stable provider ID.
	 */
	public function provider_id(): string;

	/**
	 * Return normalized models.
	 *
	 * @return ModelInfo[]
	 */
	public function models(): array;
}
