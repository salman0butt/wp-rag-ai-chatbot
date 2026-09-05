<?php
/**
 * M10 semantic vector-filter mapping tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval\Filter;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Filter\VectorFilterMapper;
use WpRagAiChatbot\VectorStore\Filter\AndFilter;
use WpRagAiChatbot\VectorStore\Filter\EqualsFilter;
use WpRagAiChatbot\VectorStore\Filter\InFilter;

/**
 * Defines fail-closed portable mapping for trusted retrieval filters.
 */
final class VectorFilterMapperTest extends TestCase {
	/**
	 * Trusted scalar and bounded-membership constraints map to M08 portable filters.
	 */
	public function test_maps_supported_constraints_to_portable_vector_filters(): void {
		$filter = new RetrievalFilter(
			visibility: 'public',
			language: 'en',
			source_ids: array( 7, 9 ),
			document_keys: array( 'doc-a', 'doc-b' )
		);

		$mapped = ( new VectorFilterMapper() )->map( $filter );

		self::assertInstanceOf( AndFilter::class, $mapped );
		self::assertCount( 4, $mapped->filters );
		self::assertInstanceOf( EqualsFilter::class, $mapped->filters[0] );
		self::assertSame( 'visibility', $mapped->filters[0]->key );
		self::assertSame( 'public', $mapped->filters[0]->value );
		self::assertInstanceOf( EqualsFilter::class, $mapped->filters[1] );
		self::assertSame( 'language', $mapped->filters[1]->key );
		self::assertSame( 'en', $mapped->filters[1]->value );
		self::assertInstanceOf( InFilter::class, $mapped->filters[2] );
		self::assertSame( 'source_id', $mapped->filters[2]->key );
		self::assertSame( array( 7, 9 ), $mapped->filters[2]->values );
		self::assertInstanceOf( InFilter::class, $mapped->filters[3] );
		self::assertSame( 'document_key', $mapped->filters[3]->key );
		self::assertSame( array( 'doc-a', 'doc-b' ), $mapped->filters[3]->values );
	}

	/**
	 * An unsupported mandatory constraint must fail before a broader search can run.
	 */
	public function test_rejects_unsupported_mandatory_constraint(): void {
		$filter = new RetrievalFilter(
			visibility: 'public',
			mandatory: array( 'tenant_id' => 'tenant-a' )
		);

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unsupported mandatory retrieval filter' );

		( new VectorFilterMapper() )->map( $filter );
	}

	/**
	 * An empty trusted filter needs no vendor expression.
	 */
	public function test_empty_filter_maps_to_null(): void {
		self::assertNull( ( new VectorFilterMapper() )->map( new RetrievalFilter() ) );
	}
}
