<?php
/**
 * Generation provider contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

/**
 * Provider-neutral text generation boundary.
 */
interface GenerationProvider {
	/**
	 * Return the stable provider ID.
	 */
	public function provider_id(): string;

	/**
	 * Report runtime availability without issuing a paid request.
	 */
	public function available(): bool;

	/**
	 * Generate one normalized text result.
	 *
	 * @param GenerationRequest $request Normalized request.
	 */
	public function generate( GenerationRequest $request ): GenerationResult;
}
