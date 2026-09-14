<?php
/**
 * M16 conversation administration REST resource contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

/** Specifies the dedicated conversation administration REST projection boundary. */
final class ConversationRestResourceContractTest extends TestCase {
	/** The M16 REST projection is a dedicated resource instead of widening persistence repositories. */
	public function test_conversation_rest_resource_boundary_exists(): void {
		$class = 'WpRagAiChatbot\\Admin\\Rest\\ConversationRestResource';

		self::assertTrue( class_exists( $class ), 'ConversationRestResource is missing.' );
		self::assertTrue( method_exists( $class, 'list' ), 'Conversation list projection is missing.' );
		self::assertTrue( method_exists( $class, 'read' ), 'Conversation detail projection is missing.' );
		self::assertTrue( method_exists( $class, 'delete' ), 'Conversation delete projection is missing.' );
	}
}
