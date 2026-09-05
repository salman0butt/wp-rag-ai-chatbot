<?php
/**
 * M09 typed job handler registry behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobHandlerRegistry;

/**
 * Defines the allowlisted handler boundary before production implementation.
 */
final class JobHandlerRegistryTest extends TestCase {
	/**
	 * Registry exposes explicit registration instead of persisted callable lookup.
	 */
	public function test_registry_exposes_explicit_registration(): void {
		$registry = new JobHandlerRegistry();

		self::assertTrue(
			method_exists( $registry, 'register' ),
			'M09 Task 3 requires explicit typed handler registration.'
		);
	}

	/**
	 * Registry exposes stable-type resolution instead of arbitrary class execution.
	 */
	public function test_registry_exposes_stable_type_resolution(): void {
		$registry = new JobHandlerRegistry();

		self::assertTrue(
			method_exists( $registry, 'for_type' ),
			'M09 Task 3 requires allowlisted stable-type handler resolution.'
		);
	}
}
