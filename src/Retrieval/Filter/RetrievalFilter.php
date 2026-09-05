<?php
/**
 * Trusted retrieval filter contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Filter;

use InvalidArgumentException;

/**
 * Immutable bounded filter values supplied only by trusted server-side policy.
 */
final readonly class RetrievalFilter {
	/** Maximum values accepted by one portable membership constraint. */
	private const MAX_MEMBERSHIP_VALUES = 32;

	/**
	 * Create trusted retrieval constraints.
	 *
	 * @param string|null $visibility Optional visibility classification.
	 * @param string|null $language Optional normalized language.
	 * @param array       $source_ids Optional allowed source IDs.
	 * @param array       $document_keys Optional allowed document keys.
	 * @param array       $mandatory Mandatory constraints not represented by the portable baseline.
	 * @phpstan-param list<int> $source_ids
	 * @phpstan-param list<string> $document_keys
	 * @phpstan-param array<string, scalar> $mandatory
	 * @throws InvalidArgumentException When trusted filter values are malformed or unbounded.
	 */
	public function __construct(
		public ?string $visibility = null,
		public ?string $language = null,
		public array $source_ids = array(),
		public array $document_keys = array(),
		public array $mandatory = array()
	) {
		if ( null !== $visibility && '' === trim( $visibility ) ) {
			throw new InvalidArgumentException( 'Retrieval visibility filter must not be blank.' );
		}
		if ( null !== $language && '' === trim( $language ) ) {
			throw new InvalidArgumentException( 'Retrieval language filter must not be blank.' );
		}
		if ( ! array_is_list( $source_ids ) || count( $source_ids ) > self::MAX_MEMBERSHIP_VALUES ) {
			throw new InvalidArgumentException( 'Retrieval source filter is invalid or unbounded.' );
		}
		foreach ( $source_ids as $source_id ) {
			if ( $source_id < 1 ) {
				throw new InvalidArgumentException( 'Retrieval source IDs must be positive.' );
			}
		}
		if ( ! array_is_list( $document_keys ) || count( $document_keys ) > self::MAX_MEMBERSHIP_VALUES ) {
			throw new InvalidArgumentException( 'Retrieval document filter is invalid or unbounded.' );
		}
		foreach ( $document_keys as $document_key ) {
			if ( '' === trim( $document_key ) ) {
				throw new InvalidArgumentException( 'Retrieval document keys must not be blank.' );
			}
		}
		foreach ( $mandatory as $key => $value ) {
			if ( '' === trim( $key ) || ! is_scalar( $value ) ) {
				throw new InvalidArgumentException( 'Mandatory retrieval filter is malformed.' );
			}
		}
	}
}
