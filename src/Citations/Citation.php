<?php
/**
 * Citation value object.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Citations;

use InvalidArgumentException;

/**
 * Immutable request-local citation backed by trusted retrieval lineage.
 */
final readonly class Citation {
	/**
	 * Create one citation.
	 *
	 * @param string      $id Request-local citation identifier.
	 * @param string      $chunk_id Canonical chunk identifier.
	 * @param string      $document_id Canonical document identifier.
	 * @param int         $source_id Canonical source identifier.
	 * @param string|null $title Optional canonical display title.
	 * @param string|null $canonical_url Optional canonical display URL.
	 * @throws InvalidArgumentException When lineage is invalid.
	 */
	public function __construct(
		public string $id,
		public string $chunk_id,
		public string $document_id,
		public int $source_id,
		public ?string $title = null,
		public ?string $canonical_url = null
	) {
		if (
			1 !== preg_match( '/^C[1-9][0-9]*$/', $id ) ||
			'' === trim( $chunk_id ) ||
			'' === trim( $document_id ) ||
			$source_id < 1 ||
			( null !== $title && '' === trim( $title ) ) ||
			( null !== $canonical_url && '' === trim( $canonical_url ) )
		) {
			throw new InvalidArgumentException( 'Citation is invalid.' );
		}
	}
}
