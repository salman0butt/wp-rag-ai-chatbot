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
			static fn ( string $url, int $component = -1 ): mixed => parse_url( $url, $component )
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
	}
}
