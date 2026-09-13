<?php
/**
 * Tests for the public-safe WordPress display context projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Frontend\WordPressDisplayContextResolver;

/** Verifies that only bounded presentation facts are projected to the browser. */
final class WordPressDisplayContextResolverTest extends TestCase {
	/** Start Brain Monkey for each test. */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_unslash' )->alias(
			static fn ( mixed $value ): mixed => is_string( $value ) ? stripslashes( $value ) : $value
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				if ( PHP_URL_PATH !== $component ) {
					return false;
				}

				$path = preg_replace( '/[?#].*$/', '', $url );

				return is_string( $path ) ? $path : false;
			}
		);
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Functions\when( 'is_rtl' )->justReturn( false );
		Functions\when( 'did_action' )->justReturn( 0 );
		Functions\when( 'current_datetime' )->justReturn(
			new DateTimeImmutable( '2026-09-13 12:00:00', new DateTimeZone( 'UTC' ) )
		);
	}

	/** Restore global state after each test. */
	protected function tearDown(): void {
		unset( $_SERVER['REQUEST_URI'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Authenticated page context exposes only normalized path/post/auth/role-match facts. */
	public function test_resolve_projects_bounded_page_auth_and_role_facts(): void {
		$_SERVER['REQUEST_URI'] = '/Support/FAQ/?ticket=secret#private';

		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_post_type' )->justReturn( 'Page' );
		Functions\when( 'wp_get_current_user' )->justReturn(
			(object) array(
				'ID'         => 42,
				'user_email' => 'private@example.test',
				'roles'      => array( 'Administrator', 'Customer', 'invalid role' ),
				'allcaps'    => array( 'manage_options' => true ),
			)
		);

		$facts   = ( new WordPressDisplayContextResolver() )->resolve();
		$encoded = wp_json_encode( $facts );
		if ( false === $encoded ) {
			$encoded = '';
		}

		self::assertSame( '/support/faq/', $facts['path'] );
		self::assertTrue( $facts['isAuthenticated'] );
		self::assertSame( 'page', $facts['postType'] );
		self::assertSame( array( 'administrator', 'customer' ), $facts['roleMatches'] );
		self::assertNull( $facts['wooArea'] );
		self::assertSame( 'en-us', $facts['siteLocale'] );
		self::assertSame( 'ltr', $facts['siteDirection'] );
		self::assertSame( 0, $facts['siteWeekday'] );
		self::assertSame( 720, $facts['siteMinuteOfDay'] );
		self::assertArrayNotHasKey( 'user', $facts );
		self::assertArrayNotHasKey( 'userId', $facts );
		self::assertArrayNotHasKey( 'email', $facts );
		self::assertArrayNotHasKey( 'capabilities', $facts );
		self::assertStringNotContainsString( 'ticket', $encoded );
		self::assertStringNotContainsString( 'private@example.test', $encoded );
	}

	/** Anonymous requests remain minimal and tolerate missing post context. */
	public function test_resolve_projects_anonymous_context_without_user_lookup(): void {
		$_SERVER['REQUEST_URI'] = '/';

		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'get_post_type' )->justReturn( false );
		Functions\expect( 'wp_get_current_user' )->never();

		$facts = ( new WordPressDisplayContextResolver() )->resolve();

		self::assertSame( '/', $facts['path'] );
		self::assertFalse( $facts['isAuthenticated'] );
		self::assertNull( $facts['postType'] );
		self::assertSame( array(), $facts['roleMatches'] );
		self::assertNull( $facts['wooArea'] );
	}

	/** A product-named post type is not treated as WooCommerce when Woo has not initialized. */
	public function test_product_post_type_without_woocommerce_context_remains_unclassified(): void {
		$_SERVER['REQUEST_URI'] = '/product/lamp/';

		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'get_post_type' )->justReturn( 'product' );

		$facts = ( new WordPressDisplayContextResolver() )->resolve();

		self::assertNull( $facts['wooArea'] );
	}

	/** Site and Woo presentation facts use bounded deterministic tokens and site-local time. */
	public function test_resolve_projects_site_locale_direction_time_and_product_area(): void {
		$_SERVER['REQUEST_URI'] = '/product/lamp/';

		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'get_post_type' )->justReturn( 'product' );
		Functions\when( 'get_locale' )->justReturn( 'ur_PK' );
		Functions\when( 'is_rtl' )->justReturn( true );
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'current_datetime' )->justReturn(
			new DateTimeImmutable( '2026-09-13 23:45:00', new DateTimeZone( 'Asia/Karachi' ) )
		);

		$facts = ( new WordPressDisplayContextResolver() )->resolve();

		self::assertSame( 'product', $facts['wooArea'] );
		self::assertSame( 'ur-pk', $facts['siteLocale'] );
		self::assertSame( 'rtl', $facts['siteDirection'] );
		self::assertSame( 0, $facts['siteWeekday'] );
		self::assertSame( 1425, $facts['siteMinuteOfDay'] );
	}
}
