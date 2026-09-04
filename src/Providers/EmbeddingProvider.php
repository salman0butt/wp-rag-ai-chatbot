<?php
/**
 * Embedding provider contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

/**
 * Provider-neutral embedding boundary.
 */
interface EmbeddingProvider {
	/**
	 * Return the stable provider ID.
	 */
	public function provider_id(): string;

	/**
	 * Report runtime availability without issuing a paid request.
	 */
	public function available(): bool;

	/**
	 * Embed one normalized request.
	 *
	 * @param EmbeddingRequest $request Normalized request.
	 */
	public function embed( EmbeddingRequest $request ): EmbeddingResult;
}
