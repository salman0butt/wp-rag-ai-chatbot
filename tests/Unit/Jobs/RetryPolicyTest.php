<?php
/**
 * M09 deterministic retry policy behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\RetryPolicy;

/**
 * Defines deterministic bounded retry delays before production implementation.
 */
final class RetryPolicyTest extends TestCase {
	/**
	 * Retry delays double from 30 seconds and cap at 900 seconds.
	 *
	 * @param int $attempt Attempt number.
	 * @param int $expected Expected delay in seconds.
	 */
	#[DataProvider( 'delayProvider' )]
	public function test_delay_is_deterministic_and_capped( int $attempt, int $expected ): void {
		self::assertSame( $expected, RetryPolicy::delay_seconds( $attempt ) );
	}

	/**
	 * Attempt zero is outside the validated queue attempt range.
	 */
	public function test_attempt_below_one_is_rejected(): void {
		$this->expectException( JobQueueException::class );
		RetryPolicy::delay_seconds( 0 );
	}

	/**
	 * Attempts above the queue contract maximum are rejected.
	 */
	public function test_attempt_above_ten_is_rejected(): void {
		$this->expectException( JobQueueException::class );
		RetryPolicy::delay_seconds( 11 );
	}

	/**
	 * Provide valid attempts and their deterministic retry delays.
	 *
	 * @return array<string,array{int,int}>
	 */
	public static function delayProvider(): array {
		return array(
			'attempt 1'  => array( 1, 30 ),
			'attempt 2'  => array( 2, 60 ),
			'attempt 3'  => array( 3, 120 ),
			'attempt 4'  => array( 4, 240 ),
			'attempt 5'  => array( 5, 480 ),
			'attempt 6'  => array( 6, 900 ),
			'attempt 10' => array( 10, 900 ),
		);
	}
}
