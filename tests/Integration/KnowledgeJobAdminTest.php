<?php
/**
 * M13 persisted knowledge job admin integration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbJobReadRepository;
use WpRagAiChatbot\Database\Repository\WpdbJobRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobReadRepository;

/** Verifies bounded persisted Task 3 inspection without reopening terminal state. */
final class KnowledgeJobAdminTest extends TestCase {
	/** Concrete persisted list reads are bounded and ordered without exposing mutation internals. */
	public function test_concrete_job_repository_pages_persisted_jobs(): void {
		$connection = $this->createMock( Connection::class );
		$connection->method( 'database_name' )->willReturn( 'wordpress_db' );
		$connection->method( 'prefix' )->willReturn( 'wp_' );
		$connection->expects( self::exactly( 2 ) )->method( 'prepare' )->willReturnCallback(
			static function ( string $query, mixed ...$args ): string {
				if ( str_contains( $query, 'COUNT(*)' ) ) {
					self::assertSame( array( 'wp_rag_ai_jobs' ), $args );
					return 'count-query';
				}

				self::assertStringContainsString( 'ORDER BY id DESC', $query );
				self::assertSame( array( 'wp_rag_ai_jobs', 20, 20 ), $args );
				return 'page-query';
			}
		);
		$connection->expects( self::once() )->method( 'get_var' )->with( 'count-query' )->willReturn( 21 );
		$connection->expects( self::once() )->method( 'get_results' )->with( 'page-query' )->willReturn( array( self::row( 'job-21', 'failed' ) ) );

		$repository = new WpdbJobReadRepository( $connection, new TableNames( 'wp_' ) );
		self::assertInstanceOf( JobReadRepository::class, $repository );

		$result = $repository->paginate( 2, 20 );
		self::assertSame( 21, $result->total );
		self::assertSame( 2, $result->page );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PagedResult follows the approved domain DTO contract.
		self::assertSame( 20, $result->perPage );
		self::assertSame( 'job-21', $result->items[0]->job_key );
	}

	/** Terminal cancellation is rejected before the concrete mutation repository can change persisted state. */
	public function test_terminal_cancel_reads_persisted_job_and_performs_no_mutation_query(): void {
		$connection = $this->createMock( Connection::class );
		$connection->method( 'database_name' )->willReturn( 'wordpress_db' );
		$connection->method( 'prefix' )->willReturn( 'wp_' );
		$connection->expects( self::once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $query, mixed ...$args ): string {
				self::assertStringContainsString( 'WHERE job_key = %s', $query );
				self::assertSame( array( 'wp_rag_ai_jobs', 'job-terminal' ), $args );
				return 'job-query';
			}
		);
		$connection->expects( self::once() )->method( 'get_row' )->with( 'job-query' )->willReturn( self::row( 'job-terminal', 'failed' ) );
		$connection->expects( self::never() )->method( 'query' );
		$connection->expects( self::never() )->method( 'update' );

		$reader     = new WpdbJobReadRepository( $connection, new TableNames( 'wp_' ) );
		$repository = new WpdbJobRepository( $connection, new TableNames( 'wp_' ) );
		$clock      = $this->createMock( Clock::class );
		$clock->method( 'now' )->willReturn( new DateTimeImmutable( '2026-09-08T19:00:00+00:00' ) );
		$resource_class = 'WpRagAiChatbot\\Admin\\Rest\\KnowledgeJobRestResource';
		$resource       = new $resource_class( $reader, $repository, $clock );

		$response = call_user_func_array( array( $resource, 'cancel' ), array( 'job-terminal' ) );
		self::assertIsArray( $response );
		self::assertSame( 'invalid_transition', $response['error']['code'] );
	}

	/**
	 * Build a persisted row accepted by the existing M09 job record contract.
	 *
	 * @param string $job_key Stable job identity.
	 * @param string $status Persisted status value.
	 */
	private static function row( string $job_key, string $status ): array {
		return array(
			'id'                  => 21,
			'job_key'             => $job_key,
			'type'                => 'index.document',
			'status'              => $status,
			'idempotency_key'     => 'document-index:fixture',
			'payload_json'        => '{"document_key":"doc:42","source_id":7,"collection_id":"collection-main","configuration_id":"config-default","generation":"v1"}',
			'attempts'            => 3,
			'max_attempts'        => 3,
			'available_at'        => '2026-09-08 18:00:00',
			'lease_owner'         => null,
			'lease_expires_at'    => null,
			'cancel_requested_at' => null,
			'progress_current'    => 10,
			'progress_total'      => 10,
			'progress_message'    => 'Indexing failed',
			'last_error_code'     => 'provider_unavailable',
			'last_error_message'  => 'Indexing provider is temporarily unavailable.',
			'started_at'          => '2026-09-08 18:01:00',
			'completed_at'        => '2026-09-08 18:02:00',
			'created_at'          => '2026-09-08 18:00:00',
			'updated_at'          => '2026-09-08 18:02:00',
		);
	}
}
