<?php
/**
 * OpenAI direct provider adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\OpenAI;

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
 * Implements fixed-endpoint OpenAI Responses generation and model discovery.
 */
final class OpenAiProvider implements GenerationProvider, ModelCatalogProvider {
	private const RESPONSES_URL = 'https://api.openai.com/v1/responses';
	private const MODELS_URL    = 'https://api.openai.com/v1/models';

	/**
	 * Create the direct OpenAI adapter.
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
		return ProviderIds::OPENAI_DIRECT;
	}

	/**
	 * Direct OpenAI support is available on the supported WordPress runtime.
	 */
	public function available(): bool {
		return true;
	}

	/**
	 * Return local configuration health without issuing a provider request.
	 */
	public function health(): ProviderHealth {
		$status = null === $this->credentials->resolve( ProviderIds::OPENAI_DIRECT )
			? ProviderHealthStatus::UNCONFIGURED
			: ProviderHealthStatus::CONFIGURED;

		return new ProviderHealth( ProviderIds::OPENAI_DIRECT, $status );
	}

	/**
	 * Generate one normalized text response with exactly one provider request.
	 *
	 * @param GenerationRequest $request Normalized generation request.
	 * @throws ProviderException When credentials, transport, HTTP status, or payload are invalid.
	 */
	public function generate( GenerationRequest $request ): GenerationResult {
		$credential = $this->required_credential();
		$body       = array(
			'model' => $request->model_id,
			'input' => $request->input,
		);

		if ( null !== $request->instructions && '' !== trim( $request->instructions ) ) {
			$body['instructions'] = $request->instructions;
		}
		if ( null !== $request->max_output_tokens ) {
			$body['max_output_tokens'] = $request->max_output_tokens;
		}

		list( $authorization, $known_secrets ) = $this->credential_material( $credential );

		$http_request = new HttpRequest(
			ProviderIds::OPENAI_DIRECT,
			'POST',
			self::RESPONSES_URL,
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
				ProviderIds::OPENAI_DIRECT,
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
			ProviderIds::OPENAI_DIRECT,
			$model_id,
			$text,
			$this->generation_status( $data['status'] ?? null ),
			$this->usage( $data['usage'] ?? null ),
			$this->request_id( $response, $known_secrets )
		);
	}

	/**
	 * Discover and normalize OpenAI models through the bounded retry client.
	 *
	 * @return ModelInfo[]
	 * @throws ProviderException When credentials, transport, HTTP status, or payload are invalid.
	 */
	public function models(): array {
		$credential = $this->required_credential();

		list( $authorization, $known_secrets ) = $this->credential_material( $credential );

		$request = new HttpRequest(
			ProviderIds::OPENAI_DIRECT,
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
				ProviderIds::OPENAI_DIRECT,
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

			$metadata = array();
			foreach ( array( 'created', 'owned_by' ) as $metadata_key ) {
				if ( array_key_exists( $metadata_key, $item ) && is_scalar( $item[ $metadata_key ] ) ) {
					$metadata[ $metadata_key ] = $item[ $metadata_key ];
				}
			}

			$models[] = new ModelInfo(
				ProviderIds::OPENAI_DIRECT,
				$item['id'],
				$item['id'],
				array(),
				array(),
				array(),
				null,
				$metadata
			);
		}

		return $models;
	}

	/**
	 * Require a configured OpenAI credential without exposing plaintext.
	 *
	 * @throws ProviderException When OpenAI Direct is not configured.
	 */
	private function required_credential(): ResolvedCredential {
		$credential = $this->credentials->resolve( ProviderIds::OPENAI_DIRECT );
		if ( null === $credential ) {
			throw new ProviderException(
				ProviderErrorCode::CONFIGURATION,
				ProviderIds::OPENAI_DIRECT,
				'OpenAI Direct is not configured.'
			);
		}

		return $credential;
	}

	/**
	 * Build the authorization header and redaction list at the explicit Secret boundary.
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
			$message = 'OpenAI provider request failed.';
		}

		throw new ProviderException(
			$error_code,
			ProviderIds::OPENAI_DIRECT,
			$message,
			$this->request_id( $response, $known_secrets )
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
	 * Concatenate explicit Responses output_text entries in provider order.
	 *
	 * @param array<string, mixed> $data Decoded OpenAI response.
	 */
	private function output_text( array $data ): string {
		if ( ! isset( $data['output'] ) || ! is_array( $data['output'] ) ) {
			return '';
		}

		$text = '';
		foreach ( $data['output'] as $output ) {
			if ( ! is_array( $output ) || ! isset( $output['content'] ) || ! is_array( $output['content'] ) ) {
				continue;
			}
			foreach ( $output['content'] as $content ) {
				if (
					is_array( $content )
					&& isset( $content['type'], $content['text'] )
					&& 'output_text' === $content['type']
					&& is_string( $content['text'] )
				) {
					$text .= $content['text'];
				}
			}
		}

		return $text;
	}

	/**
	 * Normalize only explicit OpenAI status values.
	 *
	 * @param mixed $status Provider status value.
	 */
	private function generation_status( mixed $status ): GenerationStatus {
		return match ( $status ) {
			'completed' => GenerationStatus::COMPLETED,
			'incomplete' => GenerationStatus::INCOMPLETE,
			'failed' => GenerationStatus::FAILED,
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
			$this->non_negative_integer( $usage['input_tokens'] ?? null ),
			$this->non_negative_integer( $usage['output_tokens'] ?? null ),
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
			ProviderIds::OPENAI_DIRECT,
			'OpenAI returned a malformed response.'
		);
	}
}
// phpcs:enable WordPress.Security.EscapeOutput