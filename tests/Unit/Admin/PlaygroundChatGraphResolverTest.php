<?php
/**
 * Playground production chat-graph composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Specifies the request-local production Playground chat-graph composition boundary.
 */
final class PlaygroundChatGraphResolverTest extends TestCase {
	/**
	 * The Playground requires one dedicated composition boundary around the existing M10/M11 graph.
	 */
	public function test_playground_chat_graph_resolver_contract_exists(): void {
		self::assertTrue(
			class_exists( 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundChatGraphResolver' ),
			'PlaygroundChatGraphResolver contract is missing.'
		);
	}
}
