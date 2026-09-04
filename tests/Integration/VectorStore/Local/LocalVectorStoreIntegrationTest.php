<?php
/**
 * Local WordPress vector-store integration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration\VectorStore\Local;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Tests\Support\VectorStore\ScriptedLocalVectorConnection;
use WpRagAiChatbot\VectorStore\Filter\EqualsFilter;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorRecord;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorStoreErrorCode;
use WpRagAiChatbot\VectorStore\VectorStoreException;

/**
 * Verifies bounded database-backed local vector-store behavior.
 */
final class LocalVectorStoreIntegrationTest extends TestCase {
	/**
	 * Search scopes collection/profile/filter before bounded PHP scoring and sorts deterministically.
	 */
	public function test_search_is_bounded_filtered_and_deterministic(): void {
		$connection  = new ScriptedLocalVectorConnection();
		$collection  = $this->collection( 'docs' );
		$fingerprint = $collection->profile->fingerprint();

		$connection->row_results[] = array(
			'fingerprint' => $fingerprint,
			'dimensions'  => 2,
		);
		$connection->result_sets[] = array(
			array(
				'vector_key'    => 'b',
				'vector_json'   => '[1,0]',
				'metadata_json' => '{"language":"en"}',
			),
			array(
				'vector_key'    => 'a',
				'vector_json'   => '[1,0]',
				'metadata_json' => '{"language":"en"}',
			),
			array(
				'vector_key'    => 'c',
				'vector_json'   => '[0,1]',
				'metadata_json' => '{"language":"en"}',
			),
		);

		$store  = $this->store( $connection, 3, 2 );
		$result = $store->search(
			new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 2, $fingerprint, new EqualsFilter( 'language', 'en' ) )
		);

		self::assertSame( array( 'a', 'b' ), array_map( static fn ( $vector_match ): string => $vector_match->id, $result->matches ) );
		$search_call = $connection->prepared_calls[ count( $connection->prepared_calls ) - 1 ];
		self::assertStringContainsString( 'collection_key = %s', $search_call['query'] );
		self::assertStringContainsString( 'fingerprint = %s', $search_call['query'] );
		self::assertStringContainsString( 'JSON_EXTRACT', $search_call['query'] );
		self::assertStringContainsString( 'LIMIT %d', $search_call['query'] );
		self::assertContains( 'docs', $search_call['args'] );
		self::assertContains( $fingerprint, $search_call['args'] );
		self::assertContains( 4, $search_call['args'] );
	}

	/**
	 * Candidate overflow fails explicitly instead of expanding a PHP scan.
	 */
	public function test_search_fails_closed_when_candidate_ceiling_is_exceeded(): void {
		$connection  = new ScriptedLocalVectorConnection();
		$collection  = $this->collection( 'docs' );
		$fingerprint = $collection->profile->fingerprint();

		$connection->row_results[] = array(
			'fingerprint' => $fingerprint,
			'dimensions'  => 2,
		);
		$connection->result_sets[] = array(
			array(
				'vector_key'    => 'a',
				'vector_json'   => '[1,0]',
				'metadata_json' => '{}',
			),
			array(
				'vector_key'    => 'b',
				'vector_json'   => '[1,0]',
				'metadata_json' => '{}',
			),
			array(
				'vector_key'    => 'c',
				'vector_json'   => '[1,0]',
				'metadata_json' => '{}',
			),
		);

		try {
			$this->store( $connection, 2, 2 )->search( new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 2, $fingerprint ) );
			self::fail( 'Expected the local candidate ceiling to fail closed.' );
		} catch ( VectorStoreException $exception ) {
			self::assertSame( VectorStoreErrorCode::OPERATION_FAILED, $exception->error_code );
		}
	}

	/**
	 * Stable-ID replacement and delete stay collection-scoped and delete is idempotent.
	 */
	public function test_upsert_replaces_stable_id_and_delete_is_collection_scoped(): void {
		$connection  = new ScriptedLocalVectorConnection();
		$collection  = $this->collection( 'docs' );
		$fingerprint = $collection->profile->fingerprint();

		$connection->row_results[] = array(
			'fingerprint' => $fingerprint,
			'dimensions'  => 2,
		);

		$connection->row_results[] = array(
			'vector_json'   => '[0,1]',
			'metadata_json' => '{"language":"en"}',
			'fingerprint'   => $fingerprint,
		);

		$connection->delete_results = array( 1, 0 );

		$store = $this->store( $connection, 10, 5 );
		$write = $store->upsert( new VectorRecord( $collection, 'chunk-1', array( 1.0, 0.0 ), $fingerprint, array( 'language' => 'en' ) ) );
		self::assertTrue( $write->changed );
		self::assertCount( 1, $connection->updates );
		self::assertSame(
			array(
				'collection_key' => 'docs',
				'vector_key'     => 'chunk-1',
			),
			$connection->updates[0]['where']
		);

		self::assertTrue( $store->delete( $collection, 'chunk-1' )->changed );
		self::assertFalse( $store->delete( $collection, 'chunk-1' )->changed );
		self::assertSame(
			array(
				'collection_key' => 'docs',
				'vector_key'     => 'chunk-1',
			),
			$connection->deletes[0]['where']
		);
	}

	/**
	 * Delete cannot cross a persisted collection compatibility boundary.
	 */
	public function test_delete_rejects_incompatible_persisted_collection_profile(): void {
		$connection = new ScriptedLocalVectorConnection();
		$collection = $this->collection( 'docs' );

		$connection->row_results[] = array(
			'fingerprint' => str_repeat( '0', 64 ),
			'dimensions'  => 2,
		);

		try {
			$this->store( $connection, 10, 5 )->delete( $collection, 'chunk-1' );
			self::fail( 'Expected incompatible persisted collection profile to block delete.' );
		} catch ( VectorStoreException $exception ) {
			self::assertSame( VectorStoreErrorCode::INCOMPATIBLE_PROFILE, $exception->error_code );
			self::assertCount( 0, $connection->deletes );
		}
	}

	/**
	 * Persisted collection identity cannot silently change compatibility profile.
	 */
	public function test_incompatible_persisted_collection_fails_before_operation(): void {
		$connection = new ScriptedLocalVectorConnection();
		$collection = $this->collection( 'docs' );

		$connection->row_results[] = array(
			'fingerprint' => str_repeat( '0', 64 ),
			'dimensions'  => 2,
		);

		try {
			$this->store( $connection, 10, 5 )->search(
				new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 2, $collection->profile->fingerprint() )
			);
			self::fail( 'Expected incompatible persisted collection profile to be rejected.' );
		} catch ( VectorStoreException $exception ) {
			self::assertSame( VectorStoreErrorCode::INCOMPATIBLE_PROFILE, $exception->error_code );
		}
	}

	/**
	 * Build the production adapter dynamically so absent behavior reaches behavioral RED.
	 *
	 * @param ScriptedLocalVectorConnection $connection Test connection.
	 * @param int                           $candidate_limit Candidate ceiling.
	 * @param int                           $max_top_k Maximum top-K.
	 */
	private function store( ScriptedLocalVectorConnection $connection, int $candidate_limit, int $max_top_k ): object {
		$class = 'WpRagAiChatbot\\VectorStore\\Local\\LocalVectorStore';
		if ( ! class_exists( $class ) ) {
			self::fail( 'LocalVectorStore must exist before Task 4 adapter behavior can pass.' );
		}
		$config_class = 'WpRagAiChatbot\\VectorStore\\Local\\LocalVectorStoreConfig';
		return new $class( $connection, new TableNames( 'wp_' ), new $config_class( $candidate_limit, $max_top_k ) );
	}

	/**
	 * Create a compatible two-dimensional collection.
	 *
	 * @param string $id Collection ID.
	 */
	private function collection( string $id ): VectorCollection {
		return new VectorCollection(
			$id,
			new VectorIndexProfile(
				new EmbeddingProfile( 'test', 'model', 2, NormalizationMode::NONE ),
				DistanceMetric::COSINE
			)
		);
	}
}
