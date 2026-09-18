<?php
/**
 * Generic OpenAI-compatible direct provider adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\OpenAiCompatible;

use JsonException;
use InvalidArgumentException;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\ResolvedCredential;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\EmbeddingUsage;
use WpRagAiChatbot\Providers\EmbeddingVector;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\Http\HttpRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\HttpTransportException;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\ModelCatalogProvider;
use WpRagAiChatbot\Providers\ModelInfo;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderHealth;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Providers\Usage;
use WpRagAiChatbot\Providers\ProviderIds;

// phpcs:disable WordPress.Security.EscapeOutput -- ProviderException metadata is sanitized/internal and is never rendered directly.
/**
 * Implements fixed-endpoint chat generation and model discovery for compatible APIs.
 */
final class OpenAiCompatibleChatProvider implements GenerationProvider, ModelCatalogProvider, EmbeddingProvider {
	/**
	 * Create an OpenAI-compatible provider around fixed endpoints.
	 *
	 * @param string             $provider_id Stable provider identifier.
	 * @param string             $generation_url Fixed chat-completions endpoint.
	 * @param string             $models_url Fixed model-catalog endpoint.
	 * @param CredentialResolver $credentials Direct-provider credential resolver.
	 * @param ProviderHttpClient $http Provider HTTP policy client.
	 * @param SecretRedactor     $redactor Provider diagnostic redactor.
	 * @param string|null        $embedding_url Optional fixed embedding endpoint.
	 */
	public function __construct(
		private readonly string $provider_id,
		private readonly string $generation_url,
		private readonly string $models_url,
		private readonly CredentialResolver $credentials,
		private readonly ProviderHttpClient $http,
		private readonly SecretRedactor $redactor,
		private readonly ?string $embedding_url = null
	) {
	}

	/**
	 * Return the stable provider identifier.
	 */
	public function provider_id(): string {
		return $this->provider_id;
	}

	/**
	 * Compatible direct-provider support is available on supported WordPress runtimes.
	 */
	public function available(): bool {
		return true;
	}

	/**
	 * Return local configuration health without issuing a provider request.
	 */
	public function health(): ProviderHealth {
		$status = null === $this->credentials->resolve( $this->provider_id )
			? ProviderHealthStatus::UNCONFIGURED
			: ProviderHealthStatus::CONFIGURED;

		return new ProviderHealth( $this->provider_id, $status );
	}

	/**
	 * Generate one normalized chat-completions response.
	 *
	 * @param GenerationRequest $request Normalized generation request.
	 * @throws ProviderException When credentials, transport, HTTP status, or payload are invalid.
	 */
	public function generate( GenerationRequest $request ): GenerationResult {
		$credential = $this->required_credential();
		$messages   = array();

		if ( null !== $request->instructions && '' !== trim( $request->instructions ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $request->instructions,
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => $request->input,
		);

		$body = array(
			'model'    => $this->normalize_model_id( $request->model_id ),
			'messages' => $messages,
		);
		if ( null !== $request->max_output_tokens ) {
			$body['max_tokens'] = $request->max_output_tokens;
		}

		list( $authorization, $known_secrets ) = $this->credential_material( $credential );
		$http_request                          = new HttpRequest(
			$this->provider_id,
			'POST',
			$this->generation_url,
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
				$this->provider_id,
				$exception->getMessage()
			);
		}

		$this->assert_success_status( $response, $known_secrets );
		$data = $this->decode_success_payload( $response->body );
		$text = $this->output_text( $data );
		if ( '' === trim( $text ) ) {
			throw $this->malformed_response();
		}

		$model_id = isset( $data['model'] ) && is_string( $data['model'] ) && '' !== trim( $data['model'] )
			? $data['model']
			: $request->model_id;

		return new GenerationResult(
			$this->provider_id,
			$model_id,
			$text,
			$this->generation_status( $this->finish_reason( $data ) ),
			$this->usage( $data['usage'] ?? null ),
			$this->request_id( $response, $known_secrets )
		);
	}

	/**
	 * Discover and normalize models through the bounded retry client.
	 *
	 * @return ModelInfo[]
	 * @throws ProviderException When credentials, transport, HTTP status, or payload are invalid.
	 */
	public function models(): array {
		$credential = $this->required_credential();

		list( $authorization, $known_secrets ) = $this->credential_material( $credential );

		$request = new HttpRequest(
			$this->provider_id,
			'GET',
			$this->models_url,
			array( 'Authorization' => $authorization ),
			null,
			10,
			0
		);

		try {
			$response = $this->http->discovery( $request );
		} catch ( HttpTransportException $exception ) {
			throw new ProviderException(
				$exception->error_code,
				$this->provider_id,
				$exception->getMessage()
			);
		}

		$this->assert_success_status( $response, $known_secrets );
		$data = $this->decode_success_payload( $response->body );
		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			throw $this->malformed_response();
		}

		$models = array();
		foreach ( $data['data'] as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['id'] ) || ! is_string( $item['id'] ) || '' === trim( $item['id'] ) ) {
				throw $this->malformed_response();
			}

			$display_name = isset( $item['name'] ) && is_string( $item['name'] ) && '' !== trim( $item['name'] )
				? $item['name']
				: $item['id'];
			$models[]     = new ModelInfo(
				$this->provider_id,
				$this->normalize_model_id( $item['id'] ),
				$display_name
			);
		}

		return $models;
	}

	/**
	 * Embed one normalized batch through the optional compatible embedding endpoint.
	 *
	 * @param EmbeddingRequest $request Normalized embedding request.
	 * @throws ProviderException When embeddings are unsupported or the request/response is invalid.
	 */
	public function embed( EmbeddingRequest $request ): EmbeddingResult {
		if ( null === $this->embedding_url ) {
			throw new ProviderException(
				ProviderErrorCode::UNSUPPORTED_CAPABILITY,
				$this->provider_id,
				'Embedding is not supported by this provider.'
			);
		}

		$credential = $this->required_credential();
		$body       = array(
			'model' => $request->model,
			'input' => ProviderIds::GEMINI_DIRECT === $this->provider_id && 1 === count( $request->inputs )
				? $request->inputs[0]
				: $request->inputs,
		);
		if ( null !== $request->dimensions ) {
			$body['dimensions'] = $request->dimensions;
		}

		list( $authorization, $known_secrets ) = $this->credential_material( $credential );
		$http_request                          = new HttpRequest(
			$this->provider_id,
			'POST',
			$this->embedding_url,
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
				$this->provider_id,
				$exception->getMessage()
			);
		}

		$this->assert_success_status( $response, $known_secrets );
		$data     = $this->decode_success_payload( $response->body );
		$model_id = isset( $data['model'] ) && is_string( $data['model'] ) && '' !== trim( $data['model'] )
			? $data['model']
			: $request->model;

		return new EmbeddingResult(
			$this->provider_id,
			$model_id,
			$this->embedding_vectors( $data['data'] ?? null ),
			$this->embedding_usage( $data['usage'] ?? null )
		);
	}

	/**
	 * Require a configured direct-provider credential.
	 *
	 * @throws ProviderException When the configured provider has no credential.
	 */
	private function required_credential(): ResolvedCredential {
		$credential = $this->credentials->resolve( $this->provider_id );
		if ( null === $credential ) {
			throw new ProviderException(
				ProviderErrorCode::CONFIGURATION,
				$this->provider_id,
				$this->provider_id . ' is not configured.'
			);
		}

		return $credential;
	}

	/**
	 * Convert provider resource names to the model IDs accepted by OpenAI-compatible endpoints.
	 *
	 * Gemini's model catalog returns resource names such as models/gemini-2.5-flash,
	 * while its compatibility endpoint expects the bare model ID.
	 *
	 * @param string $model_id Provider model identifier.
	 */
	private function normalize_model_id( string $model_id ): string {
		if ( ProviderIds::GEMINI_DIRECT === $this->provider_id && str_starts_with( $model_id, 'models/' ) ) {
			return substr( $model_id, 7 );
		}

		return $model_id;
	}

	/**
	 * Build the authorization header and redaction list at the Secret boundary.
	 *
	 * @param ResolvedCredential $credential Resolved provider credential.
	 * @return array{0:string,1:string[]}
	 */
	private function credential_material( ResolvedCredential $credential ): array {
		$authorization = '';
		$known_secrets = array();
		$credential->secret->with_value(
			static function ( string $plaintext ) use ( &$authorization, &$known_secrets ): void {
				$authorization   = 'Bearer ' . $plaintext;
				$known_secrets[] = $plaintext;
			}
		);

		return array( $authorization, $known_secrets );
	}

	/**
	 * Require a successful HTTP status or raise a sanitized provider failure.
	 *
	 * @param HttpResponse $response Provider HTTP response.
	 * @param string[]     $known_secrets Plaintext values that must be redacted.
	 * @throws ProviderException When the provider returns a non-success HTTP status.
	 */
	private function assert_success_status( HttpResponse $response, array $known_secrets ): void {
		if ( $response->status >= 200 && $response->status < 300 ) {
			return;
		}

		$error_code = match ( $response->status ) {
			401 => ProviderErrorCode::AUTHENTICATION,
			403 => ProviderErrorCode::AUTHORIZATION,
			429 => ProviderErrorCode::RATE_LIMIT,
			default => $response->status >= 500 && $response->status <= 599
				? ProviderErrorCode::UPSTREAM_SERVER
				: ProviderErrorCode::UNKNOWN,
		};
		$message = $this->redactor->sanitize_body( $response->body, $known_secrets );
		if ( '' === trim( $message ) ) {
			$message = 'Provider request failed.';
		}

		throw new ProviderException(
			$error_code,
			$this->provider_id,
			$message,
			$this->request_id( $response, $known_secrets )
		);
	}

	/**
	 * Decode one successful provider JSON object.
	 *
	 * @param string $body Raw provider response body.
	 * @return array<string, mixed>
	 * @throws ProviderException When the response body is not a JSON object.
	 */
	private function decode_success_payload( string $body ): array {
		try {
			$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw $this->malformed_response();
		}

		if ( ! is_array( $data ) ) {
			throw $this->malformed_response();
		}

		return $data;
	}

	/**
	 * Return explicit first-choice message content.
	 *
	 * @param array<string, mixed> $data Decoded provider response.
	 */
	private function output_text( array $data ): string {
		if ( ! isset( $data['choices'][0]['message']['content'] ) || ! is_string( $data['choices'][0]['message']['content'] ) ) {
			return '';
		}

		return $data['choices'][0]['message']['content'];
	}

	/**
	 * Return explicit first-choice finish reason when present.
	 *
	 * @param array<string, mixed> $data Decoded provider response.
	 */
	private function finish_reason( array $data ): mixed {
		return $data['choices'][0]['finish_reason'] ?? null;
	}

	/**
	 * Normalize explicit chat-completions finish reasons.
	 *
	 * @param mixed $finish_reason Provider finish reason.
	 */
	private function generation_status( mixed $finish_reason ): GenerationStatus {
		if ( is_string( $finish_reason ) ) {
			$finish_reason = strtolower( trim( $finish_reason ) );
		}

		return match ( $finish_reason ) {
			'stop' => GenerationStatus::COMPLETED,
			'length' => GenerationStatus::INCOMPLETE,
			default => GenerationStatus::UNKNOWN,
		};
	}

	/**
	 * Normalize explicit integer token counts.
	 *
	 * @param mixed $usage Provider usage payload.
	 */
	private function usage( mixed $usage ): Usage {
		if ( ! is_array( $usage ) ) {
			return new Usage();
		}

		return new Usage(
			$this->non_negative_integer( $usage['prompt_tokens'] ?? null ),
			$this->non_negative_integer( $usage['completion_tokens'] ?? null ),
			$this->non_negative_integer( $usage['total_tokens'] ?? null )
		);
	}

	/**
	 * Normalize an embedding vector list while preserving provider order.
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
		foreach ( array_values( $data ) as $position => $item ) {
			if (
				! is_array( $item )
				|| ! isset( $item['embedding'] )
				|| ! is_array( $item['embedding'] )
			) {
				throw $this->malformed_response();
			}
			$index = $item['index'] ?? $position;
			if ( ! is_int( $index ) ) {
				throw $this->malformed_response();
			}

			try {
				$vectors[] = new EmbeddingVector( $index, $item['embedding'] );
			} catch ( InvalidArgumentException ) {
				throw $this->malformed_response();
			}
		}

		return $vectors;
	}

	/**
	 * Normalize explicit input-token usage.
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

	/**
	 * Return only non-negative integer token values.
	 *
	 * @param mixed $value Provider token value.
	 */
	private function non_negative_integer( mixed $value ): ?int {
		return is_int( $value ) && $value >= 0 ? $value : null;
	}

	/**
	 * Return a safe scalar x-request-id header when supplied.
	 *
	 * @param HttpResponse $response Provider HTTP response.
	 * @param string[]     $known_secrets Plaintext values that must not appear in diagnostics.
	 */
	private function request_id( HttpResponse $response, array $known_secrets ): ?string {
		foreach ( $response->headers as $name => $value ) {
			if ( 'x-request-id' !== strtolower( $name ) || ! is_scalar( $value ) ) {
				continue;
			}

			$request_id = trim( (string) $value );
			if ( '' === $request_id ) {
				return null;
			}

			return $this->redactor->sanitize( $request_id, $known_secrets ) === $request_id ? $request_id : null;
		}

		return null;
	}

	/**
	 * Create the constant malformed-response failure.
	 */
	private function malformed_response(): ProviderException {
		return new ProviderException(
			ProviderErrorCode::MALFORMED_RESPONSE,
			$this->provider_id,
			'Provider returned a malformed response.'
		);
	}
}
// phpcs:enable WordPress.Security.EscapeOutput
