<?php
/**
 * Retrieval query preprocessing.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

use InvalidArgumentException;

/**
 * Produces deterministic bounded query data before provider/store calls.
 */
final class QueryPreprocessor {
	/**
	 * Create one query preprocessor.
	 *
	 * @param RetrievalConfig $config Retrieval hard bounds.
	 */
	public function __construct( private readonly RetrievalConfig $config ) {
	}

	/**
	 * Normalize one untrusted user query.
	 *
	 * @param string $query Untrusted query text.
	 * @throws InvalidArgumentException When the query is empty, invalid, or exceeds bounds.
	 */
	public function preprocess( string $query ): RetrievalQuery {
		if ( 1 !== preg_match( '//u', $query ) ) {
			throw new InvalidArgumentException( 'Retrieval query must be valid UTF-8.' );
		}

		$normalized = preg_replace( '/\s+/u', ' ', trim( $query ) );
		if ( ! is_string( $normalized ) || '' === $normalized ) {
			throw new InvalidArgumentException( 'Retrieval query must not be empty.' );
		}
		if ( strlen( $normalized ) > $this->config->max_query_bytes ) {
			throw new InvalidArgumentException( 'Retrieval query exceeds the byte limit.' );
		}

		$matched = preg_match_all( '/[\p{L}\p{N}_\.\/:\-]+/u', $normalized, $matches );
		if ( false === $matched ) {
			throw new InvalidArgumentException( 'Retrieval query could not be tokenized.' );
		}

		/**
		 * Ordered matched query terms.
		 *
		 * @var list<string> $terms
		 */
		$terms = $matches[0];
		if ( count( $terms ) > $this->config->max_query_tokens ) {
			throw new InvalidArgumentException( 'Retrieval query exceeds the token limit.' );
		}

		$terms = array_map(
			static function ( string $term ): string {
				return function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
			},
			$terms
		);

		return new RetrievalQuery( $normalized, $terms );
	}
}
