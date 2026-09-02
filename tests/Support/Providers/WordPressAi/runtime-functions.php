<?php
/**
 * WordPress 7 AI Client runtime function shims for isolated-process tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Providers\WordPressAi {
	/**
	 * Holds deterministic runtime state consumed by the global WordPress API shims.
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
}

namespace {
	use WpRagAiChatbot\Tests\Support\Providers\WordPressAi\RuntimeShim;

	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- These are isolated test shims for exact WordPress public API names.
	/**
	 * Return the deterministic fake WordPress AI prompt builder.
	 *
	 * @param string $input Prompt input.
	 */
	function wp_ai_client_prompt( string $input ): object {
		RuntimeShim::$inputs[] = $input;
		return RuntimeShim::$builder;
	}

	/**
	 * Report deterministic WordPress AI support.
	 */
	function wp_supports_ai(): bool {
		return RuntimeShim::$supports_ai;
	}

	/**
	 * Recognize only the deterministic fake error selected by the test.
	 *
	 * @param mixed $thing Candidate error object.
	 */
	function is_wp_error( mixed $thing ): bool {
		return null !== RuntimeShim::$error && RuntimeShim::$error === $thing;
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
}
