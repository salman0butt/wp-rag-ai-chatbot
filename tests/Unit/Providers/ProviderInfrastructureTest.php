<?php
/**
 * Provider registry, configuration, and bootstrap tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers;

use Brain\Monkey;
use Brain\Monkey\Functions;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSource;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\ModelCatalogProvider;
use WpRagAiChatbot\Providers\ProviderBootstrap;
use WpRagAiChatbot\Providers\ProviderConfigurationService;
use WpRagAiChatbot\Providers\ProviderDescriptor;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\ProviderRegistry;

/**
 * Verifies provider composition exposes only safe, local state.
 */
final class ProviderInfrastructureTest extends TestCase {
	/**
	 * Start Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear Brain Monkey down after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Registry returns exact registered generation/catalog providers and stable IDs.
	 */
	public function test_registry_registers_and_resolves_providers(): void {
		$this->require_infrastructure();

		$generation = $this->createMock( GenerationProvider::class );
		$catalog    = $this->createMock( ModelCatalogProvider::class );
		$generation->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
		$catalog->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );

		$registry = new ProviderRegistry();
		$registry->register( ProviderIds::OPENAI_DIRECT, $generation, $catalog );

		self::assertSame( $generation, $registry->generation( ProviderIds::OPENAI_DIRECT ) );
		self::assertSame( $catalog, $registry->catalog( ProviderIds::OPENAI_DIRECT ) );
		self::assertSame( array( ProviderIds::OPENAI_DIRECT ), $registry->ids() );
	}

	/**
	 * Duplicate provider registration is rejected.
	 */
	public function test_registry_rejects_duplicate_registration(): void {
		$this->require_infrastructure();

		$generation = $this->createMock( GenerationProvider::class );
		$generation->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
		$registry = new ProviderRegistry();
		$registry->register( ProviderIds::OPENAI_DIRECT, $generation );

		$this->expectException( InvalidArgumentException::class );
		$registry->register( ProviderIds::OPENAI_DIRECT, $generation );
	}

	/**
	 * Unknown generation lookup is explicit and catalog absence remains nullable.
	 */
	public function test_registry_unknown_generation_throws(): void {
		$this->require_infrastructure();

		$registry = new ProviderRegistry();
		self::assertNull( $registry->catalog( 'unknown' ) );

		$this->expectException( OutOfBoundsException::class );
		$registry->generation( 'unknown' );
	}

	/**
	 * Descriptors expose local configuration state without secret material or provider calls.
	 */
	public function test_configuration_descriptors_are_secret_free_and_local_only(): void {
		$this->require_infrastructure();

		$openai     = $this->createMock( GenerationProvider::class );
		$openrouter = $this->createMock( GenerationProvider::class );
		$core       = $this->createMock( GenerationProvider::class );
		$openai_cat = $this->createMock( ModelCatalogProvider::class );
		$router_cat = $this->createMock( ModelCatalogProvider::class );

		$openai->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
		$openrouter->method( 'provider_id' )->willReturn( ProviderIds::OPENROUTER_DIRECT );
		$core->method( 'provider_id' )->willReturn( ProviderIds::WORDPRESS_AI_CLIENT );
		$openai->method( 'available' )->willReturn( true );
		$openrouter->method( 'available' )->willReturn( true );
		$core->method( 'available' )->willReturn( true );
		$openai->expects( self::never() )->method( 'generate' );
		$openrouter->expects( self::never() )->method( 'generate' );
		$core->expects( self::never() )->method( 'generate' );
		$openai_cat->expects( self::never() )->method( 'models' );
		$router_cat->expects( self::never() )->method( 'models' );
		$openai_cat->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
		$router_cat->method( 'provider_id' )->willReturn( ProviderIds::OPENROUTER_DIRECT );

		$registry = new ProviderRegistry();
		$registry->register( ProviderIds::OPENAI_DIRECT, $openai, $openai_cat );
		$registry->register( ProviderIds::OPENROUTER_DIRECT, $openrouter, $router_cat );
		$registry->register( ProviderIds::WORDPRESS_AI_CLIENT, $core );

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturnCallback(
			static fn ( string $name ): ?string => 'OPENAI_API_KEY' === $name ? 'descriptor-secret' : null
		);
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( null );
		$service = new ProviderConfigurationService( $registry, new CredentialResolver( $reader, $store ) );

		$openai_descriptor = $service->describe( ProviderIds::OPENAI_DIRECT );
		self::assertInstanceOf( ProviderDescriptor::class, $openai_descriptor );
		self::assertSame( CredentialSource::ENVIRONMENT, $openai_descriptor->credential_source );
		self::assertSame( ProviderHealthStatus::CONFIGURED, $openai_descriptor->health->status );
		self::assertSame( array( 'generation', 'model_catalog' ), $openai_descriptor->capabilities );

		$router_descriptor = $service->describe( ProviderIds::OPENROUTER_DIRECT );
		self::assertSame( CredentialSource::NONE, $router_descriptor->credential_source );
		self::assertSame( ProviderHealthStatus::UNCONFIGURED, $router_descriptor->health->status );

		$core_descriptor = $service->describe( ProviderIds::WORDPRESS_AI_CLIENT );
		self::assertSame( CredentialSource::CORE, $core_descriptor->credential_source );
		self::assertSame( ProviderHealthStatus::CONFIGURED, $core_descriptor->health->status );
		self::assertSame( array( 'generation' ), $core_descriptor->capabilities );

		$serialized = wp_json_encode( array( $openai_descriptor, $router_descriptor, $core_descriptor ) );
		self::assertIsString( $serialized );
		self::assertStringNotContainsString( 'descriptor-secret', $serialized );
		self::assertStringNotContainsString( 'ciphertext', strtolower( $serialized ) );
		self::assertStringNotContainsString( 'authorization', strtolower( $serialized ) );
		self::assertStringNotContainsString( 'kdf', strtolower( $serialized ) );
		self::assertStringNotContainsString( 'salt', strtolower( $serialized ) );
	}

	/**
	 * Runtime composition registers all adapters without issuing outbound provider requests.
	 */
	public function test_provider_bootstrap_composes_without_outbound_calls(): void {
		$this->require_infrastructure();

		Functions\expect( 'wp_remote_request' )->never();
		ProviderBootstrap::register();

		self::assertSame(
			array( ProviderIds::OPENAI_DIRECT, ProviderIds::OPENROUTER_DIRECT, ProviderIds::WORDPRESS_AI_CLIENT ),
			ProviderBootstrap::registry()->ids()
		);
		self::assertInstanceOf( ProviderConfigurationService::class, ProviderBootstrap::configuration() );
	}

	/**
	 * Require the intended missing-infrastructure RED before implementation.
	 */
	private function require_infrastructure(): void {
		foreach ( array( ProviderDescriptor::class, ProviderRegistry::class, ProviderConfigurationService::class, ProviderBootstrap::class ) as $class_name ) {
			self::assertTrue( class_exists( $class_name ), sprintf( '%s must exist before provider infrastructure behavior can pass.', $class_name ) );
		}
	}
}
