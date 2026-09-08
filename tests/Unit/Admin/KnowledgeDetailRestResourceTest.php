<?php
/**
 * M13 source/document/chunk detail projection tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\PagedResult;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Documents\DocumentRepository;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Retrieval\Lexical\ChunkInspectionStore;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;

/**
 * Verifies bounded allow-listed knowledge inspection DTOs.
 */
final class KnowledgeDetailRestResourceTest extends TestCase {
	/** Source detail must never serialize config or source hashes. */
	public function test_source_detail_projects_only_safe_fields(): void {
		$source = $this->source();
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( $source );

		$response = $this->resource( $sources )->source( 7 );

		self::assertSame( 7, $response['id'] );
		self::assertSame( 'wp:post:42', $response['source_key'] );
		self::assertSame( '2026-09-08T09:00:00+00:00', $response['created_at'] );
		$serialized = wp_json_encode( $response );
		self::assertIsString( $serialized );
		self::assertStringNotContainsString( 'SOURCE-CONFIG-SECRET', $serialized );
		self::assertStringNotContainsString( 'SOURCE-HASH-SECRET', $serialized );
	}

	/** Missing sources use the repository-owned stable not_found shape. */
	public function test_source_detail_returns_stable_not_found(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'findById' )->with( 404 )->willReturn( null );

		$response = $this->resource( $sources )->source( 404 );

		self::assertSame( 'not_found', $response['error']['code'] );
	}

	/** Document child pages are bounded and exclude raw content, metadata and hashes. */
	public function test_documents_are_bounded_and_allow_listed(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'findById' )->with( 7 )->willReturn( $this->source() );
		$documents = $this->createMock( DocumentRepository::class );
		$documents->expects( self::once() )
			->method( 'paginateBySource' )
			->with( 7, 2, 20 )
			->willReturn( new PagedResult( array( $this->document() ), 21, 2, 20 ) );

		$response = $this->resource( $sources, $documents )->documents( 7, 2, 20 );

		self::assertSame( 21, $response['total'] );
		self::assertSame( 2, $response['page'] );
		self::assertSame( 'doc:42', $response['items'][0]['document_key'] );
		$serialized = wp_json_encode( $response );
		self::assertIsString( $serialized );
		self::assertStringNotContainsString( 'DOCUMENT-CONTENT-SECRET', $serialized );
		self::assertStringNotContainsString( 'DOCUMENT-METADATA-SECRET', $serialized );
		self::assertStringNotContainsString( str_repeat( 'a', 64 ), $serialized );
	}

	/** Chunk inspection is bounded and explicitly reports content truncation. */
	public function test_chunks_are_bounded_and_report_truncation(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'findById' )->with( 7 )->willReturn( $this->source() );
		$documents = $this->createMock( DocumentRepository::class );
		$documents->method( 'findByKey' )->with( 'doc:42' )->willReturn( $this->document() );
		$chunks = $this->createMock( ChunkInspectionStore::class );
		$chunks->expects( self::once() )
			->method( 'paginateByDocument' )
			->with( 'doc:42', 1, 20 )
			->willReturn( new PagedResult( array( $this->chunk() ), 1, 1, 20 ) );

		$response = $this->resource( $sources, $documents, $chunks )->chunks( 7, 'doc:42', 1, 20 );

		self::assertSame( 1, $response['total'] );
		self::assertSame( 2000, strlen( $response['items'][0]['content'] ) );
		self::assertTrue( $response['items'][0]['content_truncated'] );
		self::assertSame( 0, $response['items'][0]['sequence'] );
		$serialized = wp_json_encode( $response );
		self::assertIsString( $serialized );
		self::assertStringNotContainsString( 'CHUNK-METADATA-SECRET', $serialized );
		self::assertStringNotContainsString( str_repeat( 'b', 64 ), $serialized );
	}

	/** Invalid child bounds are rejected before repository access. */
	public function test_child_pages_reject_invalid_bounds(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'findById' );
		$documents = $this->createMock( DocumentRepository::class );
		$documents->expects( self::never() )->method( 'paginateBySource' );

		$response = $this->resource( $sources, $documents )->documents( 7, 0, 20 );

		self::assertSame( 'invalid_request', $response['error']['code'] );
	}

	private function source(): KnowledgeSourceRecord {
		return new KnowledgeSourceRecord(
			7,
			'wp:post:42',
			'wordpress_post',
			'42',
			'Privacy policy',
			'https://example.test/privacy',
			'indexed',
			array( 'secret' => 'SOURCE-CONFIG-SECRET' ),
			'SOURCE-HASH-SECRET',
			new DateTimeImmutable( '2026-09-08T10:00:00+00:00' ),
			new DateTimeImmutable( '2026-09-08T09:00:00+00:00' ),
			new DateTimeImmutable( '2026-09-08T10:05:00+00:00' )
		);
	}

	private function document(): DocumentRecord {
		return new DocumentRecord(
			11,
			'doc:42',
			7,
			'42',
			'wordpress_post',
			'Privacy policy',
			'https://example.test/privacy',
			'DOCUMENT-CONTENT-SECRET',
			array( 'secret' => 'DOCUMENT-METADATA-SECRET' ),
			'v1',
			str_repeat( 'a', 64 ),
			'en',
			'public',
			new DateTimeImmutable( '2026-09-08T09:00:00+00:00' ),
			new DateTimeImmutable( '2026-09-08T10:05:00+00:00' )
		);
	}

	private function chunk(): ChunkSearchRecord {
		return new ChunkSearchRecord(
			str_repeat( 'c', 64 ),
			'doc:42',
			7,
			'wordpress_post',
			'Privacy policy',
			'https://example.test/privacy',
			str_repeat( 'x', 2100 ),
			str_repeat( 'b', 64 ),
			'en',
			'public',
			0,
			array( 'secret' => 'CHUNK-METADATA-SECRET' )
		);
	}

	/**
	 * Construct the not-yet-implemented resource dynamically so static analysis can reach PHPUnit RED.
	 */
	private function resource(
		KnowledgeSourceRepository $sources,
		?DocumentRepository $documents = null,
		?ChunkInspectionStore $chunks = null
	): object {
		$class = 'WpRagAiChatbot\\Admin\\Rest\\KnowledgeDetailRestResource';
		self::assertTrue( class_exists( $class ), 'KnowledgeDetailRestResource must exist.' );

		return new $class(
			$sources,
			$documents ?? $this->createMock( DocumentRepository::class ),
			$chunks ?? $this->createMock( ChunkInspectionStore::class )
		);
	}
}
