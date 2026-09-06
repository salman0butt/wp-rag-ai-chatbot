<?php
/**
 * Ranked retrieval candidate.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Fusion;

use InvalidArgumentException;

/**
 * Immutable candidate emitted by one native retrieval channel before fusion.
 */
final readonly class RankedCandidate {
	/**
	 * Create one pre-fusion candidate.
	 *
	 * @param string      $chunk_id Stable chunk identifier.
	 * @param string      $document_id Stable document identifier.
	 * @param int         $source_id Stable source identifier.
	 * @param string      $content Untrusted retrieved chunk content.
	 * @param string|null $language Optional normalized language.
	 * @param string      $visibility Trusted visibility classification.
	 * @param float       $native_score Native channel score used only for channel ranking.
	 * @throws InvalidArgumentException When candidate lineage or score is invalid.
	 */
	public function __construct(
		public string $chunk_id,
		public string $document_id,
		public int $source_id,
		public string $content,
		public ?string $language,
		public string $visibility,
		public float $native_score
	) {
		if (
			'' === trim( $chunk_id ) ||
			'' === trim( $document_id ) ||
			$source_id < 1 ||
			'' === trim( $content ) ||
			'' === trim( $visibility ) ||
			! is_finite( $native_score )
		) {
			throw new InvalidArgumentException( 'Ranked retrieval candidate is invalid.' );
		}
	}
}
