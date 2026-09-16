<?php
/**
 * Bot retrieval administration resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Admin\Rest\BotRetrievalResource;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\BotRetrievalBindingRepository;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

/** Defines the bounded, server-owned bot knowledge binding contract. */
final class BotRetrievalResourceTest extends TestCase {
	private const BOT_ID = '0123456789abcdef0123456789abcdef';

	/** Missing persisted binding is a safe unconfigured response. */
	public function test_read_returns_bounded_unconfigured_projection_when_binding_is_missing(): void {
		$bindings = $this->bindings();
		$bindings->expects( self::once() )->method( 'find' )->with( new BotId( self::BOT_ID ) )->willReturn( null );

		$response = $this->resource( $bindings )->read( self::BOT_ID );

		self::assertSame(
			array(
				'retrieval' => array(
					'configured'       => false,
					'source_id'        => null,
					'source_title'     => null,
					'collection_id'    => null,
					'collection_ready' => false,
				),
			),
			$response
		);
	}

	/** A compatible persisted binding projects only safe source and readiness fields. */
	public function test_read_projects_ready_server_owned_binding(): void {
		$bindings = $this->bindings();
		$bindings->expects( self::once() )->method( 'find' )->with( new BotId( self::BOT_ID ) )->willReturn( new BotRetrievalBinding( 7, 'wp-rag-default' ) );
		$sources = $this->sources();
		$sources->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( $this->source() );

		$response = $this->resource( $bindings, $sources, $this->connection( $this->collection_row() ) )->read( self::BOT_ID );

		self::assertSame(
			array(
				'retrieval' => array(
					'configured'       => true,
					'source_id'        => 7,
					'source_title'     => 'Support content',
					'collection_id'    => 'wp-rag-default',
					'collection_ready' => true,
				),
			),
			$response
		);
	}

	/** A binding whose source was removed becomes a stable safe error. */
	public function test_read_rejects_stale_binding_source(): void {
		$bindings = $this->bindings();
		$bindings->method( 'find' )->willReturn( new BotRetrievalBinding( 7, 'wp-rag-default' ) );
		$sources = $this->sources();
		$sources->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( null );

		$response = $this->resource( $bindings, $sources )->read( self::BOT_ID );

		self::assertSame( 'source_not_found', $response['error']['code'] );
	}

	/** Malformed persisted binding data never leaks a repository exception. */
	public function test_read_normalizes_malformed_persisted_binding(): void {
		$bindings = $this->bindings();
		$bindings->method( 'find' )->willThrowException( new RuntimeException( 'raw persisted binding details' ) );

		$response = $this->resource( $bindings )->read( self::BOT_ID );

		self::assertSame( 'retrieval_read_failed', $response['error']['code'] );
		self::assertStringNotContainsString( 'raw persisted binding details', $response['error']['message'] );
	}

	/** The selected source must carry the exact server-owned semantic profile. */
	public function test_read_rejects_stale_source_profile(): void {
		$bindings = $this->bindings();
		$bindings->method( 'find' )->willReturn( new BotRetrievalBinding( 7, 'wp-rag-default' ) );
		$stale   = $this->source( 1536 );
		$sources = $this->sources();
		$sources->method( 'findById' )->willReturn( $stale );

		$response = $this->resource( $bindings, $sources )->read( self::BOT_ID );

		self::assertSame( 'source_configuration_invalid', $response['error']['code'] );
	}

	/** Browser payloads cannot smuggle collection/provider/model/path fields into a binding. */
	public function test_write_rejects_extra_keys_before_persistence(): void {
		$bindings = $this->bindings();
		$bindings->expects( self::never() )->method( 'save' );
		$sources = $this->sources();
		$sources->expects( self::never() )->method( 'findById' );

		$response = $this->resource( $bindings, $sources )->write(
			self::BOT_ID,
			array(
				'source_id'     => 7,
				'collection_id' => 'attacker-selected',
			)
		);

		self::assertSame( 'invalid_request', $response['error']['code'] );
	}

	/** PUT derives the fixed collection and persists only the trusted binding. */
	public function test_write_saves_only_server_derived_binding(): void {
		$bindings = $this->bindings();
		$bindings->expects( self::once() )
			->method( 'save' )
			->with(
				new BotId( self::BOT_ID ),
				self::callback(
					static fn ( BotRetrievalBinding $binding ): bool => 7 === $binding->source_id && 'wp-rag-default' === $binding->collection_id
				)
			);
		$sources = $this->sources();
		$sources->method( 'findById' )->willReturn( $this->source() );

		$response = $this->resource( $bindings, $sources, $this->connection( $this->collection_row() ) )->write(
			self::BOT_ID,
			array( 'source_id' => 7 )
		);

		self::assertTrue( $response['retrieval']['configured'] );
		self::assertSame( 'wp-rag-default', $response['retrieval']['collection_id'] );
	}

	/** A source cannot be bound before its local collection has the fixed profile. */
	public function test_write_rejects_collection_that_is_not_ready(): void {
		$bindings = $this->bindings();
		$bindings->expects( self::never() )->method( 'save' );
		$sources = $this->sources();
		$sources->method( 'findById' )->willReturn( $this->source() );

		$response = $this->resource( $bindings, $sources, $this->connection( null ) )->write( self::BOT_ID, array( 'source_id' => 7 ) );

		self::assertSame( 'collection_not_ready', $response['error']['code'] );
	}

	/** DELETE clears only the bot binding and does not touch source/index persistence. */
	public function test_delete_clears_only_binding(): void {
		$bindings = $this->bindings();
		$bindings->expects( self::once() )->method( 'clear' )->with( new BotId( self::BOT_ID ) );
		$sources = $this->sources();
		$sources->expects( self::never() )->method( 'delete' );
		$connection = $this->createMock( Connection::class );
		$connection->expects( self::never() )->method( 'delete' );

		self::assertSame( array( 'deleted' => true ), $this->resource( $bindings, $sources, $connection )->delete( self::BOT_ID ) );
	}

	/** Invalid bot identifiers are rejected before any persistence access. */
	public function test_invalid_bot_id_is_safe(): void {
		$bindings = $this->bindings();
		$bindings->expects( self::never() )->method( 'find' );

		$response = $this->resource( $bindings )->read( 'not-a-bot' );

		self::assertSame( 'invalid_bot_id', $response['error']['code'] );
	}

	/**
	 * Build the resource under test so RED reaches PHPUnit while the class is absent.
	 *
	 * @param BotRetrievalBindingRepository&MockObject $bindings Binding mock.
	 * @param KnowledgeSourceRepository|null           $sources Source mock.
	 * @param Connection|null                          $connection Database mock.
	 */
	private function resource(
		BotRetrievalBindingRepository&MockObject $bindings,
		?KnowledgeSourceRepository $sources = null,
		?Connection $connection = null
	): BotRetrievalResource {
		self::assertTrue( class_exists( BotRetrievalResource::class ), 'BotRetrievalResource must exist.' );

		return new BotRetrievalResource(
			$bindings,
			$sources ?? $this->sources(),
			$connection ?? $this->connection( null )
		);
	}

	/**
	 * Build the binding repository mock.
	 *
	 * @return BotRetrievalBindingRepository&MockObject
	 */
	private function bindings(): BotRetrievalBindingRepository&MockObject {
		return $this->createMock( BotRetrievalBindingRepository::class );
	}

	/**
	 * Build the source repository mock.
	 *
	 * @return KnowledgeSourceRepository&MockObject
	 */
	private function sources(): KnowledgeSourceRepository&MockObject {
		return $this->createMock( KnowledgeSourceRepository::class );
	}

	/**
	 * Build a local vector database mock.
	 *
	 * @param array<string,mixed>|null $row Collection row.
	 * @return Connection&MockObject
	 */
	private function connection( ?array $row ): Connection&MockObject {
		$connection = $this->createMock( Connection::class );
		$connection->method( 'table_exists' )->willReturn( true );
		$connection->method( 'prepare' )->willReturn( 'prepared' );
		$connection->method( 'get_row' )->willReturn( $row );

		return $connection;
	}

	/**
	 * Build a source carrying the fixed Task 2/3 semantic profile.
	 *
	 * @param int $dimensions Embedding dimensions for the source fixture.
	 */
	private function source( int $dimensions = 3072 ): KnowledgeSourceRecord {
		$now = new DateTimeImmutable( '2026-09-15T12:00:00+00:00' );

		return new KnowledgeSourceRecord(
			7,
			'source:support',
			'manual_text',
			null,
			'Support content',
			null,
			'active',
			array(
				'text'               => 'Support instructions.',
				'semantic_retrieval' => array(
					'collection_id'         => 'wp-rag-default',
					'configuration_id'      => 'gemini-embedding-001-3072-cosine-v1',
					'embedding_provider_id' => 'gemini_direct',
					'embedding_model_id'    => 'gemini-embedding-001',
					'dimensions'            => $dimensions,
					'normalization'         => 'none',
					'distance'              => 'cosine',
					'vector_store_id'       => 'local-wordpress',
				),
			),
			'source-generation',
			null,
			$now,
			$now
		);
	}

	/**
	 * Return a compatible persisted local collection row.
	 *
	 * @return array{fingerprint:string,dimensions:int}
	 */
	private function collection_row(): array {
		return array(
			'fingerprint' => hash(
				'sha256',
				"v1\nprovider=gemini_direct\nmodel=gemini-embedding-001\ndimensions=3072\nnormalization=none\ndistance=cosine"
			),
			'dimensions'  => 3072,
		);
	}
}
