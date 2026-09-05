<?php
/**
 * M09 typed job handler registry behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobExecutionContext;
use WpRagAiChatbot\Jobs\JobHandler;
use WpRagAiChatbot\Jobs\JobHandlerRegistry;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRecord;

/**
 * Defines the allowlisted handler boundary before production implementation.
 */
final class JobHandlerRegistryTest extends TestCase {
	/**
	 * Duplicate stable job types are rejected instead of silently replaced.
	 */
	public function test_duplicate_handler_registration_is_rejected(): void {
		$registry = new JobHandlerRegistry();
		$registry->register( $this->handler( 'index.document' ) );

		$this->expectException( JobQueueException::class );
		$registry->register( $this->handler( 'index.document' ) );
	}

	/**
	 * Persisted unknown types cannot resolve arbitrary classes or callables.
	 */
	public function test_unknown_handler_type_is_rejected(): void {
		$registry = new JobHandlerRegistry();

		$this->expectException( JobQueueException::class );
		$registry->for_type( 'php.system.exec' );
	}

	/**
	 * Explicitly registered handlers resolve by their stable persisted type.
	 */
	public function test_registered_handler_resolves_by_type(): void {
		$registry = new JobHandlerRegistry();
		$handler  = $this->handler( 'index.document' );
		$registry->register( $handler );

		self::assertSame( $handler, $registry->for_type( 'index.document' ) );
	}

	/**
	 * Handler types must follow the same stable grammar as queued requests.
	 */
	public function test_invalid_registered_handler_type_is_rejected(): void {
		$registry = new JobHandlerRegistry();

		$this->expectException( JobQueueException::class );
		$registry->register( $this->handler( 'Invalid Handler' ) );
	}

	/**
	 * Build one no-op typed handler fixture.
	 *
	 * @param string $type Stable persisted handler type.
	 */
	private function handler( string $type ): JobHandler {
		return new class( $type ) implements JobHandler {
			/**
			 * Create the no-op handler fixture.
			 *
			 * @param string $job_type Stable persisted handler type.
			 */
			public function __construct( private readonly string $job_type ) {
			}

			/**
			 * Return the fixture type.
			 */
			public function type(): string {
				return $this->job_type;
			}

			/**
			 * No-op handler body used only for registry tests.
			 *
			 * @param JobRecord           $job Current persisted job.
			 * @param JobExecutionContext $context Current execution context.
			 */
			public function handle( JobRecord $job, JobExecutionContext $context ): void {
			}
		};
	}
}
