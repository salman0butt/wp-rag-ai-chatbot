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
	 * Prompt-safe selected evidence in final context order.
	 *
	 * @var list<array{id: string, content: string}>
	 */
	private array $prompt_evidence;

	/**
	 * Create the registry from validated citations and prompt-safe evidence.
	 *
	 * @param array $citations Ordered citations.
	 * @param array $prompt_evidence Prompt-safe selected evidence.
	 * @phpstan-param list<Citation> $citations
	 * @phpstan-param list<array{id: string, content: string}> $prompt_evidence
	 */
	private function __construct( array $citations, array $prompt_evidence ) {
		$citations_by_id = array();
		foreach ( $citations as $citation ) {
			$citations_by_id[ $citation->id ] = $citation;
		}

		$this->citations       = $citations;
		$this->citations_by_id = $citations_by_id;
		$this->prompt_evidence = $prompt_evidence;
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

		$citations       = array();
		$prompt_evidence = array();
		foreach ( array_values( $retrieval_candidates ) as $index => $candidate ) {
			if ( ! $candidate instanceof RetrievalCandidate ) {
				throw new InvalidArgumentException( 'Citation registry candidate is invalid.' );
			}

			$id          = 'C' . ( $index + 1 );
			$citations[] = new Citation(
				$id,
				$candidate->chunk_id,
				$candidate->document_id,
				$candidate->source_id
			);
			$prompt_evidence[] = array(
				'id'      => $id,
				'content' => $candidate->content,
			);
		}

		return new self( $citations, $prompt_evidence );
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
	 * Return only the selected fields needed for prompt construction.
	 *
	 * Raw retrieval metadata, visibility, scores, and authorization state are intentionally excluded.
	 *
	 * @return list<array{id: string, content: string}>
	 */
	public function prompt_evidence(): array {
		return $this->prompt_evidence;
	}

	/**
	 * Resolve one request-local citation identifier.
	 *
	 * @param string $id Request-local citation identifier.
	 */
	public function get( string $id ): ?Citation {
		return $this->citations_by_id[ $id ] ?? null;
	}
}
