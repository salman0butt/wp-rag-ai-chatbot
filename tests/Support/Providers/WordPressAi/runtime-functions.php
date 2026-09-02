<?php
/**
 * WordPress 7 AI Client global function shims for isolated-process tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

use WpRagAiChatbot\Tests\Support\Providers\WordPressAi\FakeWordPressAiError;
use WpRagAiChatbot\Tests\Support\Providers\WordPressAi\RuntimeShim;

if ( ! class_exists( 'WP_Error', false ) ) {
	class_alias( FakeWordPressAiError::class, 'WP_Error' );
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Isolated test shims mirror exact WordPress public API names.
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
