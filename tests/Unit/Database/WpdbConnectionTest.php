<?php
/**
 * WordPress database adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\WpdbConnection;
use WpRagAiChatbot\Tests\Support\Database\FakeWpdb;

/** Proves scalar database values retain their native type at the adapter boundary. */
final class WpdbConnectionTest extends TestCase {
	/** A numeric zero must not be converted to null. */
	public function test_get_var_preserves_numeric_zero(): void {
		if ( ! class_exists( 'wpdb', false ) ) {
			class_alias( FakeWpdb::class, 'wpdb' );
		}

		$wpdb                = new FakeWpdb( 1 );
		$wpdb->scalar_result = 0;
		$connection          = new WpdbConnection( $wpdb );

		self::assertSame( 0, $connection->get_var( 'SELECT 0' ) );
	}

	/** A WordPress string scalar containing zero must be normalized to an integer. */
	public function test_get_var_normalizes_numeric_string_zero(): void {
		if ( ! class_exists( 'wpdb', false ) ) {
			class_alias( FakeWpdb::class, 'wpdb' );
		}

		$wpdb                = new FakeWpdb( 1 );
		$wpdb->scalar_result = '0';
		$connection          = new WpdbConnection( $wpdb );

		self::assertSame( 0, $connection->get_var( 'SELECT 0' ) );
	}
}
