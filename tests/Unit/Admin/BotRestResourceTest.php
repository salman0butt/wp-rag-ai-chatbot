<?php
/**
 * M12 bot REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\BotRestResource;

/**
 * Defines the administration bot REST resource seam.
 */
final class BotRestResourceTest extends TestCase {
	/** Task 3 requires a dedicated repository-backed bot REST resource. */
	public function test_bot_rest_resource_contract_exists(): void {
		self::assertTrue(
			class_exists( BotRestResource::class ),
			'M12 Task 3 requires BotRestResource.'
		);
	}
}
