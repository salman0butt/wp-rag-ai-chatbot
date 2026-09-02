<?php
/**
 * WordPress schema version store tests.
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
use WpRagAiChatbot\Database\WordPressSchemaVersionStore;

/**
 * Verifies schema version persistence through WordPress options.
 */
final class WordPressSchemaVersionStoreTest extends TestCase {
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
	 * Missing option reads as schema version zero.
	 */
	public function test_current_defaults_to_zero(): void {
		self::assertTrue( class_exists( WordPressSchemaVersionStore::class ), 'WordPressSchemaVersionStore must exist before version persistence behavior can pass.' );

		Functions\expect( 'get_option' )->once()->with( DatabaseSchema::VERSION_OPTION, 0 )->andReturn( false );

		self::assertSame( 0, ( new WordPressSchemaVersionStore() )->current() );
	}

	/**
	 * Stored scalar versions are normalized to integers.
	 */
	public function test_current_casts_stored_version_to_integer(): void {
		self::assertTrue( class_exists( WordPressSchemaVersionStore::class ), 'WordPressSchemaVersionStore must exist before version persistence behavior can pass.' );

		Functions\expect( 'get_option' )->once()->with( DatabaseSchema::VERSION_OPTION, 0 )->andReturn( '2' );

		self::assertSame( 2, ( new WordPressSchemaVersionStore() )->current() );
	}

	/**
	 * Saving the current version avoids an unnecessary option write.
	 */
	public function test_save_is_noop_when_version_is_unchanged(): void {
		self::assertTrue( class_exists( WordPressSchemaVersionStore::class ), 'WordPressSchemaVersionStore must exist before version persistence behavior can pass.' );

		Functions\expect( 'get_option' )->once()->with( DatabaseSchema::VERSION_OPTION, 0 )->andReturn( 2 );
		Functions\expect( 'update_option' )->never();

		( new WordPressSchemaVersionStore() )->save( 2 );
		self::addToAssertionCount( 1 );
	}

	/**
	 * A changed version is stored as a non-autoloaded option.
	 */
	public function test_save_persists_changed_version_without_autoload(): void {
		self::assertTrue( class_exists( WordPressSchemaVersionStore::class ), 'WordPressSchemaVersionStore must exist before version persistence behavior can pass.' );

		Functions\expect( 'get_option' )->once()->with( DatabaseSchema::VERSION_OPTION, 0 )->andReturn( 1 );
		Functions\expect( 'update_option' )->once()->with( DatabaseSchema::VERSION_OPTION, 2, false )->andReturn( true );

		( new WordPressSchemaVersionStore() )->save( 2 );
		self::addToAssertionCount( 1 );
	}

	/**
	 * WordPress false-return writes are accepted when a follow-up read proves success.
	 */
	public function test_save_accepts_false_write_result_when_value_matches_afterward(): void {
		self::assertTrue( class_exists( WordPressSchemaVersionStore::class ), 'WordPressSchemaVersionStore must exist before version persistence behavior can pass.' );

		Functions\expect( 'get_option' )->twice()->with( DatabaseSchema::VERSION_OPTION, 0 )->andReturn( 1, 2 );
		Functions\expect( 'update_option' )->once()->with( DatabaseSchema::VERSION_OPTION, 2, false )->andReturn( false );

		( new WordPressSchemaVersionStore() )->save( 2 );
		self::addToAssertionCount( 1 );
	}

	/**
	 * A failed write that remains stale is surfaced as a database error.
	 */
	public function test_save_throws_when_version_cannot_be_persisted(): void {
		self::assertTrue( class_exists( WordPressSchemaVersionStore::class ), 'WordPressSchemaVersionStore must exist before version persistence behavior can pass.' );

		Functions\expect( 'get_option' )->twice()->with( DatabaseSchema::VERSION_OPTION, 0 )->andReturn( 1 );
		Functions\expect( 'update_option' )->once()->with( DatabaseSchema::VERSION_OPTION, 2, false )->andReturn( false );

		$this->expectException( DatabaseException::class );
		( new WordPressSchemaVersionStore() )->save( 2 );
	}
}
