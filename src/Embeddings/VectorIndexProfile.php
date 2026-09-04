<?php
/**
 * Vector index compatibility profile.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Embeddings;

/**
 * Immutable index identity including embedding profile and distance metric.
 */
final class VectorIndexProfile {
	/**
	 * @param EmbeddingProfile $embedding Embedding-generation profile.
	 * @param DistanceMetric   $distance Vector distance metric.
	 */
	public function __construct(
		public readonly EmbeddingProfile $embedding,
		public readonly DistanceMetric $distance
	) {
	}

	/**
	 * Return the versioned deterministic compatibility fingerprint.
	 */
	public function fingerprint(): string {
		$payload = implode(
			"\n",
			array(
				'v1',
				'provider=' . $this->embedding->provider_id,
				'model=' . $this->embedding->model_id,
				'dimensions=' . $this->embedding->dimensions,
				'normalization=' . $this->embedding->normalization->value,
				'distance=' . $this->distance->value,
			)
		);

		return hash( 'sha256', $payload );
	}
}
