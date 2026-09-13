<?php
/**
 * Bot-scoped public retrieval binding tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\BotRetrievalBindingRepository;

/**
 * Specifies the bounded server-owned retrieval selection stored per bot.
 */
final class BotRetrievalBindingTest extends TestCase {
	/** A positive source and bounded collection key form one valid binding. */
	public function test_accepts_bounded_server_owned_binding(): void {
		self::assertTrue( class_exists( BotRetrievalBinding::class ), 'BotRetrievalBinding is missing.' );
		self::assertTrue( interface_exists( BotRetrievalBindingRepository::class ), 'BotRetrievalBindingRepository is missing.' );

		$binding = new BotRetrievalBinding( 42, 'support-en-v1' );

		self::assertSame( 42, $binding->source_id );
		self::assertSame( 'support-en-v1', $binding->collection_id );
	}

	/** Public runtime must never carry an invalid source identifier. */
	public function test_rejects_non_positive_source_identifier(): void {
		self::assertTrue( class_exists( BotRetrievalBinding::class ), 'BotRetrievalBinding is missing.' );

		$this->expectException( InvalidArgumentException::class );
		new BotRetrievalBinding( 0, 'support-en-v1' );
	}

	/** Collection keys use the same bounded portable identifier shape as Playground. */
	public function test_rejects_unbounded_or_malformed_collection_identifier(): void {
		self::assertTrue( class_exists( BotRetrievalBinding::class ), 'BotRetrievalBinding is missing.' );

		$this->expectException( InvalidArgumentException::class );
		new BotRetrievalBinding( 42, '../caller-selected' );
	}
}
