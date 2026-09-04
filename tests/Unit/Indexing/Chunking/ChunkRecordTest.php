<?php
/**
 * Immutable chunk record contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Indexing\Chunking;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;

/**
 * Verifies the immutable chunk output contract exists and remains readonly.
 */
final class ChunkRecordTest extends TestCase {
	/**
	 * Chunk records are immutable value objects.
	 */
	public function test_chunk_record_is_readonly(): void {
		if ( ! class_exists( ChunkRecord::class ) ) {
			self::fail( 'ChunkRecord class does not exist yet.' );
		}

		$reflection = new ReflectionClass( ChunkRecord::class );
		self::assertTrue( $reflection->isReadOnly() );
	}
}
