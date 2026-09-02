<?php
/**
 * Database uninstaller tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\DatabaseSchema;
use WpRagAiChatbot\Database\DatabaseUninstaller;
use WpRagAiChatbot\Tests\Support\Database\FakeWpdb;

/**
 * Verifies destructive uninstall failure handling.
 */
final class DatabaseUninstallerTest extends TestCase {
	/**
	 * Start WordPress function mocking and install the minimal wpdb runtime alias.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! class_exists( 'wpdb', false ) ) {
			class_alias( FakeWpdb::class, 'wpdb' );
		}
	}

	/**
	 * Remove globals and WordPress function mocks after each test.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * A failed destructive query must preserve uninstall state so cleanup can be retried.
	 */
	public function test_failed_drop_preserves_database_options_and_throws(): void {
		$wpdb             = new FakeWpdb( false );
		$GLOBALS['wpdb'] = $wpdb;

		Functions\expect( 'get_option' )->once()->with( DatabaseSchema::DELETE_DATA_OPTION, false )->andReturn( '1' );
		Functions\expect( 'delete_option' )->never();

		$this->expectException( DatabaseException::class );
		DatabaseUninstaller::run();
	}
}
