<?php
/**
 * Bounded setup readiness REST resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use Throwable;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\BotRetrievalBindingRepository;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Providers\Cache\CachedModelCatalogProvider;
use WpRagAiChatbot\Providers\ProviderConfigurationService;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\ProviderRegistry;

// phpcs:disable WordPress.NamingConventions -- REST keys follow the existing snake_case contract.
/** Projects local provider, knowledge, index, and bot state without provider work. */
final class SetupReadinessRestResource {
	private const MAX_BOT_SCAN = 100;

	/**
	 * A readiness request scans at most one existing repository page. Larger inventories fail closed
	 * until an exact aggregate compatibility query exists; this avoids retaining all bots or binding N+1.
	 */

	/**
	 * Create the readiness resource.
	 *
	 * @param ProviderRegistry              $providers Registered provider capabilities.
	 * @param ProviderConfigurationService  $configuration Non-secret provider state.
	 * @param KnowledgeSourceRepository     $sources Persisted knowledge sources.
	 * @param BotRepository                 $bots Persisted bot configurations.
	 * @param BotRetrievalBindingRepository $bindings Persisted bot bindings.
	 * @param Connection                    $connection Local index persistence.
	 */
	public function __construct(
		private readonly ProviderRegistry $providers,
		private readonly ProviderConfigurationService $configuration,
		private readonly KnowledgeSourceRepository $sources,
		private readonly BotRepository $bots,
		private readonly BotRetrievalBindingRepository $bindings,
		private readonly Connection $connection
	) {
	}

	/**
	 * Return bounded server-derived setup state.
	 *
	 * @return array{ready:bool,next_step:string,configured_generation_provider:bool,configured_gemini_embedding:bool,model_available:bool,source_count:int,completed_index_present:bool,enabled_bot_count:int,bound_bot_present:bool,publishable_bot_present:bool,issue?:string}
	 */
	public function readiness(): array {
		$model_state             = $this->model_state();
		$bot_page                = $this->bots->list( 1, self::MAX_BOT_SCAN );
		$scan_complete           = $bot_page['total'] <= self::MAX_BOT_SCAN;
		$bots                    = $bot_page['items'];
		$compatible              = $model_state['compatible'];
		$legacy_ready            = $scan_complete && $this->has_model_ready_bot( $bots, $compatible );
		$source_count            = $this->sources->paginate( 1, self::MAX_BOT_SCAN )->total;
		$completed_index_present = $this->completed_index_present();
		$enabled_bot_count       = $this->enabled_bot_count();
		$bound_bot_present       = $this->has_any_binding();
		$bindings                = $scan_complete ? $this->bindings_for_bots( $bots ) : array();
		$publishable_bot_present = $scan_complete && $this->has_publishable_bot( $bots, $bindings, $compatible, $completed_index_present );

		$response = array(
			'ready'                          => $legacy_ready,
			'next_step'                      => $this->next_step( $model_state['configured_provider'], $model_state['model_available'], $legacy_ready ),
			'configured_generation_provider' => $model_state['configured_provider'],
			'configured_gemini_embedding'    => $this->configured_gemini_embedding(),
			'model_available'                => $model_state['model_available'],
			'source_count'                   => $source_count,
			'completed_index_present'        => $completed_index_present,
			'enabled_bot_count'              => $enabled_bot_count,
			'bound_bot_present'              => $bound_bot_present,
			'publishable_bot_present'        => $publishable_bot_present,
		);
		if ( null !== $model_state['issue'] ) {
			$response['issue'] = $model_state['issue'];
		}

		return $response;
	}

	/**
	 * Build local compatible model IDs and the legacy safe issue code without model discovery.
	 *
	 * @return array{configured_provider:bool,model_available:bool,compatible:array<string,array<string,bool>>,issue:string|null}
	 */
	private function model_state(): array {
		$compatible = array();
		$issues     = array();
		$configured = false;

		foreach ( $this->providers->ids() as $provider_id ) {
			try {
				$provider   = $this->providers->generation( $provider_id );
				$descriptor = $this->configuration->describe( $provider_id );
			} catch ( Throwable ) {
				$issues[] = 'provider_unavailable';
				continue;
			}

			if ( ! $provider->available() || ProviderHealthStatus::UNAVAILABLE === $descriptor->health->status ) {
				$issues[] = 'provider_unavailable';
				continue;
			}
			if ( ProviderHealthStatus::UNCONFIGURED === $descriptor->health->status ) {
				$issues[] = 'missing_credential';
				continue;
			}

			$configured = true;
			$catalog    = $this->providers->catalog( $provider_id );
			if ( ! $catalog instanceof CachedModelCatalogProvider ) {
				$issues[] = 'unsupported_capability';
				continue;
			}

			try {
				$models = $catalog->cached_models();
			} catch ( Throwable ) {
				$issues[] = 'provider_unavailable';
				continue;
			}
			if ( null === $models || array() === $models ) {
				$issues[] = 'unsupported_capability';
				continue;
			}

			foreach ( $models as $model ) {
				if ( ModelReadinessRestResource::supports_model( $model, $provider_id, 'generation' ) ) {
					$compatible[ $provider_id ][ $model->model_id ] = true;
				}
			}
		}

		$model_available = array() !== $compatible;
		return array(
			'configured_provider' => $configured,
			'model_available'     => $model_available,
			'compatible'          => $compatible,
			'issue'               => $model_available ? null : $this->readiness_issue( $issues ),
		);
	}

	/**
	 * Select the existing safe onboarding issue priority.
	 *
	 * @param array<int,string> $issues Observed safe issue codes.
	 */
	private function readiness_issue( array $issues ): ?string {
		foreach ( array( 'missing_credential', 'provider_unavailable', 'unsupported_capability' ) as $code ) {
			if ( in_array( $code, $issues, true ) ) {
				return $code;
			}
		}

		return null;
	}

	/** Determine whether the fixed Gemini embedding capability is locally configured. */
	private function configured_gemini_embedding(): bool {
		try {
			$embedding  = $this->providers->embedding( ProviderIds::GEMINI_DIRECT );
			$descriptor = $this->configuration->describe( ProviderIds::GEMINI_DIRECT );
		} catch ( Throwable ) {
			return false;
		}

		return null !== $embedding
			&& $embedding->available()
			&& ProviderHealthStatus::CONFIGURED === $descriptor->health->status;
	}

	/**
	 * Determine whether one bounded bot page uses a compatible local model.
	 *
	 * @param array<int,Bot>                   $bots Persisted bot page.
	 * @param array<string,array<string,bool>> $compatible Compatible model IDs.
	 */
	private function has_model_ready_bot( array $bots, array $compatible ): bool {
		foreach ( $bots as $bot ) {
			if ( isset( $compatible[ $bot->provider_id ][ $bot->model_id ] ) ) {
				return true;
			}
		}

		return false;
	}

	/** Return the exact aggregate enabled count without materializing bot rows. */
	private function enabled_bot_count(): int {
		try {
			return $this->bots->count_enabled();
		} catch ( Throwable ) {
			return 0;
		}
	}

	/** Return whether any complete binding exists without loading every bot. */
	private function has_any_binding(): bool {
		try {
			return $this->bindings->has_any();
		} catch ( Throwable ) {
			return false;
		}
	}

	/**
	 * Resolve the bounded page's bindings in one repository query.
	 *
	 * @param array<int,Bot> $bots Persisted bot page.
	 * @return array<string,BotRetrievalBinding> Bindings keyed by bot ID.
	 */
	private function bindings_for_bots( array $bots ): array {
		try {
			$ids = array_map( static fn ( Bot $bot ): \WpRagAiChatbot\Bots\BotId => $bot->id, $bots );
			return $this->bindings->find_for_bot_ids( $ids );
		} catch ( Throwable ) {
			return array();
		}
	}

	/**
	 * Determine whether one bounded bot page can be published.
	 *
	 * @param array<int,Bot>                    $bots Persisted bot page.
	 * @param array<string,BotRetrievalBinding> $bindings Valid bindings keyed by bot ID.
	 * @param array<string,array<string,bool>>  $compatible Compatible model IDs.
	 * @param bool                              $index_present Whether both projections are complete.
	 */
	private function has_publishable_bot( array $bots, array $bindings, array $compatible, bool $index_present ): bool {
		foreach ( $bots as $bot ) {
			$binding = $bindings[ $bot->id->value ] ?? null;
			if (
				$bot->enabled
				&& isset( $compatible[ $bot->provider_id ][ $bot->model_id ] )
				&& $binding instanceof BotRetrievalBinding
				&& $this->binding_is_publishable( $binding, $index_present )
			) {
				return true;
			}
		}

		return false;
	}

	/** Check the fixed local lexical and vector projections without touching a provider. */
	private function completed_index_present(): bool {
		if ( ! GuidedRetrievalReadiness::collection_ready( $this->connection ) ) {
			return false;
		}

		$tables = new TableNames( $this->connection->prefix() );
		if ( ! $this->connection->table_exists( $tables->chunk_search() ) ) {
			return false;
		}

		$lexical_count = (int) $this->connection->get_var(
			$this->connection->prepare(
				'SELECT COUNT(*) FROM %i WHERE collection_id = %s',
				$tables->chunk_search(),
				GuidedRetrievalReadiness::collection_id()
			)
		);
		$vector_count  = (int) $this->connection->get_var(
			$this->connection->prepare(
				'SELECT COUNT(*) FROM %i WHERE collection_key = %s AND fingerprint = %s',
				$tables->vectors(),
				GuidedRetrievalReadiness::collection_id(),
				GuidedRetrievalReadiness::profile_fingerprint()
			)
		);

		return $lexical_count > 0 && $vector_count > 0;
	}

	/**
	 * Confirm that a binding uses the fixed source profile and completed local projections.
	 *
	 * @param BotRetrievalBinding $binding Persisted binding.
	 * @param bool                $index_present Whether both projections are complete.
	 */
	private function binding_is_publishable( BotRetrievalBinding $binding, bool $index_present ): bool {
		if ( ! $index_present || GuidedRetrievalReadiness::collection_id() !== $binding->collection_id ) {
			return false;
		}

		try {
			$source = $this->sources->findById( $binding->source_id );
		} catch ( Throwable ) {
			return false;
		}

		return null !== $source && GuidedRetrievalReadiness::collection_id() === GuidedRetrievalReadiness::source_collection_id( $source );
	}

	/**
	 * Preserve the existing onboarding step names while adding publish readiness separately.
	 *
	 * @param bool $provider_configured Whether a generation provider is configured.
	 * @param bool $model_available Whether a compatible local model is cached.
	 * @param bool $ready Whether a persisted bot is model-ready.
	 */
	private function next_step( bool $provider_configured, bool $model_available, bool $ready ): string {
		if ( ! $provider_configured ) {
			return 'provider';
		}
		if ( ! $model_available ) {
			return 'model';
		}

		return $ready ? 'complete' : 'first_bot';
	}
}
// phpcs:enable WordPress.NamingConventions
