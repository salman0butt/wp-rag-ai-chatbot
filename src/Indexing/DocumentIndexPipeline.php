<?php
/**
 * Pure source-to-index planning pipeline.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing;

use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Chunking\StructureAwareChunker;
use WpRagAiChatbot\Indexing\Dedup\ChunkDeduplicator;
use WpRagAiChatbot\Indexing\Normalization\ContentNormalizer;
use WpRagAiChatbot\Indexing\Planning\IncrementalIndexPlanner;

/**
 * Composes deterministic normalization, chunking, deduplication, and planning.
 */
final class DocumentIndexPipeline {
	/**
	 * Create the pure M07 composition service.
	 */
	public function __construct(
		private ContentNormalizer $normalizer,
		private StructureAwareChunker $chunker,
		private ChunkDeduplicator $deduplicator,
		private IncrementalIndexPlanner $planner
	) {
	}

	/**
	 * Build current chunk evidence and the minimal incremental plan.
	 *
	 * @param DocumentRecord          $document Canonical source document.
	 * @param array<int, ChunkRecord> $previousChunks Previously indexed canonical chunks.
	 */
	public function plan( DocumentRecord $document, array $previousChunks = array() ): DocumentIndexResult {
		$normalized_content  = $this->normalizer::normalize( $document->content );
		$normalized_document = $this->normalizedDocument( $document, $normalized_content );
		$chunks              = $this->chunker->chunks( $normalized_document );
		$deduplicated        = $this->deduplicator->deduplicate( $chunks );
		$index_plan          = $this->planner->plan( $previousChunks, $deduplicated );

		return new DocumentIndexResult(
			$normalized_content,
			$chunks,
			$deduplicated->canonicalChunks,
			$deduplicated->duplicateAliases,
			$index_plan
		);
	}

	/**
	 * Return the original immutable document when normalization is already stable.
	 */
	private function normalizedDocument( DocumentRecord $document, string $normalizedContent ): DocumentRecord {
		if ( $normalizedContent === $document->content ) {
			return $document;
		}

		return new DocumentRecord(
			$document->id,
			$document->documentKey,
			$document->sourceId,
			$document->externalId,
			$document->documentType,
			$document->title,
			$document->canonicalUrl,
			$normalizedContent,
			$document->metadata,
			$document->sourceVersion,
			DocumentHasher::hash( array( 'content' => $normalizedContent ) ),
			$document->language,
			$document->visibility,
			$document->createdAt,
			$document->updatedAt
		);
	}
}
