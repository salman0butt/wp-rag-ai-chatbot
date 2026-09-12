<?php
/**
 * Tests for the typed persisted Playground configuration aggregate.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Admin\Rest\PlaygroundConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalConfiguration;
use WpRagAiChatbot\Bots\Bot;

/**
 * Verifies the closed typed persisted Playground configuration contract.
 */
final class PlaygroundConfigurationTest extends TestCase {
	/**
	 * The aggregate carries only already-resolved persisted configuration values.
	 */
	public function test_it_carries_only_the_resolved_bot_and_retrieval_selection(): void {
		$bot       = ( new ReflectionClass( Bot::class ) )->newInstanceWithoutConstructor();
		$retrieval = ( new ReflectionClass( PlaygroundRetrievalConfiguration::class ) )->newInstanceWithoutConstructor();

		$configuration = new PlaygroundConfiguration( $bot, $retrieval );

		self::assertSame( $bot, $configuration->bot );
		self::assertSame( $retrieval, $configuration->retrieval );
	}
}
