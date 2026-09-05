<?php
/**
 * Retrieval query preprocessing tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\QueryPreprocessor;
use WpRagAiChatbot\Retrieval\RetrievalConfig;

/**
 * Defines the M10 query normalization and abuse-bound contract.
 */
final class QueryPreprocessorTest extends TestCase {
	/**
	 * Whitespace is normalized while exact-friendly identifiers remain intact.
	 */
	public function test_preprocessor_normalizes_whitespace_and_preserves_identifier_tokens(): void {
		$preprocessor = new QueryPreprocessor( new RetrievalConfig() );

		$query = $preprocessor->preprocess( "  Find\tSKU-42/A  and\nERR_CONNECTION_RESET model.x:7  " );

		self::assertSame( 'Find SKU-42/A and ERR_CONNECTION_RESET model.x:7', $query->normalized );
		self::assertContains( 'sku-42/a', $query->lexical_terms );
		self::assertContains( 'err_connection_reset', $query->lexical_terms );
		self::assertContains( 'model.x:7', $query->lexical_terms );
	}

	/**
	 * Empty normalized queries are rejected before retrieval work begins.
	 */
	public function test_preprocessor_rejects_empty_query(): void {
		$preprocessor = new QueryPreprocessor( new RetrievalConfig() );

		$this->expectException( InvalidArgumentException::class );
		$preprocessor->preprocess( " \t\n " );
	}

	/**
	 * Query byte bounds prevent oversized provider or store requests.
	 */
	public function test_preprocessor_rejects_query_over_byte_limit(): void {
		$config       = new RetrievalConfig( max_query_bytes: 8 );
		$preprocessor = new QueryPreprocessor( $config );

		$this->expectException( InvalidArgumentException::class );
		$preprocessor->preprocess( '123456789' );
	}

	/**
	 * Query token bounds are enforced after deterministic tokenization.
	 */
	public function test_preprocessor_rejects_query_over_token_limit(): void {
		$config       = new RetrievalConfig( max_query_tokens: 2 );
		$preprocessor = new QueryPreprocessor( $config );

		$this->expectException( InvalidArgumentException::class );
		$preprocessor->preprocess( 'one two three' );
	}

	/**
	 * Retrieval configuration refuses zero or negative execution limits.
	 */
	public function test_config_rejects_unbounded_or_non_positive_limits(): void {
		$this->expectException( InvalidArgumentException::class );
		new RetrievalConfig( semantic_top_k: 0 );
	}
}
