<?php
/**
 * Setup readiness REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbotTests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
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
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\Credentials\Secret;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\ModelCatalogProvider;
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

		self::assertSame( false, $response['ready'] );
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

	/** Provider, fixed Gemini embedding capability, source, index, bot, and binding all gate publishability. */
	public function test_readiness_reports_persisted_provider_index_and_publishable_bot_state(): void {
		$registry = $this->registry( true, true );
		$bots     = $this->bots( array( $this->bot() ) );
		$binding  = $this->createMock( BotRetrievalBindingRepository::class );
		$binding->expects( self::once() )
			->method( 'find' )
			->with( new BotId( self::BOT_ID ) )
			->willReturn( new BotRetrievalBinding( 7, WordPressDocumentIndexDependencies::COLLECTION_ID ) );

		$response = $this->resource(
			$registry,
			$this->configuration( $registry, true ),
			$this->sources( 1, $this->source() ),
			$bots,
			$binding,
			$this->connection( array( 2, 2 ) )
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
		$registry = $this->registry( true, true );
		$bots     = $this->bots( array( $this->bot( false ) ) );
		$binding  = $this->createMock( BotRetrievalBindingRepository::class );
		$binding->expects( self::once() )
			->method( 'find' )
			->with( new BotId( self::BOT_ID ) )
			->willReturn( null );

		$response = $this->resource(
			$registry,
			$this->configuration( $registry, true ),
			$this->sources( 1, $this->source() ),
			$bots,
			$binding,
			$this->connection( array( 2, 2 ) )
		)->readiness();

		self::assertSame( 0, $response['enabled_bot_count'] );
		self::assertFalse( $response['bound_bot_present'] );
		self::assertFalse( $response['publishable_bot_present'] );
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
			$bindings ?? $this->createMock( BotRetrievalBindingRepository::class ),
			$connection ?? $this->connection( array( 0, 0 ) )
		);
	}

	/**
	 * Build a provider registry fixture.
	 *
	 * @param bool $with_catalog Whether a local model catalog is registered.
	 * @param bool $with_embedding Whether the Gemini embedding capability is registered.
	 */
	private function registry( bool $with_catalog, bool $with_embedding ): ProviderRegistry {
		$generation = $this->createMock( GenerationProvider::class );
		$generation->method( 'provider_id' )->willReturn( ProviderIds::GEMINI_DIRECT );
		$generation->method( 'available' )->willReturn( true );
		$catalog = null;
		if ( $with_catalog ) {
			$catalog = $this->createMock( ModelCatalogProvider::class );
			$catalog->method( 'provider_id' )->willReturn( ProviderIds::GEMINI_DIRECT );
		}

		$registry = new ProviderRegistry();
		$registry->register( ProviderIds::GEMINI_DIRECT, $generation, $catalog, $with_embedding ? $this->embedding() : null );
		return $registry;
	}

	/** Build a Gemini embedding capability fixture. */
	private function embedding(): EmbeddingProvider&MockObject {
		$embedding = $this->createMock( EmbeddingProvider::class );
		$embedding->method( 'provider_id' )->willReturn( ProviderIds::GEMINI_DIRECT );
		$embedding->method( 'available' )->willReturn( true );
		return $embedding;
	}

	/**
	 * Build provider configuration state.
	 *
	 * @param ProviderRegistry $registry Provider registry.
	 * @param bool             $configured Whether the credential is present.
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
	private function sources( int $total = 0, ?KnowledgeSourceRecord $source = null ): KnowledgeSourceRepository&MockObject {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'paginate' )->willReturn( new PagedResult( $source ? array( $source ) : array(), $total, 1, 100 ) );
		$sources->method( 'findById' )->willReturn( $source );
		return $sources;
	}

	/**
	 * Build a bot repository fixture.
	 *
	 * @param array<int,Bot> $items Persisted bot page.
	 */
	private function bots( array $items = array() ): BotRepository&MockObject {
		$bots = $this->createMock( BotRepository::class );
		$bots->method( 'list' )->willReturn(
			array(
				'items'    => $items,
				'total'    => count( $items ),
				'page'     => 1,
				'per_page' => 100,
			)
		);
		return $bots;
	}

	/**
	 * Build a local index connection fixture.
	 *
	 * @param array<int,int> $counts Lexical and vector row counts.
	 */
	private function connection( array $counts ): Connection&MockObject {
		$connection = $this->createMock( Connection::class );
		$connection->method( 'prefix' )->willReturn( 'wp_' );
		$connection->method( 'table_exists' )->willReturn( true );
		$connection->method( 'prepare' )->willReturn( 'prepared' );
		$connection->method( 'get_var' )->willReturnOnConsecutiveCalls( ...$counts );
		return $connection;
	}

	/**
	 * Build one persisted bot fixture.
	 *
	 * @param bool $enabled Whether the bot is enabled.
	 */
	private function bot( bool $enabled = true ): Bot {
		return new Bot( new BotId( self::BOT_ID ), 'Support bot', $enabled, ProviderIds::GEMINI_DIRECT, 'gemini-2.5-flash', 1, '2026-09-15 12:00:00', '2026-09-15 12:00:00' );
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
}
