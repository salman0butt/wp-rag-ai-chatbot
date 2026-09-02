<?php
/**
 * OpenRouter direct provider adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\OpenRouter;

use JsonException;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\ResolvedCredential;
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
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Providers\Usage;

// phpcs:disable WordPress.Security.EscapeOutput -- ProviderException metadata is sanitized/internal and is never rendered directly.
/**
 * Implements fixed-endpoint OpenRouter chat generation and model discovery.
 */
final class OpenRouterProvider implements GenerationProvider, ModelCatalogProvider {
	private const GENERATION_URL = 'https://openrouter.ai/api/v1/chat/completions';
	private const MODELS_URL     = 'https://openrouter.ai/api/v1/models';

	/**
	 * Create the direct OpenRouter adapter.
	 *
	 * @param CredentialResolver $credentials Direct-provider credential resolver.
	 * @param ProviderHttpClient $http Provider HTTP policy client.
	 * @param SecretRedactor     $redactor Provider diagnostic redactor.
	 */
	public function __construct(
		private readonly CredentialResolver $credentials,
		private readonly ProviderHttpClient $http,
		private readonly SecretRedactor $redactor
	) {
	}

	/**
	 * Return the stable provider identifier.
	 */
	public function provider_id(): string {
		return ProviderIds::OPENROUTER_DIRECT;
	}

	/**
	 * Direct OpenRouter support is available on the supported WordPress runtime.
	 */
	public function available(): bool {
		return true;
	}

	/**
	 * Return local configuration health without issuing a provider request.
	 */
	public function health(): ProviderHealth {
		$status = null === $this->credentials->resolve( ProviderIds::OPENROUTER_DIRECT )
			? ProviderHealthStatus::UNCONFIGURED
			: ProviderHealthStatus::CONFIGURED;

		return new ProviderHealth( ProviderIds::OPENROUTER_DIRECT, $status );
	}

	/**
	 * Generate one normalized text response with exactly one provider request.
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
			'model'    => $request->model_id,
			'messages' => $messages,
		);
		if ( null !== $request->max_output_tokens ) {
			$body['max_tokens'] = $request->max_output_tokens;
		}

		list( $authorization, $known_secrets ) = $this->credential_material( $credential );

		$http_request = new HttpRequest(
			ProviderIds::OPENROUTER_DIRECT,
			'POST',
			self::GENERATION_URL,
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
		$data = $this->decode_success_payload( $response->body );
		$text = $this->output_text( $data );
		if ( '' === trim( $text ) ) {
			throw $this->malformed_response();
		}

		$model_id = isset( $data['model'] ) && is_string( $data['model'] ) && '' !== trim( $data['model'] )
			? $data['model']
			: $request->model_id;

		return new GenerationResult(
			ProviderIds::OPENROUTER_DIRECT,
			$model_id,
			$text,
			$this->generation_status( $this->finish_reason( $data ) ),
			$this->usage( $data['usage'] ?? null ),
			$this->success_request_id( $response, $data )
		);
	}

	/**
	 * Discover and normalize OpenRouter models through the bounded retry client.
	 *
	 * @return ModelInfo[]
	 * @throws ProviderException When credentials, transport, HTTP status, or payload are invalid.
	 */
	public function models(): array {
		$credential = $this->required_credential();

		list( $authorization, $known_secrets ) = $this->credential_material( $credential );

		$request = new HttpRequest(
			ProviderIds::OPENROUTER_DIRECT,
			'GET',
			self::MODELS_URL,
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
				ProviderIds::OPENROUTER_DIRECT,
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

			$display_name   = isset( $item['name'] ) && is_string( $item['name'] ) && '' !== trim( $item['name'] )
				? $item['name']
				: $item['id'];
			$context_window = isset( $item['context_length'] ) && is_int( $item['context_length'] ) && $item['context_length'] > 0
				? $item['context_length']
				: null;

			$architecture      = isset( $item['architecture'] ) && is_array( $item['architecture'] ) ? $item['architecture'] : array();
			$input_modalities  = $this->string_list( $architecture['input_modalities'] ?? null );
			$output_modalities = $this->string_list( $architecture['output_modalities'] ?? null );
			$capabilities      = $this->string_list( $item['supported_parameters'] ?? null );

			$models[] = new ModelInfo(
				ProviderIds::OPENROUTER_DIRECT,
				$item['id'],
				$display_name,
				$input_modalities,
				$output_modalities,
				$capabilities,
				$context_window,
				array()
			);
		}

		return $models;
	}

	/**
	 * Require a configured OpenRouter credential without exposing plaintext.
	 *
	 * @throws ProviderException When OpenRouter Direct is not configured.
	 */
	private function required_credential(): ResolvedCredential {
		$credential = $this->credentials->resolve( ProviderIds::OPENROUTER_DIRECT );
		if ( null === $credential ) {
			throw new ProviderException(
				ProviderErrorCode::CONFIGURATION,
				ProviderIds::OPENROUTER_DIRECT,
				'OpenRouter Direct is not configured.'
			);
		}

		return $credential;
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
	 * @throws ProviderException When the provider HTTP status is unsuccessful.
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
			$message = 'OpenRouter provider request failed.';
		}

		throw new ProviderException(
			$error_code,
			ProviderIds::OPENROUTER_DIRECT,
			$message,
			$this->header_request_id( $response )
		);
	}

	/**
	 * Decode a successful JSON body to an associative array.
	 *
	 * @param string $body Raw provider body.
	 * @return array<string, mixed>
	 * @throws ProviderException When JSON is invalid or not an object.
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
	 * @param array<string, mixed> $data Decoded OpenRouter response.
	 */
	private function output_text( array $data ): string {
		if ( ! isset( $data['choices'] ) || ! is_array( $data['choices'] ) || ! isset( $data['choices'][0] ) || ! is_array( $data['choices'][0] ) ) {
			return '';
		}

		$choice = $data['choices'][0];
		if ( ! isset( $choice['message'] ) || ! is_array( $choice['message'] ) || ! isset( $choice['message']['content'] ) || ! is_string( $choice['message']['content'] ) ) {
			return '';
		}

		return $choice['message']['content'];
	}

	/**
	 * Return explicit first-choice finish reason when present.
	 *
	 * @param array<string, mixed> $data Decoded OpenRouter response.
	 */
	private function finish_reason( array $data ): mixed {
		if ( ! isset( $data['choices'] ) || ! is_array( $data['choices'] ) || ! isset( $data['choices'][0] ) || ! is_array( $data['choices'][0] ) ) {
			return null;
		}

		return $data['choices'][0]['finish_reason'] ?? null;
	}

	/**
	 * Normalize only explicit OpenRouter finish reasons.
	 *
	 * @param mixed $finish_reason Provider finish reason.
	 */
	private function generation_status( mixed $finish_reason ): GenerationStatus {
		return match ( $finish_reason ) {
			'stop' => GenerationStatus::COMPLETED,
			'length' => GenerationStatus::INCOMPLETE,
			default => GenerationStatus::UNKNOWN,
		};
	}

	/**
	 * Normalize only explicit integer token counts.
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
	 * Return only non-negative integer token values.
	 *
	 * @param mixed $value Provider token value.
	 */
	private function non_negative_integer( mixed $value ): ?int {
		return is_int( $value ) && $value >= 0 ? $value : null;
	}

	/**
	 * Normalize a provider array into non-empty strings only.
	 *
	 * @param mixed $value Provider list value.
	 * @return string[]
	 */
	private function string_list( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$items = array();
		foreach ( $value as $item ) {
			if ( is_string( $item ) && '' !== trim( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Return x-request-id header or explicit top-level generation ID.
	 *
	 * @param HttpResponse         $response Provider HTTP response.
	 * @param array<string, mixed> $data Decoded successful response.
	 */
	private function success_request_id( HttpResponse $response, array $data ): ?string {
		$header_id = $this->header_request_id( $response );
		if ( null !== $header_id ) {
			return $header_id;
		}

		if ( ! isset( $data['id'] ) || ! is_scalar( $data['id'] ) ) {
			return null;
		}

		$request_id = trim( (string) $data['id'] );
		return '' === $request_id ? null : $request_id;
	}

	/**
	 * Return a safe scalar x-request-id header when supplied.
	 *
	 * @param HttpResponse $response Provider HTTP response.
	 */
	private function header_request_id( HttpResponse $response ): ?string {
		foreach ( $response->headers as $name => $value ) {
			if ( 'x-request-id' !== strtolower( $name ) || ! is_scalar( $value ) ) {
				continue;
			}

			$request_id = trim( (string) $value );
			return '' === $request_id ? null : $request_id;
		}

		return null;
	}

	/**
	 * Create the constant malformed-response failure.
	 */
	private function malformed_response(): ProviderException {
		return new ProviderException(
			ProviderErrorCode::MALFORMED_RESPONSE,
			ProviderIds::OPENROUTER_DIRECT,
			'OpenRouter returned a malformed response.'
		);
	}
}
// phpcs:enable WordPress.Security.EscapeOutput
