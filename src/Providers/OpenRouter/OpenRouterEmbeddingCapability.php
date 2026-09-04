<?php
/**
 * OpenRouter direct embedding capability.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\OpenRouter;

use InvalidArgumentException;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\EmbeddingUsage;
use WpRagAiChatbot\Providers\EmbeddingVector;
use WpRagAiChatbot\Providers\Http\HttpRequest;
use WpRagAiChatbot\Providers\Http\HttpTransportException;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderIds;

// phpcs:disable WordPress.Security.EscapeOutput -- ProviderException metadata is sanitized/internal and is never rendered directly.
/**
 * Adds fixed-endpoint, one-shot embeddings to the direct OpenRouter adapter.
 */
trait OpenRouterEmbeddingCapability {
	/**
	 * Embed one normalized batch with exactly one provider request.
	 *
	 * @param EmbeddingRequest $request Normalized embedding request.
	 * @throws ProviderException When credentials, transport, HTTP status, or payload are invalid.
	 */
	public function embed( EmbeddingRequest $request ): EmbeddingResult {
		$credential = $this->required_credential();
		$body       = array(
			'model' => $request->model,
			'input' => $request->inputs,
		);
		if ( null !== $request->dimensions ) {
			$body['dimensions'] = $request->dimensions;
		}

		list( $authorization, $known_secrets ) = $this->credential_material( $credential );
		$http_request                          = new HttpRequest(
			ProviderIds::OPENROUTER_DIRECT,
			'POST',
			'https://openrouter.ai/api/v1/embeddings',
			array(
				'Authorization' => $authorization,
				'Content-Type'  => 'application/json',
			),
			$body,
			45,
			0
		);

		try {
			$response = $this->http->generation( $http_request );
		} catch ( HttpTransportException $exception ) {
			throw new ProviderException(
				$exception->error_code,
				ProviderIds::OPENROUTER_DIRECT,
				$exception->getMessage()
			);
		}

		$this->assert_success_status( $response, $known_secrets );
		$data     = $this->decode_success_payload( $response->body );
		$model_id = isset( $data['model'] ) && is_string( $data['model'] ) && '' !== trim( $data['model'] )
			? $data['model']
			: $request->model;

		return new EmbeddingResult(
			ProviderIds::OPENROUTER_DIRECT,
			$model_id,
			$this->embedding_vectors( $data['data'] ?? null ),
			$this->embedding_usage( $data['usage'] ?? null )
		);
	}

	/**
	 * Normalize an embedding vector list while preserving provider indices.
	 *
	 * @param mixed $data Provider embedding data.
	 * @return EmbeddingVector[]
	 * @throws ProviderException When vector data is malformed.
	 */
	private function embedding_vectors( mixed $data ): array {
		if ( ! is_array( $data ) || array() === $data ) {
			throw $this->malformed_response();
		}

		$vectors = array();
		foreach ( $data as $item ) {
			if (
				! is_array( $item )
				|| ! isset( $item['index'], $item['embedding'] )
				|| ! is_int( $item['index'] )
				|| ! is_array( $item['embedding'] )
			) {
				throw $this->malformed_response();
			}

			try {
				$vectors[] = new EmbeddingVector( $item['index'], $item['embedding'] );
			} catch ( InvalidArgumentException ) {
				throw $this->malformed_response();
			}
		}

		return $vectors;
	}

	/**
	 * Normalize explicit input-token usage without inventing missing values.
	 *
	 * @param mixed $usage Provider usage payload.
	 */
	private function embedding_usage( mixed $usage ): EmbeddingUsage {
		if ( ! is_array( $usage ) ) {
			return EmbeddingUsage::unknown();
		}

		$tokens = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null;
		return is_int( $tokens ) && $tokens >= 0
			? EmbeddingUsage::input_tokens( $tokens )
			: EmbeddingUsage::unknown();
	}
}
