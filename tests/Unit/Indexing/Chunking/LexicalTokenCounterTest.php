<?php
/**
 * Lexical token counter contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Indexing\Chunking;

use DomainException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Indexing\Chunking\LexicalTokenCounter;
use WpRagAiChatbot\Indexing\Chunking\TokenCounter;

/**
 * Verifies deterministic provider-independent lexical token counting.
 */
final class LexicalTokenCounterTest extends TestCase {
	/**
	 * Empty and whitespace-only text contain no lexical units.
	 */
	public function test_counts_empty_and_whitespace_only_text_as_zero(): void {
		$counter = $this->counter();

		self::assertSame( 0, $counter->count( '' ) );
		self::assertSame( 0, $counter->count( " \t\n" ) );
	}

	/**
	 * Unicode letter/number runs count as one unit each.
	 */
	public function test_counts_unicode_letter_and_number_runs_deterministically(): void {
		$counter = $this->counter();

		self::assertSame( 2, $counter->count( 'Hello world' ) );
		self::assertSame( 2, $counter->count( 'café 123' ) );
		self::assertSame( 2, $counter->count( '你好 世界' ) );
	}

	/**
	 * Non-whitespace punctuation and symbols count individually.
	 */
	public function test_counts_punctuation_and_symbols_as_individual_units(): void {
		$counter = $this->counter();

		self::assertSame( 4, $counter->count( 'Hello, world!' ) );
		self::assertSame( 3, $counter->count( 'A + B' ) );
	}

	/**
	 * Invalid UTF-8 must fail closed rather than return an unstable count.
	 */
	public function test_invalid_utf8_fails_closed(): void {
		$counter = $this->counter();

		$this->expectException( DomainException::class );
		$counter->count( "\xC3\x28" );
	}

	/**
	 * Build the production counter while preserving assertion-style RED before it exists.
	 */
	private function counter(): TokenCounter {
		if ( ! interface_exists( TokenCounter::class ) || ! class_exists( LexicalTokenCounter::class ) ) {
			self::fail( 'TokenCounter/LexicalTokenCounter contracts do not exist yet.' );
		}

		return new LexicalTokenCounter();
	}
}
