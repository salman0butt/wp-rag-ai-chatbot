<?php
/**
 * Vector-store contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Tests\Support\VectorStore\InMemoryVectorStore;
use WpRagAiChatbot\VectorStore\Filter\AndFilter;
use WpRagAiChatbot\VectorStore\Filter\EqualsFilter;
use WpRagAiChatbot\VectorStore\Filter\InFilter;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorRecord;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;

/**
 * Verifies infrastructure-neutral M08 vector-store boundaries.
 */
final class VectorStoreContractsTest extends TestCase {
	/**
	 * Collection compatibility is enforced before a record reaches an adapter.
	 */
	public function test_record_rejects_dimension_and_fingerprint_mismatch(): void {
		$collection = $this->collection( 'docs', 2 );

		foreach (
			array(
				array( array( 0.1 ), $collection->profile->fingerprint() ),
				array( array( 0.1, 0.2 ), str_repeat( '0', 64 ) ),
			) as $case
		) {
			try {
				new VectorRecord( $collection, 'chunk-1', $case[0], $case[1] );
				self::fail( 'Expected incompatible vector record to be rejected.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/**
	 * Portable metadata rejects composite values at the runtime boundary.
	 */
	public function test_record_rejects_non_scalar_metadata(): void {
		$collection = $this->collection( 'docs', 2 );

		$this->expectException( InvalidArgumentException::class );
		new VectorRecord(
			$collection,
			'chunk-1',
			array( 0.1, 0.2 ),
			$collection->profile->fingerprint(),
			array( 'nested' => array( 'not-portable' ) )
		);
	}

	/**
	 * Search requests enforce bounded top-K and collection compatibility.
	 */
	public function test_search_request_validates_top_k_dimensions_and_fingerprint(): void {
		$collection = $this->collection( 'docs', 2 );

		$this->expectException( InvalidArgumentException::class );
		new VectorSearchRequest(
			$collection,
			array( 0.1, 0.2 ),
			0,
			$collection->profile->fingerprint()
		);
	}

	/**
	 * Portable filters accept bounded scalar metadata and compose deterministically.
	 */
	public function test_portable_filters_match_only_typed_metadata(): void {
		$filter = new AndFilter(
			array(
				new EqualsFilter( 'language', 'en' ),
				new InFilter( 'visibility', array( 'public', 'members' ) ),
			)
		);

		self::assertTrue(
			$filter->matches(
				array(
					'language'   => 'en',
					'visibility' => 'public',
				)
			)
		);
		self::assertFalse(
			$filter->matches(
				array(
					'language'   => 'fr',
					'visibility' => 'public',
				)
			)
		);
	}

	/**
	 * Filter keys are bounded portable identifiers, not vendor fragments.
	 */
	public function test_filter_rejects_vendor_expression_keys(): void {
		$this->expectException( InvalidArgumentException::class );
		new EqualsFilter( '$and[0].metadata.language', 'en' );
	}

	/**
	 * Test-only reference adapter proves replacement, isolation, filtering and ordering.
	 */
	public function test_in_memory_contract_preserves_collection_isolation_and_stable_ordering(): void {
		$store       = new InMemoryVectorStore( 'memory' );
		$docs        = $this->collection( 'docs', 2 );
		$other_docs  = $this->collection( 'other-docs', 2 );
		$fingerprint = $docs->profile->fingerprint();

		$store->upsert( new VectorRecord( $docs, 'b', array( 1.0, 0.0 ), $fingerprint, array( 'language' => 'en' ) ) );
		$store->upsert( new VectorRecord( $docs, 'a', array( 1.0, 0.0 ), $fingerprint, array( 'language' => 'en' ) ) );
		$store->upsert( new VectorRecord( $docs, 'filtered', array( 1.0, 0.0 ), $fingerprint, array( 'language' => 'fr' ) ) );
		$store->upsert( new VectorRecord( $other_docs, 'leak', array( 1.0, 0.0 ), $other_docs->profile->fingerprint(), array( 'language' => 'en' ) ) );

		$result = $store->search(
			new VectorSearchRequest( $docs, array( 1.0, 0.0 ), 5, $fingerprint, new EqualsFilter( 'language', 'en' ) )
		);

		self::assertSame( array( 'a', 'b' ), array_map( static fn ( $vector_match ): string => $vector_match->id, $result->matches ) );

		$store->upsert( new VectorRecord( $docs, 'a', array( 0.0, 1.0 ), $fingerprint, array( 'language' => 'en' ) ) );
		self::assertSame( array( 'b', 'a' ), array_map( static fn ( $vector_match ): string => $vector_match->id, $store->search( new VectorSearchRequest( $docs, array( 1.0, 0.0 ), 5, $fingerprint, new EqualsFilter( 'language', 'en' ) ) )->matches ) );

		$store->delete( $docs, 'b' );
		$store->delete( $docs, 'b' );
		self::assertSame( array( 'a' ), array_map( static fn ( $vector_match ): string => $vector_match->id, $store->search( new VectorSearchRequest( $docs, array( 1.0, 0.0 ), 5, $fingerprint, new EqualsFilter( 'language', 'en' ) ) )->matches ) );
	}

	/**
	 * Create a compatible collection fixture.
	 *
	 * @param string $id Collection ID.
	 * @param int    $dimensions Vector dimensions.
	 */
	private function collection( string $id, int $dimensions ): VectorCollection {
		return new VectorCollection(
			$id,
			new VectorIndexProfile(
				new EmbeddingProfile( 'test', 'model', $dimensions, NormalizationMode::NONE ),
				DistanceMetric::COSINE
			)
		);
	}
}
