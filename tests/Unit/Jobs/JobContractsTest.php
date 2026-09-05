<?php
/**
 * M09 immutable job contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;

/**
 * Verifies bounded queue requests and stable persisted states.
 */
final class JobContractsTest extends TestCase {
	/**
	 * The persisted queue state machine has explicit stable values.
	 */
	public function test_job_status_values_are_stable(): void {
		self::assertSame( 'queued', JobStatus::QUEUED->value );
		self::assertSame( 'running', JobStatus::RUNNING->value );
		self::assertSame( 'retry_wait', JobStatus::RETRY_WAIT->value );
		self::assertSame( 'succeeded', JobStatus::SUCCEEDED->value );
		self::assertSame( 'failed', JobStatus::FAILED->value );
		self::assertSame( 'cancelled', JobStatus::CANCELLED->value );
	}

	/**
	 * Valid queue requests preserve their bounded application identity.
	 */
	public function test_job_request_preserves_valid_fields(): void {
		$request = new JobRequest(
			'index.document',
			array( 'document_id' => 42 ),
			'document:42:v7',
			3
		);

		self::assertSame( 'index.document', $request->type );
		self::assertSame( array( 'document_id' => 42 ), $request->payload );
		self::assertSame( 'document:42:v7', $request->idempotency_key );
		self::assertSame( 3, $request->max_attempts );
	}

	/**
	 * Oversized payloads never cross the queue persistence boundary.
	 */
	public function test_job_request_rejects_payload_larger_than_64_kib(): void {
		$this->expectException( JobQueueException::class );

		new JobRequest(
			'index.document',
			array( 'text' => str_repeat( 'x', 65537 ) )
		);
	}

	/**
	 * Deeply nested untrusted payloads are rejected before persistence.
	 */
	public function test_job_request_rejects_payload_deeper_than_eight_levels(): void {
		$this->expectException( JobQueueException::class );

		new JobRequest(
			'index.document',
			array(
				'a' => array(
					'b' => array(
						'c' => array(
							'd' => array(
								'e' => array(
									'f' => array(
										'g' => array(
											'h' => array(
												'i' => true,
											),
										),
									),
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * The persisted payload contract requires an object at the root.
	 */
	public function test_job_request_rejects_non_empty_list_root_payload(): void {
		$this->expectException( JobQueueException::class );

		new JobRequest( 'index.document', array( 42, 43 ) );
	}

	/**
	 * Executable PHP objects cannot be smuggled through JSON encoding.
	 */
	public function test_job_request_rejects_object_payload_values(): void {
		$this->expectException( JobQueueException::class );

		new JobRequest(
			'index.document',
			array(
				'callback' => static fn (): string => 'should-never-run-or-persist',
			)
		);
	}

	/**
	 * Retry attempts are explicitly bounded to protect worker execution.
	 */
	public function test_job_request_rejects_more_than_ten_attempts(): void {
		$this->expectException( JobQueueException::class );

		new JobRequest( 'index.document', array( 'document_id' => 42 ), null, 11 );
	}

	/**
	 * Job types cannot contain arbitrary executable/class-like data.
	 */
	public function test_job_request_rejects_invalid_job_type(): void {
		$this->expectException( JobQueueException::class );

		new JobRequest( 'Some\\Arbitrary\\Callable::run', array() );
	}
}
