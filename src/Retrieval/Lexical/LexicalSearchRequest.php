<?php
/**
 * Bounded lexical candidate request.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

use InvalidArgumentException;

/**
 * Immutable query terms plus trusted scope and SQL candidate ceiling.
 */
final readonly class LexicalSearchRequest {
	private const MAX_TERMS      = 128;
	private const MAX_CANDIDATES = 1000;

	/**
	 * Validated normalized lexical terms.
	 *
	 * @var list<string>
	 */
	public array $terms;

	/**
	 * Create one bounded lexical candidate request.
	 *
	 * @param LexicalFilter $filter Trusted search scope.
	 * @param array         $terms Untrusted normalized lexical terms.
	 * @param int           $limit Maximum SQL candidates returned.
	 * @phpstan-param array<array-key, mixed> $terms
	 * @throws InvalidArgumentException When terms or limit exceed hard bounds.
	 */
	public function __construct(
		public LexicalFilter $filter,
		array $terms,
		public int $limit
	) {
		if ( ! array_is_list( $terms ) || array() === $terms || count( $terms ) > self::MAX_TERMS ) {
			throw new InvalidArgumentException( 'Lexical terms must be a bounded non-empty list.' );
		}
		$validated_terms = array();
		foreach ( $terms as $term ) {
			if ( ! is_string( $term ) || '' === $term || strlen( $term ) > 191 ) {
				throw new InvalidArgumentException( 'Lexical search term is invalid.' );
			}
			$validated_terms[] = $term;
		}
		if ( $limit < 1 || $limit > self::MAX_CANDIDATES ) {
			throw new InvalidArgumentException( 'Lexical candidate limit is invalid.' );
		}

		$this->terms = $validated_terms;
	}
}
