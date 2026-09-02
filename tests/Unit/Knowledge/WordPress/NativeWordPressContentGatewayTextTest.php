<?php
/**
 * Native WordPress content gateway text-boundary tests.
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

/**
 * Verifies WordPress post text is converted at the native boundary.
 */
final class NativeWordPressContentGatewayTextTest extends TestCase {
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
	 * Native post title, excerpt, and body are stripped to safe text.
	 */
	public function test_posts_converts_wordpress_html_to_safe_text(): void {
		$post                    = new stdClass();
		$post->ID                = 51;
		$post->post_type         = 'post';
		$post->post_status       = 'publish';
		$post->post_title        = '<strong>Release</strong>';
		$post->post_excerpt      = '<p>Short <em>summary</em></p>';
		$post->post_content      = '<p>Body <script>alert(1)</script><b>text</b></p>';
		$post->post_modified_gmt = '2026-09-03 01:00:00';
		$post->post_password     = '';
		$post->post_author       = '7';

		Functions\expect( 'get_posts' )->once()->andReturn( array( $post ) );
		Functions\expect( 'get_permalink' )->once()->with( 51 )->andReturn( 'https://example.test/release/' );
		Functions\expect( 'get_object_taxonomies' )->once()->with( 'post', 'names' )->andReturn( array() );
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( string $text ): string {
				return preg_replace( '/<[^>]+>/', '', $text ) ?? '';
			}
		);

		$result = ( new NativeWordPressContentGateway() )->posts( array( 'post' ), false, 1, 20 );

		self::assertCount( 1, $result );
		self::assertSame( 'Release', $result[0]->title );
		self::assertSame( 'Short summary', $result[0]->excerpt );
		self::assertSame( 'Body alert(1)text', $result[0]->content );
	}
}
