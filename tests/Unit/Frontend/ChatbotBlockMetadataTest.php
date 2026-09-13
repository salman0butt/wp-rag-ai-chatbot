<?php
/**
 * Gutenberg chatbot block metadata tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

/** Verifies the packaged dynamic block stays a bounded adapter. */
final class ChatbotBlockMetadataTest extends TestCase {
	/** Packaged metadata exposes only bot ID and the existing editor entry. */
	public function test_metadata_exposes_only_bounded_bot_attribute_and_editor_script(): void {
		$metadata_path = dirname( __DIR__, 3 ) . '/blocks/chatbot/block.json';

		self::assertFileExists( $metadata_path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local packaged metadata fixture.
		$contents = file_get_contents( $metadata_path );
		self::assertIsString( $contents );

		/**
		 * Decoded block metadata.
		 *
		 * @var array<string,mixed> $metadata
		 */
		$metadata = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );

		self::assertSame( 3, $metadata['apiVersion'] ?? null );
		self::assertSame( 'wp-rag-ai-chatbot/chatbot', $metadata['name'] ?? null );
		self::assertSame(
			array(
				'bot' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			$metadata['attributes'] ?? null
		);
		self::assertSame(
			array( 'wp-blocks', 'wp-element', 'file:../../build/chatbot-block.js' ),
			$metadata['editorScript'] ?? null
		);
		self::assertArrayNotHasKey( 'provider', $metadata['attributes'] ?? array() );
		self::assertArrayNotHasKey( 'model', $metadata['attributes'] ?? array() );
		self::assertArrayNotHasKey( 'retrieval_limit', $metadata['attributes'] ?? array() );
	}
}
