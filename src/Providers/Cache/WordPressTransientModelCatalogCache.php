<?php
/**
 * WordPress transient-backed model catalog cache.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Cache;

use InvalidArgumentException;
use WpRagAiChatbot\Providers\ModelInfo;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Stores normalized model catalogs in fixed provider transients.
 */
final class WordPressTransientModelCatalogCache implements ModelCatalogCache {
	private const TTL = 900;

	/**
	 * Return a cached normalized model catalog when present.
	 *
	 * @param string $provider_id Stable provider identifier.
	 * @return ModelInfo[]|null
	 */
	public function get( string $provider_id ): ?array {
		$key   = $this->transient_key( $provider_id );
		$value = get_transient( $key );

		if ( false === $value ) {
			return null;
		}

		if ( ! is_array( $value ) ) {
			delete_transient( $key );
			return null;
		}

		$models = array();
		foreach ( $value as $item ) {
			$model = $this->array_to_model( $provider_id, $item );
			if ( null === $model ) {
				delete_transient( $key );
				return null;
			}
			$models[] = $model;
		}

		return $models;
	}

	/**
	 * Persist a normalized model catalog for exactly 900 seconds.
	 *
	 * @param string      $provider_id Stable provider identifier.
	 * @param ModelInfo[] $models Normalized model catalog.
	 */
	public function put( string $provider_id, array $models ): void {
		$payload = array();
		foreach ( $models as $model ) {
			$payload[] = $this->model_to_array( $model );
		}

		set_transient( $this->transient_key( $provider_id ), $payload, self::TTL );
	}

	/**
	 * Remove the fixed provider transient.
	 *
	 * @param string $provider_id Stable provider identifier.
	 */
	public function invalidate( string $provider_id ): void {
		delete_transient( $this->transient_key( $provider_id ) );
	}

	/**
	 * Resolve the fixed transient key for a direct provider.
	 *
	 * @param string $provider_id Stable provider identifier.
	 */
	private function transient_key( string $provider_id ): string {
		return match ( $provider_id ) {
			ProviderIds::OPENAI_DIRECT => 'wp_rag_ai_models_openai_direct_v1',
			ProviderIds::OPENROUTER_DIRECT => 'wp_rag_ai_models_openrouter_direct_v1',
			default => throw new InvalidArgumentException( 'Unsupported provider ID for model catalog cache.' ),
		};
	}

	/**
	 * Serialize one normalized model without provider secrets.
	 *
	 * @param ModelInfo $model Normalized model.
	 * @return array<string, mixed>
	 */
	private function model_to_array( ModelInfo $model ): array {
		return array(
			'provider_id'       => $model->provider_id,
			'model_id'          => $model->model_id,
			'display_name'      => $model->display_name,
			'input_modalities'  => $model->input_modalities,
			'output_modalities' => $model->output_modalities,
			'capabilities'      => $model->capabilities,
			'context_window'    => $model->context_window,
			'provider_metadata' => $model->provider_metadata,
		);
	}

	/**
	 * Reconstruct one normalized model from cached data.
	 *
	 * @param string $provider_id Expected provider identifier.
	 * @param mixed  $item Cached model payload.
	 */
	private function array_to_model( string $provider_id, mixed $item ): ?ModelInfo {
		if ( ! is_array( $item ) ) {
			return null;
		}

		$required = array(
			'provider_id',
			'model_id',
			'display_name',
			'input_modalities',
			'output_modalities',
			'capabilities',
			'context_window',
			'provider_metadata',
		);
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $item ) ) {
				return null;
			}
		}

		if (
			! is_string( $item['provider_id'] )
			|| $provider_id !== $item['provider_id']
			|| ! is_string( $item['model_id'] )
			|| ! is_string( $item['display_name'] )
			|| ! is_array( $item['input_modalities'] )
			|| ! is_array( $item['output_modalities'] )
			|| ! is_array( $item['capabilities'] )
			|| ( null !== $item['context_window'] && ! is_int( $item['context_window'] ) )
			|| ! is_array( $item['provider_metadata'] )
		) {
			return null;
		}

		try {
			return new ModelInfo(
				$item['provider_id'],
				$item['model_id'],
				$item['display_name'],
				$item['input_modalities'],
				$item['output_modalities'],
				$item['capabilities'],
				$item['context_window'],
				$item['provider_metadata']
			);
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}
}
