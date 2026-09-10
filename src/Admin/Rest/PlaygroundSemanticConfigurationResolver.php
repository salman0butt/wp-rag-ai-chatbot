<?php
/**
 * Persisted Playground semantic runtime configuration resolution.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use UnexpectedValueException;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;

/**
 * Resolves semantic runtime identity only from explicit persisted source configuration.
 */
final class PlaygroundSemanticConfigurationResolver {
	/**
	 * Resolve persisted semantic configuration for one closed Playground selection.
	 *
	 * @param PlaygroundConfiguration $configuration Resolved bot/source/collection selection.
	 * @throws UnexpectedValueException When required persisted semantic identity is absent or invalid.
	 */
	public function resolve( PlaygroundConfiguration $configuration ): PlaygroundSemanticConfiguration {
		$semantic = $configuration->retrieval->source->config['semantic_retrieval'] ?? null;
		if ( ! is_array( $semantic ) ) {
			throw new UnexpectedValueException( 'Persisted semantic retrieval configuration is missing.' );
		}

		$provider_id     = $semantic['embedding_provider_id'] ?? null;
		$model_id        = $semantic['embedding_model_id'] ?? null;
		$dimensions      = $semantic['dimensions'] ?? null;
		$normalization   = $semantic['normalization'] ?? null;
		$distance        = $semantic['distance'] ?? null;
		$vector_store_id = $semantic['vector_store_id'] ?? null;

		if (
			! is_string( $provider_id )
			|| '' === trim( $provider_id )
			|| ! is_string( $model_id )
			|| '' === trim( $model_id )
			|| ! is_int( $dimensions )
			|| $dimensions < 1
			|| ! is_string( $vector_store_id )
			|| '' === trim( $vector_store_id )
			|| ! is_string( $normalization )
			|| ! is_string( $distance )
		) {
			throw new UnexpectedValueException( 'Persisted semantic retrieval configuration is invalid.' );
		}

		$normalization_mode = NormalizationMode::tryFrom( $normalization );
		if ( null === $normalization_mode ) {
			throw new UnexpectedValueException( 'Persisted semantic retrieval normalization is invalid.' );
		}

		$distance_metric = DistanceMetric::tryFrom( $distance );
		if ( null === $distance_metric ) {
			throw new UnexpectedValueException( 'Persisted semantic retrieval distance is invalid.' );
		}

		return new PlaygroundSemanticConfiguration(
			new EmbeddingProfile(
				trim( $provider_id ),
				trim( $model_id ),
				$dimensions,
				$normalization_mode
			),
			$distance_metric,
			trim( $vector_store_id )
		);
	}
}
