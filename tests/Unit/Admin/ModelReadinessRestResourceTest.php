<?php
/**
 * Model capability and onboarding readiness resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\ModelReadinessRestResource;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\Credentials\Secret;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\ModelCatalogProvider;
use WpRagAiChatbot\Providers\ModelInfo;
use WpRagAiChatbot\Providers\ProviderConfigurationService;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\ProviderRegistry;

/**
 * Verifies server-derived model compatibility and onboarding state.
 */
final class ModelReadinessRestResourceTest extends TestCase {
	/**
	 * Missing providers, credentials, and model-catalog support remain distinguishable.
	 */
	public function test_model_listing_distinguishes_provider_failure_states(): void {
		$this->require_resource();

		$unknown = $this->resource( new ProviderRegistry(), false );
		self::assertSame( 'provider_unavailable', $unknown->models( 'missing-provider', 'generation' )['error']['code'] );

		$unconfigured_registry = $this->registry( true );
		$unconfigured          = $this->resource( $unconfigured_registry, false );
		self::assertSame(
			'missing_credential',
			$unconfigured->models( ProviderIds::OPENAI_DIRECT, 'generation' )['error']['code']
		);

		$unsupported_registry = $this->registry( false );
		$unsupported          = $this->resource( $unsupported_registry, true );
		self::assertSame(
			'unsupported_capability',
			$unsupported->models( ProviderIds::OPENAI_DIRECT, 'generation' )['error']['code']
		);
	}

	/**
	 * Model responses are normalized and filtered by purpose and requested capability.
	 */
	public function test_model_listing_normalizes_and_filters_models(): void {
		$this->require_resource();

		$catalog = $this->createMock( ModelCatalogProvider::class );
		$catalog->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
		$catalog->method( 'models' )->willReturn(
			array(
				new ModelInfo( ProviderIds::OPENAI_DIRECT, 'chat-stream', 'Chat Stream', array( 'text' ), array( 'text' ), array( 'generation', 'streaming' ) ),
				new ModelInfo( ProviderIds::OPENAI_DIRECT, 'embed-only', 'Embed Only', array( 'text' ), array( 'embedding' ), array( 'embedding' ) ),
				new ModelInfo( ProviderIds::OPENAI_DIRECT, 'chat-basic', 'Chat Basic', array( 'text' ), array( 'text' ), array( 'generation' ) ),
			)
		);

		$registry = $this->registry( true, $catalog );
		$response = $this->resource( $registry, true )->models( ProviderIds::OPENAI_DIRECT, 'generation', 'streaming' );

		self::assertSame( ProviderIds::OPENAI_DIRECT, $response['provider_id'] );
		self::assertSame( 'generation', $response['purpose'] );
		self::assertSame( 'streaming', $response['capability'] );
		self::assertCount( 1, $response['models'] );
		self::assertSame(
			array(
				'provider_id'       => ProviderIds::OPENAI_DIRECT,
				'model_id'          => 'chat-stream',
				'display_name'      => 'Chat Stream',
				'input_modalities'  => array( 'text' ),
				'output_modalities' => array( 'text' ),
				'capabilities'      => array( 'generation', 'streaming' ),
				'context_window'    => null,
			),
			$response['models'][0]
		);
	}

	/**
	 * Readiness is reconstructed from configured provider, compatible model, and persisted bot truth.
	 */
	public function test_readiness_advances_only_when_persisted_requirements_are_satisfied(): void {
		$this->require_resource();

		$catalog = $this->createMock( ModelCatalogProvider::class );
		$catalog->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
		$catalog->method( 'models' )->willReturn(
			array( new ModelInfo( ProviderIds::OPENAI_DIRECT, 'chat-ready', 'Chat Ready', array( 'text' ), array( 'text' ), array( 'generation' ) ) )
		);
		$registry = $this->registry( true, $catalog );

		$without_bot = $this->resource( $registry, true );
		self::assertSame( 'first_bot', $without_bot->readiness()['next_step'] );
		self::assertFalse( $without_bot->readiness()['ready'] );

		$bot  = new Bot(
			new BotId( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
			'Ready bot',
			true,
			ProviderIds::OPENAI_DIRECT,
			'chat-ready',
			1,
			'2026-09-07 10:00:00',
			'2026-09-07 10:00:00'
		);
		$bots = $this->createMock( BotRepository::class );
		$bots->method( 'list' )->willReturn(
			array(
				'items'    => array( $bot ),
				'total'    => 1,
				'page'     => 1,
				'per_page' => 100,
			)
		);

		$ready = new ModelReadinessRestResource(
			$registry,
			$this->configuration( $registry, true ),
			$bots
		);
		self::assertSame( 'complete', $ready->readiness()['next_step'] );
		self::assertTrue( $ready->readiness()['ready'] );

		$unconfigured = $this->resource( $registry, false, $bots );
		self::assertSame( 'provider', $unconfigured->readiness()['next_step'] );
		self::assertFalse( $unconfigured->readiness()['ready'] );
		self::assertSame( 'missing_credential', $unconfigured->readiness()['issue'] );
	}

	/**
	 * Create a provider registry for direct OpenAI test doubles.
	 *
	 * @param bool                      $with_catalog Whether catalog support is registered.
	 * @param ModelCatalogProvider|null $catalog Optional explicit catalog.
	 */
	private function registry( bool $with_catalog, ?ModelCatalogProvider $catalog = null ): ProviderRegistry {
		$provider = $this->createMock( GenerationProvider::class );
		$provider->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
		$provider->method( 'available' )->willReturn( true );

		if ( $with_catalog && null === $catalog ) {
			$catalog = $this->createMock( ModelCatalogProvider::class );
			$catalog->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
			$catalog->method( 'models' )->willReturn( array() );
		}

		$registry = new ProviderRegistry();
		$registry->register( ProviderIds::OPENAI_DIRECT, $provider, $catalog );
		return $registry;
	}

	/**
	 * Create the resource with deterministic credential and bot state.
	 *
	 * @param ProviderRegistry   $registry Provider registry.
	 * @param bool               $configured Whether a managed credential exists.
	 * @param BotRepository|null $bots Optional bot repository.
	 */
	private function resource( ProviderRegistry $registry, bool $configured, ?BotRepository $bots = null ): ModelReadinessRestResource {
		$bots ??= $this->createMock( BotRepository::class );
		$bots->method( 'list' )->willReturn(
			array(
				'items'    => array(),
				'total'    => 0,
				'page'     => 1,
				'per_page' => 100,
			)
		);

		return new ModelReadinessRestResource( $registry, $this->configuration( $registry, $configured ), $bots );
	}

	/**
	 * Create the existing provider configuration service with deterministic credential state.
	 *
	 * @param ProviderRegistry $registry Provider registry.
	 * @param bool             $configured Whether a managed credential exists.
	 */
	private function configuration( ProviderRegistry $registry, bool $configured ): ProviderConfigurationService {
		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( null );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( $configured ? new Secret( 'sk-test-only' ) : null );

		return new ProviderConfigurationService( $registry, new CredentialResolver( $reader, $store ) );
	}

	/**
	 * Require the intended missing resource for genuine RED evidence.
	 */
	private function require_resource(): void {
		self::assertTrue(
			class_exists( ModelReadinessRestResource::class ),
			'ModelReadinessRestResource must exist before Task 5 behavior can pass.'
		);
	}
}
