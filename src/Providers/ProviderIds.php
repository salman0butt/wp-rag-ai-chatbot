<?php
/**
 * Stable provider identifiers.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

/**
 * Canonical provider IDs used by configuration and registries.
 */
final class ProviderIds {
	public const OPENAI_DIRECT       = 'openai_direct';
	public const OPENROUTER_DIRECT   = 'openrouter_direct';
	public const WORDPRESS_AI_CLIENT = 'wordpress_ai_client';

	/**
	 * Static constants only.
	 */
	private function __construct() {}
}
