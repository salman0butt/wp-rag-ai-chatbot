<?php
/**
 * Optional WordPress AI Client provider adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\WordPressAi;

use Throwable;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderHealth;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Providers\Usage;

// phpcs:disable WordPress.Security.EscapeOutput -- ProviderException metadata is sanitized/internal and is never rendered directly.
/**
 * Uses only public WordPress AI Client APIs when the optional runtime is available.
 */
final class WordPressAiClientProvider implements GenerationProvider {
	/**
	 * Create the optional WordPress AI Client adapter.
	 *
	 * @param SecretRedactor $redactor Provider diagnostic redactor.
	 */
	public function __construct( private readonly SecretRedactor $redactor ) {
	}

	/**
	 * Return the stable provider identifier.
	 */
	public function provider_id(): string {
		return ProviderIds::WORDPRESS_AI_CLIENT;
	}

	/**
	 * Report whether the public WordPress AI Client runtime is available.
	 */
	public function available(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		if ( function_exists( 'wp_supports_ai' ) && false === wp_supports_ai() ) {
			return false;
		}

		return true;
	}

	/**
	 * Return local feature-detection health without issuing a generation request.
	 */
	public function health(): ProviderHealth {
		return new ProviderHealth(
			ProviderIds::WORDPRESS_AI_CLIENT,
			$this->available() ? ProviderHealthStatus::CONFIGURED : ProviderHealthStatus::UNAVAILABLE
		);
	}

	/**
	 * Generate one normalized result through documented WordPress AI Client APIs.
	 *
	 * @param GenerationRequest $request Normalized generation request.
	 * @throws ProviderException When the optional API is unavailable or returns an invalid result.
	 */
	public function generate( GenerationRequest $request ): GenerationResult {
		if ( ! $this->available() ) {
			throw new ProviderException(
				ProviderErrorCode::UNSUPPORTED_CAPABILITY,
				ProviderIds::WORDPRESS_AI_CLIENT,
				'WordPress AI Client is unavailable on this site.'
			);
		}

		try {
			$builder = wp_ai_client_prompt( $request->input );
			if ( null !== $request->instructions && '' !== trim( $request->instructions ) ) {
				$builder->using_system_instruction( $request->instructions );
			}
			if ( null !== $request->max_output_tokens ) {
				$builder->using_max_tokens( $request->max_output_tokens );
			}
			$builder->using_model_preference( $request->model_id );
			$result = $builder->generate_text_result();
		} catch ( Throwable $exception ) {
			throw new ProviderException(
				ProviderErrorCode::UNKNOWN,
				ProviderIds::WORDPRESS_AI_CLIENT,
				$this->redactor->sanitize( $exception->getMessage() )
			);
		}

		if ( is_wp_error( $result ) ) {
			throw $this->wordpress_error( $result );
		}

		try {
			$text = $result->toText();
			$id   = $result->getId();
			$data = $this->result_data( $result->toArray() );
		} catch ( Throwable ) {
			throw $this->malformed_response();
		}

		if ( '' === trim( $text ) ) {
			throw $this->malformed_response();
		}

		$model_id = $request->model_id;
		if (
			isset( $data['modelMetadata'] )
			&& is_array( $data['modelMetadata'] )
			&& isset( $data['modelMetadata']['id'] )
			&& is_string( $data['modelMetadata']['id'] )
			&& '' !== trim( $data['modelMetadata']['id'] )
		) {
			$model_id = $data['modelMetadata']['id'];
		}

		return new GenerationResult(
			ProviderIds::WORDPRESS_AI_CLIENT,
			$model_id,
			$text,
			$this->generation_status( $this->finish_reason( $data ) ),
			$this->usage( $data['tokenUsage'] ?? null ),
			$this->request_id( $id )
		);
	}

	/**
	 * Widen documented result metadata after validating the public array boundary.
	 *
	 * This keeps runtime normalization defensive when optional metadata is absent,
	 * while avoiding assumptions derived only from current WordPress stubs.
	 *
	 * @param mixed $data Public WordPress result metadata.
	 * @return array<string, mixed>
	 * @throws ProviderException When result metadata is not an array.
	 */
	private function result_data( mixed $data ): array {
		if ( ! is_array( $data ) ) {
			throw $this->malformed_response();
		}

		return $data;
	}

	/**
	 * Normalize a public WordPress error without inspecting connector credentials.
	 *
	 * @param \WP_Error $error Public WordPress error object.
	 */
	private function wordpress_error( \WP_Error $error ): ProviderException {
		$message = $this->redactor->sanitize( $error->get_error_message() );
		if ( '' === trim( $message ) ) {
			$message = 'WordPress AI Client request failed.';
		}

		$request_id = null;
		$error_data = $error->get_error_data();
		if ( is_array( $error_data ) && isset( $error_data['request_id'] ) && is_scalar( $error_data['request_id'] ) ) {
			$request_id = trim( (string) $error_data['request_id'] );
			$request_id = '' === $request_id ? null : $request_id;
		}

		return new ProviderException(
			ProviderErrorCode::UNKNOWN,
			ProviderIds::WORDPRESS_AI_CLIENT,
			$message,
			$request_id
		);
	}

	/**
	 * Normalize explicit token usage from public WordPress result data.
	 *
	 * @param mixed $usage Public token-usage payload.
	 */
	private function usage( mixed $usage ): Usage {
		if ( ! is_array( $usage ) ) {
			return new Usage();
		}

		return new Usage(
			$this->non_negative_integer( $usage['promptTokens'] ?? null ),
			$this->non_negative_integer( $usage['completionTokens'] ?? null ),
			$this->non_negative_integer( $usage['totalTokens'] ?? null )
		);
	}

	/**
	 * Return explicit first-candidate finish reason when present.
	 *
	 * @param array<string, mixed> $data Public WordPress result data.
	 */
	private function finish_reason( array $data ): mixed {
		if (
			! isset( $data['candidates'] )
			|| ! is_array( $data['candidates'] )
			|| ! isset( $data['candidates'][0] )
			|| ! is_array( $data['candidates'][0] )
		) {
			return null;
		}

		return $data['candidates'][0]['finishReason'] ?? null;
	}

	/**
	 * Normalize only documented finish-reason values.
	 *
	 * @param mixed $finish_reason Public finish reason.
	 */
	private function generation_status( mixed $finish_reason ): GenerationStatus {
		return match ( $finish_reason ) {
			'stop' => GenerationStatus::COMPLETED,
			'length', 'max_tokens' => GenerationStatus::INCOMPLETE,
			default => GenerationStatus::UNKNOWN,
		};
	}

	/**
	 * Return only non-negative integer token values.
	 *
	 * @param mixed $value Public token value.
	 */
	private function non_negative_integer( mixed $value ): ?int {
		return is_int( $value ) && $value >= 0 ? $value : null;
	}

	/**
	 * Normalize the public result identifier when non-empty.
	 *
	 * @param mixed $id Public result identifier.
	 */
	private function request_id( mixed $id ): ?string {
		if ( ! is_scalar( $id ) ) {
			return null;
		}

		$request_id = trim( (string) $id );
		return '' === $request_id ? null : $request_id;
	}

	/**
	 * Create a constant malformed-response failure.
	 */
	private function malformed_response(): ProviderException {
		return new ProviderException(
			ProviderErrorCode::MALFORMED_RESPONSE,
			ProviderIds::WORDPRESS_AI_CLIENT,
			'WordPress AI Client returned a malformed response.'
		);
	}
}
// phpcs:enable WordPress.Security.EscapeOutput
