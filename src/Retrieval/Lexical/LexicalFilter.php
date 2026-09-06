<?php
/**
 * Trusted lexical search scope.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

use InvalidArgumentException;

/**
 * Immutable trusted filters applied before lexical candidates leave SQL.
 */
final readonly class LexicalFilter {
	/**
	 * Create trusted lexical scope.
	 *
	 * @param string      $collection_id Collection scope.
	 * @param string|null $document_key Optional document scope.
	 * @param int|null    $source_id Optional source scope.
	 * @param string|null $language Optional language scope.
	 * @param string|null $visibility Optional visibility scope.
	 * @throws InvalidArgumentException When any trusted scope value is invalid.
	 */
	public function __construct(
		public string $collection_id,
		public ?string $document_key = null,
		public ?int $source_id = null,
		public ?string $language = null,
		public ?string $visibility = null
	) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $collection_id ) ) {
			throw new InvalidArgumentException( 'Lexical collection ID is invalid.' );
		}
		if ( null !== $document_key && ( '' === $document_key || strlen( $document_key ) > 191 ) ) {
			throw new InvalidArgumentException( 'Lexical document filter is invalid.' );
		}
		if ( null !== $source_id && $source_id < 1 ) {
			throw new InvalidArgumentException( 'Lexical source filter is invalid.' );
		}
		if ( null !== $language && ( '' === $language || strlen( $language ) > 35 ) ) {
			throw new InvalidArgumentException( 'Lexical language filter is invalid.' );
		}
		if ( null !== $visibility && ( '' === $visibility || strlen( $visibility ) > 32 ) ) {
			throw new InvalidArgumentException( 'Lexical visibility filter is invalid.' );
		}
	}
}
