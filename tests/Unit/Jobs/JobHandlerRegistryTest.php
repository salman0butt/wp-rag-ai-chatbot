<?php
/**
 * M09 typed job handler registry behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;

/**
 * Defines the allowlisted handler boundary before production implementation.
 */
final class JobHandlerRegistryTest extends TestCase {
	/**
	 * The worker contract exposes a typed handler interface.
	 */
	public function test_job_handler_contract_exists(): void {
		self::assertTrue(
			interface_exists( 'WpRagAiChatbot\\Jobs\\JobHandler' ),
			'M09 Task 3 requires the typed JobHandler contract.'
		);
	}

	/**
	 * The worker resolves handlers only through an explicit registry.
	 */
	public function test_job_handler_registry_exists(): void {
		self::assertTrue(
			class_exists( 'WpRagAiChatbot\\Jobs\\JobHandlerRegistry' ),
			'M09 Task 3 requires the allowlisted JobHandlerRegistry.'
		);
	}
}
