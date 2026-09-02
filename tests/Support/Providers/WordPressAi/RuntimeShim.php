<?php
/**
 * WordPress AI runtime test state.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Providers\WordPressAi;

/**
 * Holds deterministic runtime state consumed by isolated WordPress API shims.
 */
final class RuntimeShim {
	/**
	 * Fake prompt builder returned by wp_ai_client_prompt().
	 *
	 * @var object
	 */
	public static object $builder;

	/**
	 * Whether wp_supports_ai() reports support.
	 *
	 * @var bool
	 */
	public static bool $supports_ai = true;

	/**
	 * Fake WP_Error object recognized by is_wp_error().
	 *
	 * @var object|null
	 */
	public static ?object $error = null;

	/**
	 * Inputs passed to wp_ai_client_prompt().
	 *
	 * @var string[]
	 */
	public static array $inputs = array();
}
