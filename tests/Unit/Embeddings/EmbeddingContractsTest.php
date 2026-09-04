<?php
/**
 * Embedding contract and compatibility tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Embeddings;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\EmbeddingUsage;
use WpRagAiChatbot\Providers\EmbeddingVector;

/**
 * Verifies M08 immutable embedding and compatibility boundaries.
 */
final class EmbeddingContractsTest extends TestCase {
	/**
	 * Equivalent profiles remain stable while incompatible dimensions change identity.
	 */
	public function test_vector_index_fingerprint_is_deterministic_and_dimension_sensitive(): void {
		$first = new VectorIndexProfile(
			new EmbeddingProfile( 'openai-direct', 'text-embedding-model', 1536, NormalizationMode::NONE ),
			DistanceMetric::COSINE
		);
		$same  = new VectorIndexProfile(
			new EmbeddingProfile( 'openai-direct', 'text-embedding-model', 1536, NormalizationMode::NONE ),
			DistanceMetric::COSINE
		);
		$other = new VectorIndexProfile(
			new EmbeddingProfile( 'openai-direct', 'text-embedding-model', 3072, NormalizationMode::NONE ),
			DistanceMetric::COSINE
		);

		self::assertSame( $first->fingerprint(), $same->fingerprint() );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $first->fingerprint() );
		self::assertNotSame( $first->fingerprint(), $other->fingerprint() );
	}

	/**
	 * Provider/model/normalization/distance are compatibility inputs too.
	 */
	public function test_vector_index_fingerprint_changes_for_each_incompatible_field(): void {
		$base = new VectorIndexProfile(
			new EmbeddingProfile( 'openai-direct', 'model-a', 3, NormalizationMode::NONE ),
			DistanceMetric::COSINE
		);

		$variants = array(
			new VectorIndexProfile( new EmbeddingProfile( 'openrouter-direct', 'model-a', 3, NormalizationMode::NONE ), DistanceMetric::COSINE ),
			new VectorIndexProfile( new EmbeddingProfile( 'openai-direct', 'model-b', 3, NormalizationMode::NONE ), DistanceMetric::COSINE ),
			new VectorIndexProfile( new EmbeddingProfile( 'openai-direct', 'model-a', 3, NormalizationMode::L2 ), DistanceMetric::COSINE ),
			new VectorIndexProfile( new EmbeddingProfile( 'openai-direct', 'model-a', 3, NormalizationMode::NONE ), DistanceMetric::DOT_PRODUCT ),
		);

		foreach ( $variants as $variant ) {
			self::assertNotSame( $base->fingerprint(), $variant->fingerprint() );
		}
	}

	/**
	 * Delimiter control characters cannot create an ambiguous compatibility fingerprint.
	 */
	public function test_embedding_profile_rejects_control_characters_in_fingerprint_identifiers(): void {
		$this->expectException( InvalidArgumentException::class );

		new EmbeddingProfile( "openai-direct\nmodel=shadow", 'model-a', 3, NormalizationMode::NONE );
	}

	/**
	 * Empty and non-finite vectors cannot enter storage/search paths.
	 */
	public function test_embedding_vector_rejects_empty_and_non_finite_values(): void {
		foreach ( array( array(), array( 0.1, INF ), array( NAN, 0.2 ) ) as $values ) {
			try {
				new EmbeddingVector( 0, $values );
				self::fail( 'Expected invalid embedding vector to be rejected.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/**
	 * Dense vector values must remain an ordered PHP list.
	 */
	public function test_embedding_vector_rejects_associative_values(): void {
		$this->expectException( InvalidArgumentException::class );

		new EmbeddingVector( 0, array( 'first' => 0.1, 'second' => 0.2 ) );
	}

	/**
	 * Request inputs preserve caller order and validate required fields.
	 */
	public function test_embedding_request_preserves_order_and_validates_dimensions(): void {
		$request = new EmbeddingRequest( 'model-a', array( 'first', 'second' ), 3 );

		self::assertSame( 'model-a', $request->model );
		self::assertSame( array( 'first', 'second' ), $request->inputs );
		self::assertSame( 3, $request->dimensions );

		$this->expectException( InvalidArgumentException::class );
		new EmbeddingRequest( 'model-a', array( 'text' ), 0 );
	}

	/**
	 * Ordered text inputs must remain a PHP list for deterministic JSON transport.
	 */
	public function test_embedding_request_rejects_associative_inputs(): void {
		$this->expectException( InvalidArgumentException::class );

		new EmbeddingRequest( 'model-a', array( 'first' => 'one', 'second' => 'two' ) );
	}

	/**
	 * Provider vectors must remain an ordered PHP list before service validation.
	 */
	public function test_embedding_result_rejects_associative_vectors(): void {
		$this->expectException( InvalidArgumentException::class );

		new EmbeddingResult(
			'provider-a',
			'model-a',
			array( 'first' => new EmbeddingVector( 0, array( 0.1, 0.2 ) ) ),
			EmbeddingUsage::unknown()
		);
	}

	/**
	 * Unknown provider usage is not fabricated as zero usage.
	 */
	public function test_embedding_usage_distinguishes_unknown_from_zero(): void {
		$unknown = EmbeddingUsage::unknown();
		$zero    = EmbeddingUsage::input_tokens( 0 );

		self::assertFalse( $unknown->known );
		self::assertNull( $unknown->input_tokens );
		self::assertTrue( $zero->known );
		self::assertSame( 0, $zero->input_tokens );
	}

	/**
	 * Blank compatibility identifiers are invalid.
	 */
	public function test_embedding_profile_rejects_blank_provider_id(): void {
		$this->expectException( InvalidArgumentException::class );

		new EmbeddingProfile( ' ', 'model-a', 3, NormalizationMode::NONE );
	}
}
