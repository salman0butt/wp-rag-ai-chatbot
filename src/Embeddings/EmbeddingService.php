<?php
/**
 * Provider-neutral embedding orchestration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Embeddings;

use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\EmbeddingUsage;
use WpRagAiChatbot\Providers\EmbeddingVector;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;

/**
 * Splits embedding work into bounded batches and validates normalized responses.
 */
final class EmbeddingService {
	/**
	 * Create the embedding service.
	 *
	 * @param EmbeddingProvider    $provider Embedding-capable provider adapter.
	 * @param EmbeddingBatchConfig $batch_config Application-level batch bound.
	 */
	public function __construct(
		private readonly EmbeddingProvider $provider,
		private readonly EmbeddingBatchConfig $batch_config
	) {
	}

	/**
	 * Return the stable ID of the configured embedding provider.
	 */
	public function provider_id(): string {
		return $this->provider->provider_id();
	}

	/**
	 * Embed one ordered request through deterministic bounded batches.
	 *
	 * @param EmbeddingRequest $request Normalized embedding request.
	 * @throws ProviderException When a provider response is inconsistent or malformed.
	 */
	public function embed( EmbeddingRequest $request ): EmbeddingResult {
		$global_vectors = array();
		$offset         = 0;
		$dimension      = null;
		$model          = null;
		$usage_known    = true;
		$input_tokens   = 0;

		foreach ( array_chunk( $request->inputs, $this->batch_config->max_inputs_per_batch ) as $inputs ) {
			$batch_request = new EmbeddingRequest( $request->model, $inputs, $request->dimensions );
			$result        = $this->provider->embed( $batch_request );

			if ( $result->provider_id !== $this->provider->provider_id() ) {
				throw $this->malformed_response();
			}
			if ( null === $model ) {
				$model = $result->model;
			} elseif ( $model !== $result->model ) {
				throw $this->malformed_response();
			}

			$vectors = $this->validate_batch( $result, count( $inputs ), $request->dimensions, $dimension );
			if ( null === $dimension ) {
				$dimension = count( $vectors[0]->values );
			}

			foreach ( $vectors as $vector ) {
				$global_vectors[] = new EmbeddingVector( $offset + $vector->index, $vector->values );
			}
			$offset += count( $inputs );

			if ( ! $result->usage->known ) {
				$usage_known = false;
			} elseif ( $usage_known && null !== $result->usage->input_tokens ) {
				$input_tokens += $result->usage->input_tokens;
			}
		}

		return new EmbeddingResult(
			$this->provider->provider_id(),
			$model ?? $request->model,
			$global_vectors,
			$usage_known ? EmbeddingUsage::input_tokens( $input_tokens ) : EmbeddingUsage::unknown()
		);
	}

	/**
	 * Validate one batch and return vectors ordered by local input index.
	 *
	 * @param EmbeddingResult $result Provider batch result.
	 * @param int             $expected_count Expected vector count.
	 * @param int|null        $requested_dimensions Optional caller-requested dimensions.
	 * @param int|null        $expected_dimensions Dimensions established by prior batches.
	 * @return EmbeddingVector[]
	 * @throws ProviderException When vector count, indexes, or dimensions are inconsistent.
	 */
	private function validate_batch(
		EmbeddingResult $result,
		int $expected_count,
		?int $requested_dimensions,
		?int $expected_dimensions
	): array {
		if ( count( $result->vectors ) !== $expected_count ) {
			throw $this->malformed_response();
		}

		$ordered          = array_fill( 0, $expected_count, null );
		$batch_dimensions = $expected_dimensions;
		foreach ( $result->vectors as $vector ) {
			if ( $vector->index >= $expected_count || null !== $ordered[ $vector->index ] ) {
				throw $this->malformed_response();
			}

			$vector_dimensions = count( $vector->values );
			if ( null !== $requested_dimensions && $vector_dimensions !== $requested_dimensions ) {
				throw $this->malformed_response();
			}
			if ( null === $batch_dimensions ) {
				$batch_dimensions = $vector_dimensions;
			} elseif ( $vector_dimensions !== $batch_dimensions ) {
				throw $this->malformed_response();
			}

			$ordered[ $vector->index ] = $vector;
		}

		foreach ( $ordered as $vector ) {
			if ( ! $vector instanceof EmbeddingVector ) {
				throw $this->malformed_response();
			}
		}

		/**
		 * Ordered embedding vectors.
		 *
		 * @var EmbeddingVector[] $ordered
		 */
		return $ordered;
	}

	/**
	 * Create the constant malformed-response failure.
	 */
	private function malformed_response(): ProviderException {
		return new ProviderException(
			ProviderErrorCode::MALFORMED_RESPONSE,
			$this->provider->provider_id(),
			'Embedding provider returned a malformed response.'
		);
	}
}
