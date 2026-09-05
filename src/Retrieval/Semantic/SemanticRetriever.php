<?php
/**
 * M10 semantic retrieval adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Semantic;

use InvalidArgumentException;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\EmbeddingService;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Filter\VectorFilterMapper;
use WpRagAiChatbot\Retrieval\Fusion\RankedCandidate;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\RetrievalQuery;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorMatch;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorSearchStore;

/**
 * Embeds one normalized query, performs bounded portable vector search, and hydrates safe canonical candidates.
 */
final class SemanticRetriever {
	/** Maximum portable top-K accepted by the M08 vector request contract. */
	private const MAX_TOP_K = 100;

	/**
	 * Create the semantic retrieval adapter.
	 *
	 * @param EmbeddingService   $embedding_service Provider-neutral embedding service.
	 * @param EmbeddingProfile   $embedding_profile Selected query embedding profile.
	 * @param VectorCollection   $collection Selected vector collection/profile.
	 * @param VectorSearchStore  $search_store Portable vector search capability.
	 * @param VectorFilterMapper $filter_mapper Trusted-filter mapper.
	 * @param RetrievalConfig    $config Bounded M10 retrieval configuration.
	 * @throws InvalidArgumentException When semantic dependencies are incompatible or unbounded.
	 */
	public function __construct(
		private readonly EmbeddingService $embedding_service,
		private readonly EmbeddingProfile $embedding_profile,
		private readonly VectorCollection $collection,
		private readonly VectorSearchStore $search_store,
		private readonly VectorFilterMapper $filter_mapper,
		private readonly RetrievalConfig $config
	) {
		$collection_profile = $collection->profile->embedding;
		if (
			$embedding_profile->provider_id !== $embedding_service->provider_id() ||
			$collection_profile->provider_id !== $embedding_profile->provider_id ||
			$collection_profile->model_id !== $embedding_profile->model_id ||
			$collection_profile->dimensions !== $embedding_profile->dimensions ||
			$collection_profile->normalization !== $embedding_profile->normalization
		) {
			throw new InvalidArgumentException( 'Semantic embedding profile does not match the selected vector collection.' );
		}
		if ( $config->semantic_top_k > self::MAX_TOP_K ) {
			throw new InvalidArgumentException( 'Semantic top-K exceeds the portable vector-search limit.' );
		}
	}

	/**
	 * Retrieve bounded semantic candidates with trusted scope enforced before and after vector search.
	 *
	 * @param RetrievalQuery           $query Normalized retrieval query.
	 * @param SemanticRetrievalContext $context Trusted scope plus bounded canonical chunk resolver.
	 * @return list<RankedCandidate>
	 */
	public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
		$vector_filter = $this->filter_mapper->map( $context->filter );
		$result        = $this->embedding_service->embed(
			new EmbeddingRequest(
				$this->embedding_profile->model_id,
				array( $query->normalized ),
				$this->embedding_profile->dimensions
			)
		);
		$vector        = $result->vectors[0]->values;
		$search_result = $this->search_store->search(
			new VectorSearchRequest(
				$this->collection,
				$vector,
				$this->config->semantic_top_k,
				$this->collection->profile->fingerprint(),
				$vector_filter
			)
		);

		$candidates = array();
		foreach ( array_slice( $search_result->matches, 0, $this->config->semantic_top_k ) as $vector_match ) {
			$candidate = $this->candidate_from_match( $vector_match, $context );
			if ( null !== $candidate ) {
				$candidates[] = $candidate;
			}
		}

		usort(
			$candidates,
			static function ( RankedCandidate $left, RankedCandidate $right ): int {
				$score_order = $right->native_score <=> $left->native_score;
				return 0 !== $score_order ? $score_order : strcmp( $left->chunk_id, $right->chunk_id );
			}
		);

		return array_slice( $candidates, 0, $this->config->semantic_top_k );
	}

	/**
	 * Convert one portable vector match into a canonical candidate or drop it fail-closed.
	 *
	 * @param VectorMatch              $vector_match Portable vector match.
	 * @param SemanticRetrievalContext $context Trusted retrieval context.
	 */
	private function candidate_from_match( VectorMatch $vector_match, SemanticRetrievalContext $context ): ?RankedCandidate {
		$metadata     = $vector_match->metadata;
		$document_key = $metadata['document_key'] ?? null;
		$source_id    = $metadata['source_id'] ?? null;
		$visibility   = $metadata['visibility'] ?? null;
		$language     = $metadata['language'] ?? null;

		if (
			! is_string( $document_key ) || '' === trim( $document_key ) ||
			! is_int( $source_id ) || $source_id < 1 ||
			! is_string( $visibility ) || '' === trim( $visibility ) ||
			( null !== $language && ! is_string( $language ) )
		) {
			return null;
		}

		$record = $context->resolve_chunk( $vector_match->id );
		if ( null === $record || ! $this->lineage_matches( $record, $vector_match->id, $document_key, $source_id, $visibility, $language ) ) {
			return null;
		}
		if ( ! $this->trusted_scope_matches( $record, $context->filter ) ) {
			return null;
		}

		return new RankedCandidate(
			$record->chunk_key,
			$record->document_key,
			$record->source_id,
			$record->content,
			$record->language,
			$record->visibility,
			$vector_match->score
		);
	}

	/**
	 * Require vector metadata and canonical local projection to describe the same chunk lineage.
	 *
	 * @param ChunkSearchRecord $record Canonical local projection record.
	 * @param string            $chunk_id Vector match ID.
	 * @param string            $document_key Vector metadata document key.
	 * @param int               $source_id Vector metadata source ID.
	 * @param string            $visibility Vector metadata visibility.
	 * @param string|null       $language Vector metadata language.
	 */
	private function lineage_matches(
		ChunkSearchRecord $record,
		string $chunk_id,
		string $document_key,
		int $source_id,
		string $visibility,
		?string $language
	): bool {
		return $record->chunk_key === $chunk_id &&
			$record->document_key === $document_key &&
			$record->source_id === $source_id &&
			$record->visibility === $visibility &&
			$record->language === $language;
	}

	/**
	 * Recheck trusted scope against canonical local projection data after vector search.
	 *
	 * @param ChunkSearchRecord $record Canonical local projection record.
	 * @param RetrievalFilter   $filter Trusted server-side retrieval filter.
	 */
	private function trusted_scope_matches( ChunkSearchRecord $record, RetrievalFilter $filter ): bool {
		if ( null !== $filter->visibility && $record->visibility !== $filter->visibility ) {
			return false;
		}
		if ( null !== $filter->language && $record->language !== $filter->language ) {
			return false;
		}
		if ( array() !== $filter->source_ids && ! in_array( $record->source_id, $filter->source_ids, true ) ) {
			return false;
		}
		if ( array() !== $filter->document_keys && ! in_array( $record->document_key, $filter->document_keys, true ) ) {
			return false;
		}

		return true;
	}
}
