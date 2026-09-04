<?php
/**
 * Embedding service batching and validation tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Embeddings;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Embeddings\EmbeddingBatchConfig;
use WpRagAiChatbot\Embeddings\EmbeddingService;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\EmbeddingUsage;
use WpRagAiChatbot\Providers\EmbeddingVector;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;

/**
 * Verifies deterministic bounded batching and fail-closed response validation.
 */
final class EmbeddingServiceTest extends TestCase {
	/**
	 * Five inputs with batch size two are sent as 2/2/1 and restored to global order.
	 */
	public function test_embed_batches_deterministically_and_restores_global_order(): void {
		$provider = new RecordingEmbeddingProvider(
			array(
				$this->result( array( array( 0.1, 0.2 ), array( 0.3, 0.4 ) ), 4 ),
				$this->result( array( array( 0.5, 0.6 ), array( 0.7, 0.8 ) ), 5 ),
				$this->result( array( array( 0.9, 1.0 ) ), 2 ),
			)
		);
		$service  = new EmbeddingService( $provider, new EmbeddingBatchConfig( 2 ) );
		$result   = $service->embed( new EmbeddingRequest( 'embed-model', array( 'a', 'b', 'c', 'd', 'e' ), 2 ) );

		self::assertSame(
			array(
				array( 'a', 'b' ),
				array( 'c', 'd' ),
				array( 'e' ),
			),
			array_map( static fn ( EmbeddingRequest $request ): array => $request->inputs, $provider->requests )
		);
		self::assertSame( array( 0, 1, 2, 3, 4 ), array_map( static fn ( EmbeddingVector $vector ): int => $vector->index, $result->vectors ) );
		self::assertSame( 11, $result->usage->input_tokens );
		self::assertTrue( $result->usage->known );
	}

	/**
	 * Any unknown batch usage keeps aggregate usage unknown instead of fabricating a total.
	 */
	public function test_embed_preserves_unknown_usage_when_any_batch_is_unknown(): void {
		$provider = new RecordingEmbeddingProvider(
			array(
				$this->result( array( array( 0.1 ), array( 0.2 ) ), 3 ),
				$this->result( array( array( 0.3 ) ), null ),
			)
		);
		$result   = ( new EmbeddingService( $provider, new EmbeddingBatchConfig( 2 ) ) )
			->embed( new EmbeddingRequest( 'embed-model', array( 'a', 'b', 'c' ), 1 ) );

		self::assertFalse( $result->usage->known );
		self::assertNull( $result->usage->input_tokens );
	}

	/**
	 * Malformed batch indices fail the whole operation closed.
	 */
	public function test_embed_rejects_missing_duplicate_and_out_of_range_batch_indices(): void {
		$cases = array(
			array( new EmbeddingVector( 0, array( 0.1 ) ) ),
			array( new EmbeddingVector( 0, array( 0.1 ) ), new EmbeddingVector( 0, array( 0.2 ) ) ),
			array( new EmbeddingVector( 0, array( 0.1 ) ), new EmbeddingVector( 2, array( 0.2 ) ) ),
		);

		foreach ( $cases as $vectors ) {
			$provider = new RecordingEmbeddingProvider(
				array( new EmbeddingResult( 'test-embedding', 'embed-model', $vectors, EmbeddingUsage::unknown() ) )
			);
			try {
				( new EmbeddingService( $provider, new EmbeddingBatchConfig( 2 ) ) )
					->embed( new EmbeddingRequest( 'embed-model', array( 'a', 'b' ), 1 ) );
				self::fail( 'Expected malformed embedding response.' );
			} catch ( ProviderException $exception ) {
				self::assertSame( ProviderErrorCode::MALFORMED_RESPONSE, $exception->error_code );
			}
		}
	}

	/**
	 * Vector dimensions must be consistent across all batches and requested dimensions.
	 */
	public function test_embed_rejects_dimension_inconsistency(): void {
		$provider = new RecordingEmbeddingProvider(
			array(
				$this->result( array( array( 0.1, 0.2 ), array( 0.3, 0.4 ) ), 3 ),
				$this->result( array( array( 0.5, 0.6, 0.7 ) ), 2 ),
			)
		);

		$this->expectException( ProviderException::class );
		( new EmbeddingService( $provider, new EmbeddingBatchConfig( 2 ) ) )
			->embed( new EmbeddingRequest( 'embed-model', array( 'a', 'b', 'c' ), 2 ) );
	}

	/**
	 * Batch configuration rejects non-positive and unreasonably large values.
	 */
	public function test_batch_config_enforces_positive_bounded_limit(): void {
		foreach ( array( 0, -1, 10001 ) as $invalid ) {
			try {
				new EmbeddingBatchConfig( $invalid );
				self::fail( 'Expected invalid embedding batch configuration.' );
			} catch ( \InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/**
	 * Build one deterministic normalized embedding result.
	 *
	 * @param array<int, array<int, float>> $values Ordered vector values.
	 * @param int|null                      $usage Known tokens or null for unknown.
	 */
	private function result( array $values, ?int $usage ): EmbeddingResult {
		$vectors = array();
		foreach ( $values as $index => $vector ) {
			$vectors[] = new EmbeddingVector( $index, $vector );
		}

		return new EmbeddingResult(
			'test-embedding',
			'embed-model',
			$vectors,
			null === $usage ? EmbeddingUsage::unknown() : EmbeddingUsage::input_tokens( $usage )
		);
	}
}

/**
 * Deterministic fake embedding provider for service tests.
 */
final class RecordingEmbeddingProvider implements EmbeddingProvider {
	/** @var EmbeddingRequest[] */
	public array $requests = array();

	/** @var EmbeddingResult[] */
	private array $results;

	/**
	 * @param EmbeddingResult[] $results Queued results.
	 */
	public function __construct( array $results ) {
		$this->results = $results;
	}

	public function provider_id(): string {
		return 'test-embedding';
	}

	public function available(): bool {
		return true;
	}

	public function embed( EmbeddingRequest $request ): EmbeddingResult {
		$this->requests[] = $request;
		$result           = array_shift( $this->results );
		if ( ! $result instanceof EmbeddingResult ) {
			throw new \RuntimeException( 'No queued embedding result.' );
		}

		return $result;
	}
}
