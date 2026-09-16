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
use WpRagAiChatbot\Jobs\Sync\WordPressDocumentIndexDependencies;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Providers\ProviderConfigurationService;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\ProviderRegistry;

// phpcs:disable WordPress.NamingConventions -- REST keys follow the existing snake_case contract.
/** Projects local provider, knowledge, index, and bot state without provider work. */
final class SetupReadinessRestResource {
	private const PAGE_SIZE = 100;

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
	 * @return array{ready:bool,next_step:string,configured_generation_provider:bool,configured_gemini_embedding:bool,model_available:bool,source_count:int,completed_index_present:bool,enabled_bot_count:int,bound_bot_present:bool,publishable_bot_present:bool}
	 */
	public function readiness(): array {
		$bots                    = $this->all_bots();
		$provider_configured     = $this->has_configured_generation_provider();
		$model_available         = $this->has_configured_model_capability();
		$legacy_ready            = $this->has_model_ready_bot( $bots );
		$source_count            = $this->sources->paginate( 1, self::PAGE_SIZE )->total;
		$completed_index_present = $this->completed_index_present();
		$enabled_bot_count       = count( array_filter( $bots, static fn ( Bot $bot ): bool => $bot->enabled ) );
		$bound_bot_present       = false;
		$publishable_bot_present = false;

		foreach ( $bots as $bot ) {
			$binding = $this->binding( $bot );
			if ( null !== $binding ) {
				$bound_bot_present = true;
			}

			if (
				$bot->enabled
				&& $this->bot_model_is_ready( $bot )
				&& null !== $binding
				&& $this->binding_is_publishable( $binding, $completed_index_present )
			) {
				$publishable_bot_present = true;
			}
		}

		return array(
			'ready'                          => $legacy_ready,
			'next_step'                      => $this->next_step( $provider_configured, $model_available, $legacy_ready ),
			'configured_generation_provider' => $provider_configured,
			'configured_gemini_embedding'    => $this->configured_gemini_embedding(),
			'model_available'                => $model_available,
			'source_count'                   => $source_count,
			'completed_index_present'        => $completed_index_present,
			'enabled_bot_count'              => $enabled_bot_count,
			'bound_bot_present'              => $bound_bot_present,
			'publishable_bot_present'        => $publishable_bot_present,
		);
	}

	/**
	 * Return all bots through the existing bounded page contract.
	 *
	 * @return array<int,Bot> Persisted bots.
	 */
	private function all_bots(): array {
		$items  = array();
		$page   = 1;
		$total  = 0;
		$loaded = 0;

		do {
			$result = $this->bots->list( $page, self::PAGE_SIZE );
			$total  = $result['total'];
			$items  = array_merge( $items, $result['items'] );
			$loaded = count( $items );
			++$page;
		} while ( $loaded < $total );

		return $items;
	}

	/** Determine whether a generation provider is locally configured and available. */
	private function has_configured_generation_provider(): bool {
		foreach ( $this->providers->ids() as $provider_id ) {
			try {
				$provider   = $this->providers->generation( $provider_id );
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

	/** Determine whether a configured provider has a registered local model catalog. */
	private function has_configured_model_capability(): bool {
		foreach ( $this->providers->ids() as $provider_id ) {
			try {
				$provider   = $this->providers->generation( $provider_id );
				$descriptor = $this->configuration->describe( $provider_id );
			} catch ( Throwable ) {
				continue;
			}

			if (
				$provider->available()
				&& ProviderHealthStatus::CONFIGURED === $descriptor->health->status
				&& null !== $this->providers->catalog( $provider_id )
			) {
				return true;
			}
		}

		return false;
	}

	/** Determine whether the fixed Gemini embedding capability is locally configured. */
	private function configured_gemini_embedding(): bool {
		$embedding = $this->providers->embedding( ProviderIds::GEMINI_DIRECT );
		if ( null === $embedding || ! $embedding->available() ) {
			return false;
		}

		try {
			$descriptor = $this->configuration->describe( ProviderIds::GEMINI_DIRECT );
		} catch ( Throwable ) {
			return false;
		}

		return ProviderHealthStatus::CONFIGURED === $descriptor->health->status;
	}

	/**
	 * Determine whether at least one persisted bot uses a locally ready provider/model capability.
	 *
	 * @param array<int,Bot> $bots Persisted bots.
	 */
	private function has_model_ready_bot( array $bots ): bool {
		foreach ( $bots as $bot ) {
			if ( $this->bot_model_is_ready( $bot ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether one persisted bot's provider and model selection are locally usable.
	 *
	 * @param Bot $bot Persisted bot.
	 */
	private function bot_model_is_ready( Bot $bot ): bool {
		try {
			$provider   = $this->providers->generation( $bot->provider_id );
			$descriptor = $this->configuration->describe( $bot->provider_id );
		} catch ( Throwable ) {
			return false;
		}

		return $provider->available()
			&& ProviderHealthStatus::CONFIGURED === $descriptor->health->status
			&& null !== $this->providers->catalog( $bot->provider_id )
			&& '' !== trim( $bot->model_id );
	}

	/** Check the fixed local lexical and vector projections without touching a provider. */
	private function completed_index_present(): bool {
		$tables = new TableNames( $this->connection->prefix() );
		if (
			! $this->connection->table_exists( $tables->vector_collections() )
			|| ! $this->connection->table_exists( $tables->vectors() )
			|| ! $this->connection->table_exists( $tables->chunk_search() )
		) {
			return false;
		}

		$lexical_count = (int) $this->connection->get_var(
			$this->connection->prepare(
				'SELECT COUNT(*) FROM %i WHERE collection_id = %s',
				$tables->chunk_search(),
				WordPressDocumentIndexDependencies::COLLECTION_ID
			)
		);
		$vector_count  = (int) $this->connection->get_var(
			$this->connection->prepare(
				'SELECT COUNT(*) FROM %i WHERE collection_key = %s AND fingerprint = %s',
				$tables->vectors(),
				WordPressDocumentIndexDependencies::COLLECTION_ID,
				self::profile_fingerprint()
			)
		);

		return $lexical_count > 0 && $vector_count > 0;
	}

	/**
	 * Resolve one persisted binding without leaking malformed storage details.
	 *
	 * @param Bot $bot Persisted bot.
	 */
	private function binding( Bot $bot ): ?BotRetrievalBinding {
		try {
			return $this->bindings->find( $bot->id );
		} catch ( Throwable ) {
			return null;
		}
	}

	/**
	 * Confirm that a binding uses the fixed source profile and completed local projections.
	 *
	 * @param BotRetrievalBinding $binding Persisted bot binding.
	 * @param bool                $index_present Whether both local projections are populated.
	 */
	private function binding_is_publishable( BotRetrievalBinding $binding, bool $index_present ): bool {
		if ( ! $index_present || WordPressDocumentIndexDependencies::COLLECTION_ID !== $binding->collection_id ) {
			return false;
		}

		try {
			$source = $this->sources->findById( $binding->source_id );
		} catch ( Throwable ) {
			return false;
		}

		return null !== $source && self::source_uses_guided_profile( $source );
	}

	/**
	 * Check the server-owned semantic configuration stored with a source.
	 *
	 * @param KnowledgeSourceRecord $source Persisted source.
	 */
	private static function source_uses_guided_profile( KnowledgeSourceRecord $source ): bool {
		$semantic = $source->config['semantic_retrieval'] ?? null;
		$expected = WordPressDocumentIndexDependencies::semantic_configuration();
		if ( ! is_array( $semantic ) || count( $semantic ) !== count( $expected ) ) {
			return false;
		}

		foreach ( $expected as $key => $value ) {
			if ( ! array_key_exists( $key, $semantic ) || $semantic[ $key ] !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Preserve the existing onboarding step names while adding publish readiness separately.
	 *
	 * @param bool $provider_configured Whether a generation provider is configured.
	 * @param bool $model_available Whether a local model catalog is registered.
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

	/** Calculate the fixed vector compatibility fingerprint without provider work. */
	private static function profile_fingerprint(): string {
		return hash(
			'sha256',
			"v1\nprovider=" . WordPressDocumentIndexDependencies::EMBEDDING_PROVIDER_ID
			. "\nmodel=" . WordPressDocumentIndexDependencies::EMBEDDING_MODEL_ID
			. "\ndimensions=" . WordPressDocumentIndexDependencies::EMBEDDING_DIMENSIONS
			. "\nnormalization=none\ndistance=cosine"
		);
	}
}
// phpcs:enable WordPress.NamingConventions
