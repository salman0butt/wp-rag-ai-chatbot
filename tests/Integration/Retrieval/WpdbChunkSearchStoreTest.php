<?php
/**
 * Chunk-search store contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration\Retrieval;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Defines hard trust-boundary limits before the wpdb store exists.
 */
final class WpdbChunkSearchStoreTest extends TestCase {
	/**
	 * Search records reject composite metadata rather than serializing arbitrary source payloads.
	 */
	public function test_chunk_search_record_rejects_composite_metadata(): void {
		$class = 'WpRagAiChatbot\\Retrieval\\Lexical\\ChunkSearchRecord';
		if ( ! class_exists( $class ) ) {
			self::fail( 'ChunkSearchRecord must exist before M10 Task 3 can pass.' );
		}

		$this->expectException( InvalidArgumentException::class );
		new $class(
			hash( 'sha256', 'chunk-a' ),
			'document-a',
			7,
			'post',
			'Title',
			'https://example.test/a',
			'SKU-42/A installation guide',
			hash( 'sha256', 'SKU-42/A installation guide' ),
			'en',
			'public',
			0,
			array( 'private_payload' => array( 'blocked' ) )
		);
	}

	/**
	 * Search records enforce the same small portable metadata entry ceiling used by vector records.
	 */
	public function test_chunk_search_record_rejects_more_than_32_metadata_entries(): void {
		$class = 'WpRagAiChatbot\\Retrieval\\Lexical\\ChunkSearchRecord';
		if ( ! class_exists( $class ) ) {
			self::fail( 'ChunkSearchRecord must exist before M10 Task 3 can pass.' );
		}

		$metadata = array();
		for ( $index = 0; $index < 33; ++$index ) {
			$metadata[ 'key_' . $index ] = $index;
		}

		$this->expectException( InvalidArgumentException::class );
		new $class(
			hash( 'sha256', 'chunk-b' ),
			'document-b',
			8,
			'post',
			'Title',
			null,
			'Content',
			hash( 'sha256', 'Content' ),
			'en',
			'public',
			0,
			$metadata
		);
	}

	/**
	 * The SQL candidate request has an absolute ceiling independent of configurable lower limits.
	 */
	public function test_lexical_search_request_rejects_more_than_1000_candidates(): void {
		$filter_class  = 'WpRagAiChatbot\\Retrieval\\Lexical\\LexicalFilter';
		$request_class = 'WpRagAiChatbot\\Retrieval\\Lexical\\LexicalSearchRequest';
		if ( ! class_exists( $filter_class ) || ! class_exists( $request_class ) ) {
			self::fail( 'LexicalFilter and LexicalSearchRequest must exist before M10 Task 3 can pass.' );
		}

		$filter = new $filter_class( 'knowledge' );
		$this->expectException( InvalidArgumentException::class );
		new $request_class( $filter, array( 'sku-42' ), 1001 );
	}
}
