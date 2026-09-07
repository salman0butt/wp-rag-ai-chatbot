<?php
/**
 * Model capability and onboarding readiness REST resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use OutOfBoundsException;
use Throwable;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Providers\ModelInfo;
use WpRagAiChatbot\Providers\ProviderConfigurationService;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderRegistry;

/**
 * Exposes model compatibility and server-derived onboarding state.
 */
final class ModelReadinessRestResource {
	/**
	 * Create the resource from existing provider and bot seams.
	 *
	 * @param ProviderRegistry             $registry Registered providers and catalogs.
	 * @param ProviderConfigurationService $configuration Non-secret provider configuration state.
	 * @param BotRepository                $bots Persisted bot configurations.
	 */
	public function __construct(
		private readonly ProviderRegistry $registry,
		private readonly ProviderConfigurationService $configuration,
		private readonly BotRepository $bots
	) {}

	/**
	 * Return compatible normalized models for one provider and purpose.
	 *
	 * @param string      $provider_id Stable provider identifier.
	 * @param string      $purpose Requested model purpose.
	 * @param string|null $capability Optional required capability.
	 * @return array<string,mixed>
	 */
	public function models( string $provider_id, string $purpose, ?string $capability = null ): array {
		if ( 'generation' !== $purpose ) {
			return $this->error( 'unsupported_capability', 'The requested model capability is not supported.' );
		}

		try {
			$provider = $this->registry->generation( $provider_id );
		} catch ( OutOfBoundsException ) {
			return $this->error( 'provider_unavailable', 'The requested provider is unavailable.' );
		}

		if ( ! $provider->available() ) {
			return $this->error( 'provider_unavailable', 'The requested provider is unavailable.' );
		}

		$descriptor = $this->configuration->describe( $provider_id );
		if ( ProviderHealthStatus::UNAVAILABLE === $descriptor->health->status ) {
			return $this->error( 'provider_unavailable', 'The requested provider is unavailable.' );
		}
		if ( ProviderHealthStatus::UNCONFIGURED === $descriptor->health->status ) {
			return $this->error( 'missing_credential', 'The requested provider is not configured.' );
		}

		$catalog = $this->registry->catalog( $provider_id );
		if ( null === $catalog ) {
			return $this->error( 'unsupported_capability', 'The requested model capability is not supported.' );
		}

		try {
			$models = $catalog->models();
		} catch ( Throwable ) {
			return $this->error( 'provider_unavailable', 'The requested provider could not supply its model catalog.' );
		}

		$normalized = array();
		foreach ( $models as $model ) {
			if ( ! $this->supports( $model, $provider_id, $purpose, $capability ) ) {
				continue;
			}
			$normalized[] = $this->normalize( $model );
		}

		return array(
			'provider_id' => $provider_id,
			'purpose'     => $purpose,
			'capability'  => $capability,
			'models'      => $normalized,
		);
	}

	/**
	 * Return onboarding progress reconstructed from persisted server state.
	 *
	 * @return array{ready:bool,next_step:string}
	 */
	public function readiness(): array {
		$compatible = array();

		foreach ( $this->registry->ids() as $provider_id ) {
			$response = $this->models( $provider_id, 'generation' );
			if ( isset( $response['error'] ) ) {
				continue;
			}

			foreach ( $response['models'] as $model ) {
				if ( isset( $model['model_id'] ) && is_string( $model['model_id'] ) ) {
					$compatible[ $provider_id ][ $model['model_id'] ] = true;
				}
			}
		}

		if ( array() === $compatible ) {
			return array(
				'ready'     => false,
				'next_step' => $this->has_configured_provider() ? 'model' : 'provider',
			);
		}

		$page_number = 1;
		$per_page    = 100;
		do {
			$page = $this->bots->list( $page_number, $per_page );
			foreach ( $page['items'] as $bot ) {
				if ( isset( $compatible[ $bot->provider_id ][ $bot->model_id ] ) ) {
					return array(
						'ready'     => true,
						'next_step' => 'complete',
					);
				}
			}

			++$page_number;
		} while ( ( $page_number - 1 ) * $per_page < $page['total'] );

		return array(
			'ready'     => false,
			'next_step' => 'first_bot',
		);
	}

	/**
	 * Determine whether any registered provider is locally configured and available.
	 */
	private function has_configured_provider(): bool {
		foreach ( $this->registry->ids() as $provider_id ) {
			try {
				$provider   = $this->registry->generation( $provider_id );
				$descriptor = $this->configuration->describe( $provider_id );
			} catch ( Throwable ) {
				continue;
			}

			if ( $provider->available() && ProviderHealthStatus::CONFIGURED === $descriptor->health->status ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Decide whether normalized provider metadata satisfies the requested purpose/capability.
	 *
	 * Empty capability metadata is treated as generation-compatible for existing M03 catalogs;
	 * explicit requested capabilities still require an explicit model declaration.
	 *
	 * @param ModelInfo   $model Provider-neutral model metadata.
	 * @param string      $provider_id Requested provider.
	 * @param string      $purpose Requested purpose.
	 * @param string|null $capability Optional requested capability.
	 */
	private function supports( ModelInfo $model, string $provider_id, string $purpose, ?string $capability ): bool {
		if ( $provider_id !== $model->provider_id ) {
			return false;
		}

		if ( array() !== $model->capabilities && ! in_array( $purpose, $model->capabilities, true ) ) {
			return false;
		}

		return null === $capability || in_array( $capability, $model->capabilities, true );
	}

	/**
	 * Serialize only stable, provider-neutral model metadata required by M12.
	 *
	 * @param ModelInfo $model Provider-neutral model metadata.
	 * @return array{provider_id:string,model_id:string,display_name:string,input_modalities:array<int,string>,output_modalities:array<int,string>,capabilities:array<int,string>,context_window:int|null}
	 */
	private function normalize( ModelInfo $model ): array {
		return array(
			'provider_id'       => $model->provider_id,
			'model_id'          => $model->model_id,
			'display_name'      => $model->display_name,
			'input_modalities'  => $model->input_modalities,
			'output_modalities' => $model->output_modalities,
			'capabilities'      => $model->capabilities,
			'context_window'    => $model->context_window,
		);
	}

	/**
	 * Return one stable safe resource error.
	 *
	 * @param string $code Stable machine-readable code.
	 * @param string $message Safe human-readable message.
	 * @return array{error:array{code:string,message:string}}
	 */
	private function error( string $code, string $message ): array {
		return array(
			'error' => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
