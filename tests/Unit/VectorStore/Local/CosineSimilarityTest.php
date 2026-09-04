<?php
/**
 * Cosine-similarity tests for the bounded local vector store.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore\Local;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic and fail-closed cosine scoring.
 */
final class CosineSimilarityTest extends TestCase {
	/**
	 * Identical and orthogonal vectors have deterministic scores.
	 */
	public function test_scores_identical_and_orthogonal_vectors(): void {
		$class = 'WpRagAiChatbot\\VectorStore\\Local\\CosineSimilarity';
		if ( ! class_exists( $class ) ) {
			self::fail( 'CosineSimilarity must exist before Task 4 scoring behavior can pass.' );
		}

		self::assertEqualsWithDelta( 1.0, (float) call_user_func( array( $class, 'score' ), array( 1.0, 0.0 ), array( 1.0, 0.0 ) ), 0.0000001 );
		self::assertEqualsWithDelta( 0.0, (float) call_user_func( array( $class, 'score' ), array( 1.0, 0.0 ), array( 0.0, 1.0 ) ), 0.0000001 );
	}

	/**
	 * Invalid vectors fail closed rather than producing ambiguous scores.
	 */
	public function test_rejects_dimension_non_finite_and_zero_norm_inputs(): void {
		$class = 'WpRagAiChatbot\\VectorStore\\Local\\CosineSimilarity';
		if ( ! class_exists( $class ) ) {
			self::fail( 'CosineSimilarity must exist before Task 4 scoring behavior can pass.' );
		}

		$cases = array(
			array( array( 1.0 ), array( 1.0, 0.0 ) ),
			array( array( INF, 0.0 ), array( 1.0, 0.0 ) ),
			array( array( 0.0, 0.0 ), array( 1.0, 0.0 ) ),
		);

		foreach ( $cases as $case ) {
			try {
				call_user_func( array( $class, 'score' ), $case[0], $case[1] );
				self::fail( 'Expected invalid cosine input to be rejected.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}
}
