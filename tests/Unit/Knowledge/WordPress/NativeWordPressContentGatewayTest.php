<?php
/**
 * Native WordPress content gateway tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\WordPress;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use stdClass;
use WpRagAiChatbot\Knowledge\WordPress\NativeWordPressContentGateway;
use WpRagAiChatbot\Knowledge\WordPress\WordPressPost;

/**
 * Verifies the native WordPress content boundary.
 */
final class NativeWordPressContentGatewayTest extends TestCase {
	/**
	 * Start Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear Brain Monkey down after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Public post types are normalized into a deterministic list.
	 */
	public function test_public_post_types_returns_sorted_public_type_names(): void {
		self::assertTrue( class_exists( NativeWordPressContentGateway::class ), 'Native gateway must exist before public post types can be read.' );

		Functions\expect( 'get_post_types' )
			->once()
			->with( array( 'public' => true ), 'names' )
			->andReturn(
				array(
					'product' => 'product',
					'post'    => 'post',
					'page'    => 'page',
				)
			);

		$gateway = new NativeWordPressContentGateway();

		self::assertSame( array( 'page', 'post', 'product' ), $gateway->publicPostTypes() );
	}

	/**
	 * Queries are bounded, paged, deterministic, and public-only by default.
	 */
	public function test_posts_uses_bounded_public_query_arguments(): void {
		self::assertTrue( class_exists( NativeWordPressContentGateway::class ), 'Native gateway must exist before query arguments can be verified.' );

		Functions\expect( 'get_posts' )
			->once()
			->with(
				array(
					'post_type'           => array( 'page', 'post' ),
					'post_status'         => array( 'publish' ),
					'posts_per_page'      => 25,
					'paged'               => 2,
					'orderby'             => 'ID',
					'order'               => 'ASC',
					'has_password'        => false,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			)
			->andReturn( array() );

		$gateway = new NativeWordPressContentGateway();

		self::assertSame( array(), $gateway->posts( array( 'post', 'page', 'post' ), false, 2, 25 ) );
	}

	/**
	 * Private posts are requested only when explicitly enabled.
	 */
	public function test_posts_adds_private_status_only_when_enabled(): void {
		self::assertTrue( class_exists( NativeWordPressContentGateway::class ), 'Native gateway must exist before private-query behavior can be verified.' );

		Functions\expect( 'get_posts' )
			->once()
			->with(
				array(
					'post_type'           => array( 'post' ),
					'post_status'         => array( 'publish', 'private' ),
					'posts_per_page'      => 10,
					'paged'               => 1,
					'orderby'             => 'ID',
					'order'               => 'ASC',
					'has_password'        => false,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			)
			->andReturn( array() );

		$gateway = new NativeWordPressContentGateway();

		self::assertSame( array(), $gateway->posts( array( 'post' ), true, 1, 10 ) );
	}

	/**
	 * Native post objects are mapped into immutable gateway values.
	 */
	public function test_posts_maps_permalink_password_and_taxonomy_labels(): void {
		self::assertTrue( class_exists( NativeWordPressContentGateway::class ), 'Native gateway must exist before post mapping can be verified.' );
		self::assertTrue( class_exists( WordPressPost::class ), 'WordPressPost value must exist before gateway mapping can pass.' );

		$post                    = new stdClass();
		$post->ID                = 42;
		$post->post_type         = 'post';
		$post->post_status       = 'publish';
		$post->post_title        = 'Release notes';
		$post->post_excerpt      = 'Short summary';
		$post->post_content      = '<p>Long body</p>';
		$post->post_modified_gmt = '2026-09-03 00:15:00';
		$post->post_password     = '';
		$post->post_author       = '7';

		$category           = new stdClass();
		$category->taxonomy = 'category';
		$category->name     = 'Updates';
		$category->slug     = 'updates';

		$tag           = new stdClass();
		$tag->taxonomy = 'post_tag';
		$tag->name     = 'AI';
		$tag->slug     = 'ai';

		Functions\expect( 'get_posts' )->once()->andReturn( array( $post ) );
		Functions\expect( 'get_permalink' )->once()->with( 42 )->andReturn( 'https://example.test/release-notes/' );
		Functions\expect( 'get_object_taxonomies' )->once()->with( 'post', 'names' )->andReturn( array( 'post_tag', 'category' ) );
		Functions\expect( 'wp_get_object_terms' )
			->once()
			->with( 42, array( 'category', 'post_tag' ), array( 'fields' => 'all' ) )
			->andReturn( array( $category, $tag ) );
		Functions\expect( 'is_wp_error' )->once()->with( array( $category, $tag ) )->andReturn( false );
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( string $text ): string {
				return preg_replace( '/<[^>]+>/', '', $text ) ?? '';
			}
		);

		$gateway = new NativeWordPressContentGateway();
		$result  = $gateway->posts( array( 'post' ), false, 1, 20 );

		self::assertCount( 1, $result );
		self::assertInstanceOf( WordPressPost::class, $result[0] );
		self::assertSame( 42, $result[0]->id );
		self::assertSame( 'post', $result[0]->type );
		self::assertSame( 'publish', $result[0]->status );
		self::assertSame( 'Release notes', $result[0]->title );
		self::assertSame( 'Short summary', $result[0]->excerpt );
		self::assertSame( 'Long body', $result[0]->content );
		self::assertSame( 'https://example.test/release-notes/', $result[0]->url );
		self::assertSame( '2026-09-03 00:15:00', $result[0]->modifiedGmt );
		self::assertNull( $result[0]->language );
		self::assertFalse( $result[0]->passwordProtected );
		self::assertSame( 7, $result[0]->authorId );
		self::assertSame(
			array(
				'category' => array(
					array(
						'name' => 'Updates',
						'slug' => 'updates',
					),
				),
				'post_tag' => array(
					array(
						'name' => 'AI',
						'slug' => 'ai',
					),
				),
			),
			$result[0]->taxonomyLabels
		);
	}
}
