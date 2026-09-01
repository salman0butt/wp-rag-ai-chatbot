<?php
/**
 * Plugin entry-point tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the public WordPress plugin entry point.
 */
final class PluginEntryPointTest extends TestCase {
	/**
	 * The entry file declares the supported runtime and delegates bootstrap work.
	 */
	public function test_plugin_entry_point_declares_runtime_and_delegates_bootstrap(): void {
		$path = dirname( __DIR__, 2 ) . '/wp-rag-ai-chatbot.php';

		self::assertFileExists( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local repository fixture, not a URL.
		$contents = (string) file_get_contents( $path );

		self::assertStringContainsString( 'Plugin Name: WP RAG AI Chatbot', $contents );
		self::assertStringContainsString( 'Requires at least: 6.9', $contents );
		self::assertStringContainsString( 'Requires PHP: 8.2', $contents );
		self::assertStringContainsString( 'Text Domain: wp-rag-ai-chatbot', $contents );
		self::assertStringContainsString( "defined( 'ABSPATH' ) || exit;", $contents );
		self::assertStringContainsString( 'Bootstrap::register( __FILE__ );', $contents );
	}
}
