<?php
/**
 * Setup readiness REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbotTests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\SetupReadinessRestResource;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Core\PagedResult;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\BotRetrievalBindingRepository;
use WpRagAiChatbot\Jobs\Sync\WordPressDocumentIndexDependencies;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Providers\Cache\CachedModelCatalogProvider;
use WpRagAiChatbot\Providers\Cache\ModelCatalogCache;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\Credentials\Secret;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\ModelCatalogProvider;
use WpRagAiChatbot\Providers\ModelInfo;
use WpRagAiChatbot\Providers\ProviderConfigurationService;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\ProviderRegistry;

/** Verifies bounded, server-derived setup readiness. */
final class SetupReadinessRestResourceTest extends TestCase {
	private const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/** Legacy readiness keys remain present when setup has no persisted state. */
	public function test_empty_setup_preserves_legacy_readiness_and_returns_safe_false_state(): void {
		self::assertTrue( class_exists( SetupReadinessRestResource::class ), 'SetupReadinessRestResource must exist.' );

		$response = $this->resource()->readiness();

		self::assertTrue( array_key_exists( 'ready', $response ) );
		self::assertTrue( array_key_exists( 'next_step', $response ) );
		self::assertArrayNotHasKey( 'issue', $response );
		self::assertFalse( $response['ready'] );
		self::assertSame( 'provider', $response['next_step'] );
		self::assertFalse( $response['configured_generation_provider'] );
		self::assertFalse( $response['configured_gemini_embedding'] );
		self::assertFalse( $response['model_available'] );
		self::assertSame( 0, $response['source_count'] );
		self::assertFalse( $response['completed_index_present'] );
		self::assertSame( 0, $response['enabled_bot_count'] );
		self::assertFalse( $response['bound_bot_present'] );
		self::assertFalse( $response['publishable_bot_present'] );
	}

	/** Safe legacy onboarding issue codes remain available for provider recovery. */
	public function test_missing_credentials_preserve_legacy_issue_code(): void {
		$registry = $this->registry( array( $this->model() ) );
		$response = $this->resource(
			$registry,
			$this->configuration( $registry, false )
		)->readiness();

		self::assertSame( 'missing_credential', $response['issue'] ?? null );
		self::assertSame( 'provider', $response['next_step'] );
		self::assertFalse( $response['model_available'] );
	}

	/** Unavailable providers expose only the existing safe issue code. */
	public function test_unavailable_provider_preserves_legacy_issue_code(): void {
		$registry = $this->registry( array( $this->model() ), false, false );

		$response = $this->resource( $registry, $this->configuration( $registry, true ) )->readiness();

		self::assertSame( 'provider_unavailable', $response['issue'] ?? null );
		self::assertSame( 'provider', $response['next_step'] );
		self::assertFalse( $response['configured_generation_provider'] );
	}

	/** Empty or unsupported local catalogs expose only the existing capability issue code. */
	public function test_unsupported_catalog_preserves_legacy_issue_code(): void {
		$registry = $this->registry();

		$response = $this->resource( $registry, $this->configuration( $registry, true ) )->readiness();

		self::assertSame( 'unsupported_capability', $response['issue'] ?? null );
		self::assertSame( 'model', $response['next_step'] );
		self::assertFalse( $response['model_available'] );
	}

	/** Empty cached catalogs fail closed without discovering models during an admin request. */
	public function test_empty_catalog_fails_model_readiness_without_provider_discovery(): void {
		$registry = $this->registry();
		$bots     = $this->bots( array( $this->bot() ) );

		$response = $this->resource( $registry, $this->configuration( $registry, true ), null, $bots )->readiness();

		self::assertFalse( $response['model_available'] );
		self::assertFalse( $response['ready'] );
		self::assertFalse( $response['publishable_bot_present'] );
	}

	/** A persisted stale model ID cannot make either readiness flag true. */
	public function test_stale_model_id_fails_ready_and_publishable_bot_presence(): void {
		$registry = $this->registry( array( $this->model( 'current-model' ) ) );
		$bots     = $this->bots( array( $this->bot( true, 'stale-model' ) ) );

		$response = $this->resource(
			$registry,
			$this->configuration( $registry, true ),
			$this->sources( 1, $this->source() ),
			$bots,
			$this->bindings( array(), true ),
			$this->connection( array( 2, 2 ), $this->validCollection() )
		)->readiness();

		self::assertTrue( $response['model_available'] );
		self::assertFalse( $response['ready'] );
		self::assertSame( 'first_bot', $response['next_step'] );
		self::assertFalse( $response['publishable_bot_present'] );
	}

	/** A cached compatible model ID is required before a bot can satisfy legacy readiness. */
	public function test_valid_model_id_makes_legacy_readiness_true(): void {
		$registry = $this->registry( array( $this->model() ) );
		$bots     = $this->bots( array( $this->bot() ) );

		$response = $this->resource( $registry, $this->configuration( $registry, true ), null, $bots )->readiness();

		self::assertTrue( $response['model_available'] );
		self::assertTrue( $response['ready'] );
		self::assertSame( 'complete', $response['next_step'] );
	}

	/** Provider, fixed Gemini embedding capability, source, index, bot, and binding all gate publishability. */
	public function test_readiness_reports_persisted_provider_index_and_publishable_bot_state(): void {
		$registry = $this->registry( array( $this->model() ), true );
		$bots     = $this->bots( array( $this->bot() ) );

		$response = $this->resource(
			$registry,
			$this->configuration( $registry, true ),
			$this->sources( 1, $this->source() ),
			$bots,
			$this->bindings( array( self::BOT_ID => new BotRetrievalBinding( 7, WordPressDocumentIndexDependencies::COLLECTION_ID ) ), true ),
			$this->connection( array( 2, 2 ), $this->validCollection() )
		)->readiness();

		self::assertTrue( $response['ready'] );
		self::assertSame( 'complete', $response['next_step'] );
		self::assertTrue( $response['configured_generation_provider'] );
		self::assertTrue( $response['configured_gemini_embedding'] );
		self::assertTrue( $response['model_available'] );
		self::assertSame( 1, $response['source_count'] );
		self::assertTrue( $response['completed_index_present'] );
		self::assertSame( 1, $response['enabled_bot_count'] );
		self::assertTrue( $response['bound_bot_present'] );
		self::assertTrue( $response['publishable_bot_present'] );
	}

	/** A disabled or unbound bot cannot become publishable merely because an index exists. */
	public function test_publishable_bot_requires_enabled_model_ready_bot_and_binding(): void {
		$registry = $this->registry( array( $this->model() ), true );
		$bots     = $this->bots( array( $this->bot( false ) ) );

		$response = $this->resource(
			$registry,
			$this->configuration( $registry, true ),
			$this->sources( 1, $this->source() ),
			$bots,
			$this->bindings(),
			$this->connection( array( 2, 2 ), $this->validCollection() )
		)->readiness();

		self::assertSame( 0, $response['enabled_bot_count'] );
		self::assertFalse( $response['bound_bot_present'] );
		self::assertFalse( $response['publishable_bot_present'] );
	}

	/** Malformed binding repository data cannot become publishable through the readiness DTO. */
	public function test_malformed_batch_binding_fails_publishability(): void {
		$registry = $this->registry( array( $this->model() ), true );
		$binding  = $this->bindings( array( self::BOT_ID => array( 'source_id' => 7 ) ), true );

		$response = $this->resource(
			$registry,
			$this->configuration( $registry, true ),
			$this->sources( 1, $this->source() ),
			$this->bots( array( $this->bot() ) ),
			$binding,
			$this->connection( array( 2, 2 ), $this->validCollection() )
		)->readiness();

		self::assertTrue( $response['bound_bot_present'] );
		self::assertFalse( $response['publishable_bot_present'] );
	}

	/**
	 * Missing, mismatched, and partial local collection state fails closed.
	 *
	 * @param array<string,mixed>|null $collection Collection row fixture.
	 * @param array<int,int>           $counts Lexical and vector row counts.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalid_collection_states' )]
	public function test_completed_index_requires_exact_collection_profile_and_both_projections(
		?array $collection,
		array $counts
	): void {
		$registry = $this->registry( array( $this->model() ), true );

		$response = $this->resource(
			$registry,
			$this->configuration( $registry, true ),
			$this->sources( 1, $this->source() ),
			$this->bots( array( $this->bot() ) ),
			$this->bindings( array( self::BOT_ID => new BotRetrievalBinding( 7, WordPressDocumentIndexDependencies::COLLECTION_ID ) ), true ),
			$this->connection( $counts, $collection )
		)->readiness();

		self::assertFalse( $response['completed_index_present'] );
		self::assertFalse( $response['publishable_bot_present'] );
	}

	/** A hard server-side scan ceiling fails closed instead of materializing all bots or binding N+1. */
	public function test_large_bot_inventory_fails_publishability_without_batch_binding_lookup(): void {
		$registry = $this->registry( array( $this->model() ), true );
		$bots     = $this->bots( array( $this->bot() ), 101, 75 );
		$binding  = $this->bindings( array(), true );
		$binding->expects( self::never() )->method( 'find_for_bot_ids' );

		$response = $this->resource(
			$registry,
			$this->configuration( $registry, true ),
			$this->sources( 1, $this->source() ),
			$bots,
			$binding,
			$this->connection( array( 2, 2 ), $this->validCollection() )
		)->readiness();

		self::assertSame( 75, $response['enabled_bot_count'] );
		self::assertTrue( $response['bound_bot_present'] );
		self::assertFalse( $response['ready'] );
		self::assertFalse( $response['publishable_bot_present'] );
	}

	/**
	 * Return invalid fixed-profile collection states.
	 *
	 * @return array<string,array{0:array<string,mixed>|null,1:array<int,int>}> Invalid fixtures.
	 */
	public static function invalid_collection_states(): array {
		return array(
			'missing collection row' => array( null, array( 2, 2 ) ),
			'wrong fingerprint'      => array(
				array(
					'fingerprint' => 'wrong',
					'dimensions'  => 3072,
				),
				array( 2, 2 ),
			),
			'wrong dimensions'       => array(
				array(
					'fingerprint' => self::profile_fingerprint(),
					'dimensions'  => 1536,
				),
				array( 2, 2 ),
			),
			'lexical only'           => array(
				array(
					'fingerprint' => self::profile_fingerprint(),
					'dimensions'  => 3072,
				),
				array( 2, 0 ),
			),
			'vector only'            => array(
				array(
					'fingerprint' => self::profile_fingerprint(),
					'dimensions'  => 3072,
				),
				array( 0, 2 ),
			),
		);
	}

	/**
	 * Build the resource under test.
	 *
	 * @param ProviderRegistry|null              $registry Provider registry.
	 * @param ProviderConfigurationService|null  $configuration Provider state.
	 * @param KnowledgeSourceRepository|null     $sources Source repository.
	 * @param BotRepository|null                 $bots Bot repository.
	 * @param BotRetrievalBindingRepository|null $bindings Binding repository.
	 * @param Connection|null                    $connection Local index connection.
	 */
	private function resource(
		?ProviderRegistry $registry = null,
		?ProviderConfigurationService $configuration = null,
		?KnowledgeSourceRepository $sources = null,
		?BotRepository $bots = null,
		?BotRetrievalBindingRepository $bindings = null,
		?Connection $connection = null
	): SetupReadinessRestResource {
		$registry ??= new ProviderRegistry();
		return new SetupReadinessRestResource(
			$registry,
			$configuration ?? $this->configuration( $registry, false ),
			$sources ?? $this->sources(),
			$bots ?? $this->bots(),
			$bindings ?? $this->bindings(),
			$connection ?? $this->connection( array( 0, 0 ), null )
		);
	}

	/**
	 * Build a provider registry fixture backed by a cache-only catalog.
	 *
	 * @param array<int,ModelInfo> $models Cached model metadata.
	 * @param bool                 $with_embedding Whether embedding is registered.
	 * @param bool                 $available Whether generation is available.
	 */
	private function registry( array $models = array(), bool $with_embedding = false, bool $available = true ): ProviderRegistry {
		$generation = $this->createMock( GenerationProvider::class );
		$generation->method( 'provider_id' )->willReturn( ProviderIds::GEMINI_DIRECT );
		$generation->method( 'available' )->willReturn( $available );
		$catalog = $this->createMock( ModelCatalogProvider::class );
		$catalog->method( 'provider_id' )->willReturn( ProviderIds::GEMINI_DIRECT );
		$cache = $this->createMock( ModelCatalogCache::class );
		$cache->method( 'get' )->willReturn( $models );

		$registry = new ProviderRegistry();
		$registry->register(
			ProviderIds::GEMINI_DIRECT,
			$generation,
			new CachedModelCatalogProvider( $catalog, $cache ),
			$with_embedding ? $this->embedding() : null
		);
		return $registry;
	}

	/**
	 * Build one compatible generation model.
	 *
	 * @param string $model_id Model identifier.
	 */
	private function model( string $model_id = 'gemini-2.5-flash' ): ModelInfo {
		return new ModelInfo( ProviderIds::GEMINI_DIRECT, $model_id, 'Test model', array( 'text' ), array( 'text' ), array( 'generation' ) );
	}

	/** Build a Gemini embedding capability fixture. */
	private function embedding(): EmbeddingProvider {
		$embedding = $this->createMock( EmbeddingProvider::class );
		$embedding->method( 'provider_id' )->willReturn( ProviderIds::GEMINI_DIRECT );
		$embedding->method( 'available' )->willReturn( true );
		return $embedding;
	}

	/**
	 * Build provider configuration state.
	 *
	 * @param ProviderRegistry $registry Provider registry.
	 * @param bool             $configured Whether a managed credential exists.
	 */
	private function configuration( ProviderRegistry $registry, bool $configured ): ProviderConfigurationService {
		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( null );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( $configured ? new Secret( 'test-only' ) : null );
		return new ProviderConfigurationService( $registry, new CredentialResolver( $reader, $store ) );
	}

	/**
	 * Build a source repository fixture.
	 *
	 * @param int                        $total Source count.
	 * @param KnowledgeSourceRecord|null $source Optional source fixture.
	 */
	private function sources( int $total = 0, ?KnowledgeSourceRecord $source = null ): KnowledgeSourceRepository {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'paginate' )->willReturn( new PagedResult( $source ? array( $source ) : array(), $total, 1, 100 ) );
		$sources->method( 'findById' )->willReturn( $source );
		return $sources;
	}

	/**
	 * Build a bot repository fixture.
	 *
	 * @param array<int,Bot> $items Persisted bot page.
	 * @param int|null       $total Persisted bot total.
	 * @param int|null       $enabled_count Aggregate enabled count.
	 */
	private function bots( array $items = array(), ?int $total = null, ?int $enabled_count = null ): BotRepository {
		$bots = $this->createMock( BotRepository::class );
		$bots->method( 'count_enabled' )->willReturn( $enabled_count ?? count( array_filter( $items, static fn ( Bot $bot ): bool => $bot->enabled ) ) );
		$bots->method( 'list' )->willReturn(
			array(
				'items'    => $items,
				'total'    => $total ?? count( $items ),
				'page'     => 1,
				'per_page' => 100,
			)
		);
		return $bots;
	}

	/**
	 * Build a binding repository fixture with aggregate and batch seams.
	 *
	 * @param array<string,BotRetrievalBinding> $bindings Valid bindings keyed by bot ID.
	 * @param bool                              $has_any Whether an aggregate binding exists.
	 */
	private function bindings( array $bindings = array(), bool $has_any = false ): BotRetrievalBindingRepository {
		$repository = $this->createMock( BotRetrievalBindingRepository::class );
		$repository->method( 'has_any' )->willReturn( $has_any );
		$repository->method( 'find_for_bot_ids' )->willReturn( $bindings );
		return $repository;
	}

	/**
	 * Build a local index connection fixture.
	 *
	 * @param array<int,int>           $counts Lexical and vector row counts.
	 * @param array<string,mixed>|null $collection Collection profile row.
	 */
	private function connection( array $counts, ?array $collection ): Connection {
		$connection = $this->createMock( Connection::class );
		$connection->method( 'prefix' )->willReturn( 'wp_' );
		$connection->method( 'table_exists' )->willReturn( true );
		$connection->method( 'prepare' )->willReturn( 'prepared' );
		$connection->method( 'get_row' )->willReturn( $collection );
		$connection->method( 'get_var' )->willReturnOnConsecutiveCalls( ...$counts );
		return $connection;
	}

	/**
	 * Build one persisted bot fixture.
	 *
	 * @param bool   $enabled Whether the bot is enabled.
	 * @param string $model_id Persisted model identifier.
	 */
	private function bot( bool $enabled = true, string $model_id = 'gemini-2.5-flash' ): Bot {
		return new Bot( new BotId( self::BOT_ID ), 'Support bot', $enabled, ProviderIds::GEMINI_DIRECT, $model_id, 1, '2026-09-15 12:00:00', '2026-09-15 12:00:00' );
	}

	/** Build one source carrying the fixed guided semantic profile. */
	private function source(): KnowledgeSourceRecord {
		$now = new DateTimeImmutable( '2026-09-15T12:00:00+00:00' );
		return new KnowledgeSourceRecord(
			7,
			'source:support',
			'manual_text',
			null,
			'Support content',
			null,
			'active',
			array( 'semantic_retrieval' => WordPressDocumentIndexDependencies::semantic_configuration() ),
			'source-generation',
			null,
			$now,
			$now
		);
	}

	/** Build the exact fixed collection projection row. */
	private function validCollection(): array {
		return array(
			'fingerprint' => self::profile_fingerprint(),
			'dimensions'  => 3072,
		);
	}

	/** Return the fixed collection profile fingerprint fixture. */
	private static function profile_fingerprint(): string {
		return hash( 'sha256', "v1\nprovider=gemini_direct\nmodel=gemini-embedding-001\ndimensions=3072\nnormalization=none\ndistance=cosine" );
	}
}
