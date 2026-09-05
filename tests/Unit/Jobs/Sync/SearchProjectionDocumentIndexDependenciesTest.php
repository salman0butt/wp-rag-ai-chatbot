<?php
/**
 * M10 accepted-plan lexical projection synchronization tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs\Sync;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Planning\IndexPlan;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;
use WpRagAiChatbot\Jobs\Sync\SearchProjectionDocumentIndexDependencies;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchStore;

/**
 * Proves lexical search projection follows the same accepted M07 plan as vector execution.
 */
final class SearchProjectionDocumentIndexDependenciesTest extends TestCase {
	/**
	 * Planning remains owned by the wrapped server-side dependencies.
	 */
	public function test_plan_delegates_without_mutating_projection(): void {
		$payload = $this->payload();
		$plan    = new IndexPlan( array(), array(), array(), array(), array() );
		$inner   = $this->createMock( DocumentIndexDependencies::class );
		$store   = $this->createMock( ChunkSearchStore::class );

		$inner->expects( self::once() )->method( 'plan' )->with( $payload )->willReturn( $plan );
		$store->expects( self::never() )->method( 'replace_document_chunks' );
		$store->expects( self::never() )->method( 'delete_document' );

		$dependencies = new SearchProjectionDocumentIndexDependencies( $inner, $store );

		self::assertSame( $plan, $dependencies->plan( $payload ) );
	}

	/**
	 * A successful accepted-plan execution replaces lexical rows from the complete current canonical plan.
	 */
	public function test_execute_projects_complete_current_document_after_primary_execution(): void {
		$payload = $this->payload();
		$upsert  = $this->chunk(
			'a',
			0,
			array(
				'safe_key' => 'safe value',
				'nested'   => array( 'drop' ),
			)
		);
		$refresh = $this->chunk( 'b', 1 );
		$stable  = $this->chunk( 'c', 2 );
		$plan    = new IndexPlan( array( $upsert ), array( $refresh ), array(), array( $stable ), array() );
		$inner   = $this->createMock( DocumentIndexDependencies::class );
		$store   = $this->createMock( ChunkSearchStore::class );

		$inner->expects( self::once() )->method( 'execute' )->with( $payload, $plan );
		$store->expects( self::once() )
			->method( 'replace_document_chunks' )
			->with(
				'collection-main',
				'doc-42',
				self::callback( static fn ( ChunkSearchRecord $record ): bool => str_repeat( 'a', 64 ) === $record->chunk_key && array( 'safe_key' => 'safe value' ) === $record->metadata ),
				self::callback( static fn ( ChunkSearchRecord $record ): bool => str_repeat( 'b', 64 ) === $record->chunk_key ),
				self::callback( static fn ( ChunkSearchRecord $record ): bool => str_repeat( 'c', 64 ) === $record->chunk_key )
			);
		$store->expects( self::never() )->method( 'delete_document' );

		( new SearchProjectionDocumentIndexDependencies( $inner, $store ) )->execute( $payload, $plan );
	}

	/**
	 * An empty accepted plan removes the document from the lexical projection after primary execution.
	 */
	public function test_execute_deletes_projection_when_document_has_no_current_chunks(): void {
		$payload = $this->payload();
		$plan    = new IndexPlan( array(), array(), array( str_repeat( 'd', 64 ) ), array(), array() );
		$inner   = $this->createMock( DocumentIndexDependencies::class );
		$store   = $this->createMock( ChunkSearchStore::class );

		$inner->expects( self::once() )->method( 'execute' )->with( $payload, $plan );
		$store->expects( self::once() )->method( 'delete_document' )->with( 'collection-main', 'doc-42' );
		$store->expects( self::never() )->method( 'replace_document_chunks' );

		( new SearchProjectionDocumentIndexDependencies( $inner, $store ) )->execute( $payload, $plan );
	}

	/**
	 * Projection writes happen after the primary executor so a retry can safely replay both stores.
	 */
	public function test_projection_failure_happens_after_primary_execution_for_retry_safety(): void {
		$payload  = $this->payload();
		$plan     = new IndexPlan( array( $this->chunk( 'a', 0 ) ), array(), array(), array(), array() );
		$inner    = $this->createMock( DocumentIndexDependencies::class );
		$store    = $this->createMock( ChunkSearchStore::class );
		$executed = false;

		$inner->expects( self::once() )
			->method( 'execute' )
			->willReturnCallback(
				static function () use ( &$executed ): void {
					$executed = true;
				}
			);
		$store->expects( self::once() )
			->method( 'replace_document_chunks' )
			->willReturnCallback(
				static function () use ( &$executed ): void {
					self::assertTrue( $executed );
					throw new RuntimeException( 'projection unavailable' );
				}
			);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'projection unavailable' );

		( new SearchProjectionDocumentIndexDependencies( $inner, $store ) )->execute( $payload, $plan );
	}

	/**
	 * Build the stable queue identity used by synchronization tests.
	 */
	private function payload(): DocumentIndexJobPayload {
		return new DocumentIndexJobPayload( 'doc-42', 42, 'collection-main', 'index-profile-default', 'generation-7' );
	}

	/**
	 * Build one valid chunk belonging to the payload document.
	 *
	 * @param string               $hex Repeated hexadecimal fixture character.
	 * @param int                  $sequence Stable sequence.
	 * @param array<string, mixed> $metadata Source metadata fixture.
	 */
	private function chunk( string $hex, int $sequence, array $metadata = array() ): ChunkRecord {
		return new ChunkRecord(
			chunkKey: str_repeat( $hex, 64 ),
			documentKey: 'doc-42',
			sourceId: 42,
			documentType: 'post',
			title: 'Example document',
			canonicalUrl: 'https://example.com/document',
			content: 'Searchable chunk ' . $sequence,
			contentHash: hash( 'sha256', 'content-' . $sequence ),
			sourceVersion: 'revision-7',
			documentContentHash: hash( 'sha256', 'document-content' ),
			language: 'en',
			visibility: 'public',
			sequence: $sequence,
			parentChunkKey: null,
			headingPath: array( 'Section' ),
			tokenCount: 3,
			chunkingVersion: 'v1',
			chunkingFingerprint: hash( 'sha256', 'chunker-v1' ),
			embeddingCompatibilityKey: null,
			sourceMetadata: $metadata
		);
	}
}
