<?php
/**
 * Document hasher tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\DocumentHasher;

/**
 * Verifies canonical document hashing.
 */
final class DocumentHasherTest extends TestCase {
	/**
	 * Associative key order, including nested keys, must not alter a hash.
	 */
	public function test_hash_is_stable_when_associative_key_order_changes(): void {
		$this->requireHasher();

		$first  = array(
			'title'    => 'Example',
			'metadata' => array(
				'post_type' => 'page',
				'post_id'   => 42,
			),
			'tags'     => array( 'alpha', 'beta' ),
		);
		$second = array(
			'tags'     => array( 'alpha', 'beta' ),
			'metadata' => array(
				'post_id'   => 42,
				'post_type' => 'page',
			),
			'title'    => 'Example',
		);

		self::assertSame( DocumentHasher::hash( $first ), DocumentHasher::hash( $second ) );
	}

	/**
	 * List order carries semantic meaning and must remain significant.
	 */
	public function test_hash_changes_when_list_order_changes(): void {
		$this->requireHasher();

		self::assertNotSame(
			DocumentHasher::hash( array( 'tags' => array( 'alpha', 'beta' ) ) ),
			DocumentHasher::hash( array( 'tags' => array( 'beta', 'alpha' ) ) )
		);
	}

	/**
	 * Content changes must produce a different content identity.
	 */
	public function test_hash_changes_when_content_changes(): void {
		$this->requireHasher();

		self::assertNotSame(
			DocumentHasher::hash( array( 'content' => 'First' ) ),
			DocumentHasher::hash( array( 'content' => 'Second' ) )
		);
	}

	/**
	 * Hash output must satisfy the DocumentRecord lowercase SHA-256 invariant.
	 */
	public function test_hash_is_lowercase_sha256_hex(): void {
		$this->requireHasher();

		self::assertMatchesRegularExpression(
			'/^[a-f0-9]{64}$/',
			DocumentHasher::hash( array( 'content' => 'Unicode ✓ / path' ) )
		);
	}

	/**
	 * Fail as an assertion while the test-first production type does not exist.
	 */
	private function requireHasher(): void {
		if ( ! class_exists( DocumentHasher::class ) ) {
			self::fail( 'DocumentHasher class does not exist yet.' );
		}
	}
}
