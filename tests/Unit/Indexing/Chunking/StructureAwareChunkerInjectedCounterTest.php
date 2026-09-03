<?php
/**
 * Structure-aware chunking tests for injected token counters.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Indexing\Chunking;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Indexing\Chunking\ChunkingConfig;
use WpRagAiChatbot\Indexing\Chunking\StructureAwareChunker;
use WpRagAiChatbot\Indexing\Chunking\TokenCounter;

/**
 * Verifies overlap respects the injected token-budget contract.
 */
final class StructureAwareChunkerInjectedCounterTest extends TestCase {
	/**
	 * Overlap shrinks to the actual remaining budget reported by an injected counter.
	 */
	public function test_overlap_respects_injected_counter_budget_units(): void {
		$counter     = new class() implements TokenCounter {
			/**
			 * Count each lexical unit as two budget units.
			 *
			 * @param string $text Text to count.
			 * @throws RuntimeException When the fixture is invalid UTF-8.
			 */
			public function count( string $text ): int {
				$matched = preg_match_all( '/[\p{L}\p{N}]+|[^\s\p{L}\p{N}]/u', $text );
				if ( false === $matched ) {
					throw new RuntimeException( 'Invalid UTF-8 test fixture.' );
				}

				return $matched * 2;
			}
		};
		$chunker     = new StructureAwareChunker(
			$counter,
			new ChunkingConfig( 32, 4, 'm07-v1', null )
		);
		$new_content = implode( ' ', array_fill( 0, 14, 'new' ) );
		$content     = "# Same\n\nalpha beta gamma delta\n\n" . $new_content;
		$chunks      = $chunker->chunks( $this->document( $content ) );

		self::assertCount( 2, $chunks );
		self::assertSame( 32, $chunks[1]->tokenCount );
		self::assertStringStartsWith( 'gamma delta ', $chunks[1]->content );
		self::assertStringEndsWith( $new_content, $chunks[1]->content );
	}

	/**
	 * Create one canonical document fixture.
	 *
	 * @param string $content Canonical content.
	 */
	private function document( string $content ): DocumentRecord {
		return new DocumentRecord(
			null,
			'doc-key',
			7,
			'external-1',
			'post',
			'Title',
			'https://example.test/doc',
			$content,
			array(),
			'v1',
			DocumentHasher::hash( array( 'content' => $content ) ),
			'en',
			'private',
			new DateTimeImmutable( '2026-09-03T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-09-03T00:00:00+00:00' )
		);
	}
}
