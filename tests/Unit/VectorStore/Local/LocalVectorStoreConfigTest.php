<?php
/**
 * Local vector-store configuration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore\Local;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies hard local vector-search bounds.
 */
final class LocalVectorStoreConfigTest extends TestCase {
	/**
	 * Local search bounds must be positive and top-K cannot exceed candidates.
	 */
	public function test_config_rejects_invalid_bounds(): void {
		$class = 'WpRagAiChatbot\\VectorStore\\Local\\LocalVectorStoreConfig';
		if ( ! class_exists( $class ) ) {
			self::fail( 'LocalVectorStoreConfig must exist before Task 4 configuration behavior can pass.' );
		}

		foreach ( array( array( 0, 10 ), array( 100, 0 ), array( 10, 11 ) ) as $args ) {
			try {
				new $class( ...$args );
				self::fail( 'Expected invalid local vector-store bounds to be rejected.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/**
	 * Valid bounds create a usable immutable configuration.
	 */
	public function test_config_accepts_bounded_candidate_and_top_k_limits(): void {
		$class = 'WpRagAiChatbot\\VectorStore\\Local\\LocalVectorStoreConfig';
		if ( ! class_exists( $class ) ) {
			self::fail( 'LocalVectorStoreConfig must exist before Task 4 configuration behavior can pass.' );
		}

		$config = new $class( 250, 25 );
		self::assertSame( 250, $config->candidate_limit );
		self::assertSame( 25, $config->max_top_k );
	}
}
