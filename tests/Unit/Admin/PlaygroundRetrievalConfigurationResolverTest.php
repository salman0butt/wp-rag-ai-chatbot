<?php
/**
 * M13 persisted Playground retrieval configuration resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalConfigurationResolver;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

/**
 * Proves Playground retrieval selects explicit persisted source and collection identifiers.
 */
final class PlaygroundRetrievalConfigurationResolverTest extends TestCase {
	/** Explicit persisted source and collection identifiers must resolve without defaults. */
	public function test_resolves_explicit_persisted_source_and_collection(): void {
		$source     = $this->source();
		$repository = $this->createMock( KnowledgeSourceRepository::class );
		$repository->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( $source );
		$connection = $this->connection_for_collection( 'production-rag' );
		$resolver   = new PlaygroundRetrievalConfigurationResolver(
			$repository,
			$connection,
			new TableNames( 'wp_' )
		);

		$resolved = $resolver->resolve( 7, 'production-rag' );

		self::assertSame( $source, $resolved->source );
		self::assertSame( 'production-rag', $resolved->collection_id );
	}

	/** Missing source IDs must fail closed before any collection fallback can occur. */
	public function test_rejects_missing_source(): void {
		$repository = $this->createMock( KnowledgeSourceRepository::class );
		$repository->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( null );
		$connection = $this->createMock( Connection::class );
		$connection->expects( self::never() )->method( 'prepare' );
		$resolver = new PlaygroundRetrievalConfigurationResolver(
			$repository,
			$connection,
			new TableNames( 'wp_' )
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Playground retrieval source was not found.' );
		$resolver->resolve( 7, 'production-rag' );
	}

	/** Unknown collection keys must never fall back to an unrelated collection. */
	public function test_rejects_missing_collection(): void {
		$repository = $this->createMock( KnowledgeSourceRepository::class );
		$repository->method( 'findById' )->willReturn( $this->source() );
		$connection = $this->connection_for_collection( null );
		$resolver   = new PlaygroundRetrievalConfigurationResolver(
			$repository,
			$connection,
			new TableNames( 'wp_' )
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Playground vector collection was not found.' );
		$resolver->resolve( 7, 'production-rag' );
	}

	/**
	 * Build one persisted knowledge-source fixture.
	 */
	private function source(): KnowledgeSourceRecord {
		$now = new DateTimeImmutable( '2026-09-10T10:00:00+00:00' );
		return new KnowledgeSourceRecord(
			7,
			'wordpress-posts',
			'wordpress_posts',
			null,
			'WordPress Posts',
			null,
			'indexed',
			array(),
			null,
			$now,
			$now,
			$now
		);
	}

	/**
	 * Build a connection double for one persisted collection lookup.
	 *
	 * @param string|null $persisted_collection Persisted collection key, or null when absent.
	 */
	private function connection_for_collection( ?string $persisted_collection ): Connection&MockObject {
		$connection = $this->createMock( Connection::class );
		$connection
			->expects( self::once() )
			->method( 'prepare' )
			->with(
				'SELECT collection_key FROM %i WHERE collection_key = %s LIMIT 1',
				'wp_rag_ai_vector_collections',
				'production-rag'
			)
			->willReturn( 'prepared-collection-query' );
		$connection
			->expects( self::once() )
			->method( 'get_var' )
			->with( 'prepared-collection-query' )
			->willReturn( $persisted_collection );
		return $connection;
	}
}
