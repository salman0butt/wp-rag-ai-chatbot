<?php
/**
 * Request-local citation registry.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Citations;

use InvalidArgumentException;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Assigns deterministic citation identifiers to final context candidates.
 */
final readonly class CitationRegistry {
	private const MAX_CITATIONS = 12;

	/**
	 * Ordered citations.
	 *
	 * @var list<Citation>
	 */
	private array $citations;

	/**
	 * Citations keyed by request-local identifier.
	 *
	 * @var array<string, Citation>
	 */
	private array $citations_by_id;

	/**
	 * Create the registry from validated citations.
	 *
	 * @param array $citations Ordered citations.
	 * @phpstan-param list<Citation> $citations
	 */
	private function __construct( array $citations ) {
		$this->citations       = $citations;
		$this->citations_by_id = array();

		foreach ( $citations as $citation ) {
			$this->citations_by_id[ $citation->id ] = $citation;
		}
	}

	/**
	 * Build citations in final context order.
	 *
	 * @param array $retrieval_candidates Final retrieval candidates.
	 * @phpstan-param array<array-key, mixed> $retrieval_candidates
	 * @throws InvalidArgumentException When the candidate list is invalid or too large.
	 */
	public static function from_candidates( array $retrieval_candidates ): self {
		if ( count( $retrieval_candidates ) > self::MAX_CITATIONS ) {
			throw new InvalidArgumentException( 'Citation registry exceeds the hard limit.' );
		}

		$citations = array();
		foreach ( array_values( $retrieval_candidates ) as $index => $candidate ) {
			if ( ! $candidate instanceof RetrievalCandidate ) {
				throw new InvalidArgumentException( 'Citation registry candidate is invalid.' );
			}

			$citations[] = new Citation(
				'C' . ( $index + 1 ),
				$candidate->chunk_id,
				$candidate->document_id,
				$candidate->source_id
			);
		}

		return new self( $citations );
	}

	/**
	 * Return citations in final context order.
	 *
	 * @return list<Citation>
	 */
	public function all(): array {
		return $this->citations;
	}

	/**
	 * Resolve one request-local citation identifier.
	 */
	public function get( string $id ): ?Citation {
		return $this->citations_by_id[ $id ] ?? null;
	}
}
