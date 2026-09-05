<?php
/**
 * Bounded terminal job cleanup tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobCleanup;
use WpRagAiChatbot\Jobs\JobCleanupStore;
use WpRagAiChatbot\Jobs\JobQueueException;

/**
 * Verifies cleanup remains terminal-only through a bounded store contract.
 */
final class JobCleanupTest extends TestCase {
	/**
	 * Default cleanup delegates with the hard 500-row bound.
	 */
	public function test_prune_defaults_to_five_hundred_rows(): void {
		$store   = $this->store( 37 );
		$cleanup = new JobCleanup( $store );
		$before  = new DateTimeImmutable( '2026-08-01T00:00:00+00:00' );

		$deleted = $cleanup->prune( $before );

		self::assertSame( 37, $deleted );
		self::assertSame( $before, $store->before );
		self::assertSame( 500, $store->limit );
	}

	/**
	 * Cleanup cannot exceed the repository-approved 500-row pass size.
	 */
	public function test_prune_rejects_limit_above_five_hundred(): void {
		$cleanup = new JobCleanup( $this->store() );

		$this->expectException( JobQueueException::class );
		$cleanup->prune( new DateTimeImmutable( '2026-08-01T00:00:00+00:00' ), 501 );
	}

	/**
	 * Cleanup requires at least one bounded row.
	 */
	public function test_prune_rejects_zero_limit(): void {
		$cleanup = new JobCleanup( $this->store() );

		$this->expectException( JobQueueException::class );
		$cleanup->prune( new DateTimeImmutable( '2026-08-01T00:00:00+00:00' ), 0 );
	}

	/**
	 * Build a recording cleanup store.
	 *
	 * @param int $deleted_rows Deleted row count to return.
	 * @return JobCleanupStore&object{before:?DateTimeImmutable,limit:?int}
	 */
	private function store( int $deleted_rows = 0 ): JobCleanupStore {
		return new class( $deleted_rows ) implements JobCleanupStore {
			public ?DateTimeImmutable $before = null;
			public ?int $limit = null;

			public function __construct( private readonly int $deleted_rows ) {
			}

			public function delete_terminal_before( DateTimeImmutable $before, int $limit ): int {
				$this->before = $before;
				$this->limit  = $limit;
				return $this->deleted_rows;
			}
		};
	}
}
