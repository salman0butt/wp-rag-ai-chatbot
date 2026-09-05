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
 * Defines the allowlisted handler behavior.
 */
final class JobHandlerRegistryTest extends TestCase {
	/**
	 * Explicitly registered handlers resolve by stable persisted type.
	 */
	public function test_registered_handler_resolves_by_type(): void {
		$registry = new JobHandlerRegistry();
		$handler  = $this->handler( 'index.document' );
		$this->register_or_fail( $registry, $handler );

		try {
			$resolved = $registry->for_type( 'index.document' );
		} catch ( JobQueueException $exception ) {
			self::fail( 'Registered handler resolution is not implemented: ' . $exception->getMessage() );
		}

		self::assertSame( $handler, $resolved );
	}

	/**
	 * Duplicate stable job types are rejected instead of silently replaced.
	 */
	public function test_duplicate_handler_registration_is_rejected(): void {
		$registry = new JobHandlerRegistry();
		$this->register_or_fail( $registry, $this->handler( 'index.document' ) );

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
	 * Handler types must follow the same stable grammar as queued requests.
	 */
	public function test_invalid_registered_handler_type_is_rejected(): void {
		$registry = new JobHandlerRegistry();

		$this->expectException( JobQueueException::class );
		$registry->register( $this->handler( 'Invalid Handler' ) );
	}

	/**
	 * Register setup data while converting an unimplemented stub into a clear RED assertion.
	 *
	 * @param JobHandlerRegistry $registry Registry under test.
	 * @param JobHandler         $handler Handler fixture.
	 */
	private function register_or_fail( JobHandlerRegistry $registry, JobHandler $handler ): void {
		try {
			$registry->register( $handler );
		} catch ( JobQueueException $exception ) {
			self::fail( 'Valid typed handler registration is not implemented: ' . $exception->getMessage() );
		}
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
