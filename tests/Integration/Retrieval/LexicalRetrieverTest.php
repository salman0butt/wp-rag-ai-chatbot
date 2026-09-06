<?php
/**
 * M10 lexical retriever integration contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration\Retrieval;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchStore;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalScorer;
use WpRagAiChatbot\Retrieval\Lexical\LexicalSearchMatch;
use WpRagAiChatbot\Retrieval\Lexical\LexicalSearchRequest;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Proves lexical retrieval preserves trusted scope and hard result bounds.
 */
final class LexicalRetrieverTest extends TestCase {
	/**
	 * The retriever forwards trusted scope, never scores beyond the configured SQL candidate limit,
	 * and returns deterministic score-desc/chunk-ID ordering.
	 */
	public function test_retrieve_is_bounded_and_deterministically_ranked(): void {
		$store     = new class() implements ChunkSearchStore {
			/**
			 * Captured bounded request.
			 *
			 * @var LexicalSearchRequest|null
			 */
			public ?LexicalSearchRequest $request = null;

			/**
			 * Ignore fixture replacement.
			 *
			 * @param string            $collection_id Collection scope.
			 * @param string            $document_key Document scope.
			 * @param ChunkSearchRecord ...$chunks Replacement chunks.
			 */
			public function replace_document_chunks( string $collection_id, string $document_key, ChunkSearchRecord ...$chunks ): void {
			}

			/**
			 * Ignore fixture deletion.
			 *
			 * @param string $collection_id Collection scope.
			 * @param string $document_key Document scope.
			 */
			public function delete_document( string $collection_id, string $document_key ): void {
			}

			/**
			 * Return a bounded lexical fixture set.
			 *
			 * @param LexicalSearchRequest $request Captured search request.
			 * @return LexicalSearchMatch[]
			 */
			public function search( LexicalSearchRequest $request ): array {
				$this->request = $request;

				return array(
					new LexicalSearchMatch( LexicalRetrieverTest::record( 'b', 'SKU-42/A guide' ) ),
					new LexicalSearchMatch( LexicalRetrieverTest::record( 'a', 'SKU-42/A guide' ) ),
					new LexicalSearchMatch( LexicalRetrieverTest::record( 'c', 'generic guide text' ) ),
				);
			}
		};
		$config    = new RetrievalConfig( lexical_candidate_limit: 3, fused_candidate_limit: 2 );
		$filter    = new LexicalFilter( 'knowledge', null, 7, 'en', 'public' );
		$query     = new RetrievalQuery( 'sku-42/a guide', array( 'sku-42/a', 'guide' ) );
		$retriever = new LexicalRetriever( $store, new LexicalScorer(), $config );

		$results = $retriever->retrieve( $query, $filter );

		self::assertNotNull( $store->request );
		self::assertSame( 3, $store->request->limit );
		self::assertSame( $filter, $store->request->filter );
		self::assertCount( 2, $results );
		self::assertSame( hash( 'sha256', 'b' ), $results[0]->chunk_id );
		self::assertSame( hash( 'sha256', 'a' ), $results[1]->chunk_id );
		self::assertGreaterThanOrEqual( $results[1]->native_score, $results[0]->native_score );
	}

	/**
	 * A defensive retriever never admits out-of-scope rows and never consumes rows beyond its hard candidate cap.
	 */
	public function test_retrieve_fails_closed_when_store_returns_restricted_or_excess_rows(): void {
		$store     = new class() implements ChunkSearchStore {
			/**
			 * Ignore fixture replacement.
			 *
			 * @param string            $collection_id Collection scope.
			 * @param string            $document_key Document scope.
			 * @param ChunkSearchRecord ...$chunks Replacement chunks.
			 */
			public function replace_document_chunks( string $collection_id, string $document_key, ChunkSearchRecord ...$chunks ): void {
			}

			/**
			 * Ignore fixture deletion.
			 *
			 * @param string $collection_id Collection scope.
			 * @param string $document_key Document scope.
			 */
			public function delete_document( string $collection_id, string $document_key ): void {
			}

			/**
			 * Return an intentionally invalid store result to prove defensive bounds.
			 *
			 * @param LexicalSearchRequest $request Bounded trusted request.
			 * @return LexicalSearchMatch[]
			 */
			public function search( LexicalSearchRequest $request ): array {
				return array(
					new LexicalSearchMatch( LexicalRetrieverTest::record( '0', 'SKU-42/A guide', 'private' ) ),
					new LexicalSearchMatch( LexicalRetrieverTest::record( 'b', 'SKU-42/A guide' ) ),
					new LexicalSearchMatch( LexicalRetrieverTest::record( 'a', 'SKU-42/A guide' ) ),
				);
			}
		};
		$config    = new RetrievalConfig( lexical_candidate_limit: 2, fused_candidate_limit: 2 );
		$filter    = new LexicalFilter( 'knowledge', null, 7, 'en', 'public' );
		$query     = new RetrievalQuery( 'sku-42/a guide', array( 'sku-42/a', 'guide' ) );
		$retriever = new LexicalRetriever( $store, new LexicalScorer(), $config );

		$results = $retriever->retrieve( $query, $filter );

		self::assertCount( 1, $results );
		self::assertSame( hash( 'sha256', 'b' ), $results[0]->chunk_id );
		self::assertSame( 'public', $results[0]->visibility );
	}

	/**
	 * Build one projected chunk fixture.
	 *
	 * @param string $seed Fixture seed.
	 * @param string $content Chunk content.
	 * @param string $visibility Trusted visibility.
	 */
	public static function record( string $seed, string $content, string $visibility = 'public' ): ChunkSearchRecord {
		return new ChunkSearchRecord(
			hash( 'sha256', $seed ),
			'doc-' . $seed,
			7,
			'post',
			'Guide',
			null,
			$content,
			hash( 'sha256', $content ),
			'en',
			$visibility,
			0
		);
	}
}
