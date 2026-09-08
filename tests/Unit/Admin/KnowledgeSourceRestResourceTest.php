<?php
/**
 * M13 knowledge source REST projection tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\PagedResult;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

/**
 * Verifies the admin inventory exposes only bounded non-secret source fields.
 */
final class KnowledgeSourceRestResourceTest extends TestCase {
	/** Source configuration and content hashes must never leak into the inventory DTO. */
	public function test_list_projects_only_safe_source_fields(): void {
		$record     = new KnowledgeSourceRecord(
			7,
			'wp:post:42',
			'wordpress_post',
			'42',
			'Privacy policy',
			'https://example.test/privacy',
			'indexed',
			array( 'api_key' => 'TOP-SECRET-SENTINEL' ),
			'SOURCE-HASH-SENTINEL',
			new DateTimeImmutable( '2026-09-08T10:00:00+00:00' ),
			new DateTimeImmutable( '2026-09-08T09:00:00+00:00' ),
			new DateTimeImmutable( '2026-09-08T10:05:00+00:00' )
		);
		$repository = $this->createMock( KnowledgeSourceRepository::class );
		$repository->expects( self::once() )
			->method( 'paginate' )
			->with( 1, 20 )
			->willReturn( new PagedResult( array( $record ), 21, 1, 20 ) );

		$response = $this->call_list( $repository, 1, 20 );

		self::assertSame( 21, $response['total'] );
		self::assertSame( 1, $response['page'] );
		self::assertSame( 20, $response['per_page'] );
		self::assertSame(
			array(
				'id'             => 7,
				'source_key'     => 'wp:post:42',
				'source_type'    => 'wordpress_post',
				'external_id'    => '42',
				'title'          => 'Privacy policy',
				'canonical_url'  => 'https://example.test/privacy',
				'status'         => 'indexed',
				'last_synced_at' => '2026-09-08T10:00:00+00:00',
				'updated_at'     => '2026-09-08T10:05:00+00:00',
			),
			$response['items'][0]
		);

		$serialized = wp_json_encode( $response );
		self::assertIsString( $serialized );
		self::assertStringNotContainsString( 'TOP-SECRET-SENTINEL', $serialized );
		self::assertStringNotContainsString( 'SOURCE-HASH-SENTINEL', $serialized );
	}

	/** Invalid bounds are rejected before the repository can perform an unbounded query. */
	public function test_list_rejects_invalid_pagination_bounds(): void {
		$repository = $this->createMock( KnowledgeSourceRepository::class );
		$repository->expects( self::never() )->method( 'paginate' );

		self::assertSame( 'invalid_request', $this->call_list( $repository, 0, 20 )['error']['code'] );
		self::assertSame( 'invalid_request', $this->call_list( $repository, 1, 101 )['error']['code'] );
	}

	/**
	 * Invoke the not-yet-implemented resource dynamically so static analysis can reach PHPUnit RED.
	 *
	 * @param KnowledgeSourceRepository $repository Repository fixture.
	 * @param int                       $page Page number.
	 * @param int                       $per_page Page size.
	 * @return array<string,mixed>
	 */
	private function call_list( KnowledgeSourceRepository $repository, int $page, int $per_page ): array {
		$class = 'WpRagAiChatbot\\Admin\\Rest\\KnowledgeSourceRestResource';
		self::assertTrue( class_exists( $class ), 'KnowledgeSourceRestResource must exist.' );
		$resource = new $class( $repository );
		$response = call_user_func( array( $resource, 'list' ), $page, $per_page );
		self::assertIsArray( $response );

		return $response;
	}
}
