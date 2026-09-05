<?php
/**
 * Retrieval candidate contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

use InvalidArgumentException;

/**
 * Immutable grounded chunk candidate with explicit lineage and channel evidence.
 */
final readonly class RetrievalCandidate {
	/**
	 * Create one retrieval candidate.
	 *
	 * @param string $chunk_id Stable chunk identifier.
	 * @param string $document_id Stable document identifier.
	 * @param int    $source_id Stable source identifier.
	 * @param string $content Untrusted retrieved chunk content.
	 * @param string|null $language Optional normalized language.
	 * @param string $visibility Trusted visibility classification.
	 * @param array  $channel_evidence Channel ranking evidence.
	 * @param float  $fused_score Deterministic fused score.
	 * @phpstan-param list<ChannelEvidence> $channel_evidence
	 * @throws InvalidArgumentException When lineage or numeric values are invalid.
	 */
	public function __construct(
		public string $chunk_id,
		public string $document_id,
		public int $source_id,
		public string $content,
		public ?string $language,
		public string $visibility,
		public array $channel_evidence,
		public float $fused_score
	) {
		if (
			'' === trim( $chunk_id ) ||
			'' === trim( $document_id ) ||
			$source_id < 1 ||
			'' === trim( $content ) ||
			'' === trim( $visibility ) ||
			! is_finite( $fused_score ) ||
			$fused_score < 0.0
		) {
			throw new InvalidArgumentException( 'Retrieval candidate is invalid.' );
		}

		foreach ( $channel_evidence as $evidence ) {
			if ( ! $evidence instanceof ChannelEvidence ) {
				throw new InvalidArgumentException( 'Retrieval candidate evidence is invalid.' );
			}
		}
	}
}
