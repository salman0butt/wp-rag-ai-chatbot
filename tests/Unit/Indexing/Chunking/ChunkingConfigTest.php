<?php
/**
 * Chunking configuration contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Indexing\Chunking;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Indexing\Chunking\ChunkingConfig;

/**
 * Verifies immutable validated chunk-budget configuration.
 */
final class ChunkingConfigTest extends TestCase {
	/**
	 * Default and explicit valid configuration produce deterministic fingerprints.
	 */
	public function test_valid_configuration_has_deterministic_fingerprint(): void {
		$this->requireConfig();

		$first  = new ChunkingConfig();
		$second = new ChunkingConfig();
		$custom = new ChunkingConfig( 256, 32, 'm07-v2', 'embed-v1' );

		self::assertSame( $first->fingerprint(), $second->fingerprint() );
		self::assertSame( 64, strlen( $first->fingerprint() ) );
		self::assertNotSame( $first->fingerprint(), $custom->fingerprint() );
	}

	/**
	 * Fingerprints include all chunking behavior fields and remain independent of object identity.
	 */
	public function test_fingerprint_changes_when_chunking_behavior_changes(): void {
		$this->requireConfig();

		$baseline = new ChunkingConfig( 512, 64, 'm07-v1', null );

		self::assertNotSame( $baseline->fingerprint(), ( new ChunkingConfig( 256, 64, 'm07-v1', null ) )->fingerprint() );
		self::assertNotSame( $baseline->fingerprint(), ( new ChunkingConfig( 512, 32, 'm07-v1', null ) )->fingerprint() );
		self::assertNotSame( $baseline->fingerprint(), ( new ChunkingConfig( 512, 64, 'm07-v2', null ) )->fingerprint() );
		self::assertNotSame( $baseline->fingerprint(), ( new ChunkingConfig( 512, 64, 'm07-v1', 'embed-v1' ) )->fingerprint() );
	}

	/**
	 * Maximum token budget must stay within the approved 32..4096 range.
	 */
	public function test_rejects_max_token_budget_outside_bounds(): void {
		$this->requireConfig();

		$this->assertInvalidConfig( static fn (): ChunkingConfig => new ChunkingConfig( 31 ) );
		$this->assertInvalidConfig( static fn (): ChunkingConfig => new ChunkingConfig( 4097 ) );
	}

	/**
	 * Overlap must be non-negative and no more than 25 percent of max tokens.
	 */
	public function test_rejects_invalid_overlap_budget(): void {
		$this->requireConfig();

		$this->assertInvalidConfig( static fn (): ChunkingConfig => new ChunkingConfig( 512, -1 ) );
		$this->assertInvalidConfig( static fn (): ChunkingConfig => new ChunkingConfig( 512, 129 ) );
		self::assertInstanceOf( ChunkingConfig::class, new ChunkingConfig( 512, 128 ) );
	}

	/**
	 * Version and optional compatibility key must be non-empty after trimming.
	 */
	public function test_rejects_blank_version_and_blank_compatibility_key(): void {
		$this->requireConfig();

		$this->assertInvalidConfig( static fn (): ChunkingConfig => new ChunkingConfig( 512, 64, '' ) );
		$this->assertInvalidConfig( static fn (): ChunkingConfig => new ChunkingConfig( 512, 64, '   ' ) );
		$this->assertInvalidConfig( static fn (): ChunkingConfig => new ChunkingConfig( 512, 64, 'm07-v1', '' ) );
		$this->assertInvalidConfig( static fn (): ChunkingConfig => new ChunkingConfig( 512, 64, 'm07-v1', '   ' ) );
	}

	/**
	 * Run a constructor expected to fail validation.
	 *
	 * @param callable(): ChunkingConfig $factory Invalid config factory.
	 */
	private function assertInvalidConfig( callable $factory ): void {
		try {
			$factory();
			self::fail( 'Expected InvalidArgumentException.' );
		} catch ( InvalidArgumentException $exception ) {
			self::assertNotSame( '', $exception->getMessage() );
		}
	}

	/**
	 * Preserve assertion-style RED while the production config does not yet exist.
	 */
	private function requireConfig(): void {
		if ( ! class_exists( ChunkingConfig::class ) ) {
			self::fail( 'ChunkingConfig class does not exist yet.' );
		}
	}
}
