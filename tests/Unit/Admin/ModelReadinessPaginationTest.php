<?php
/**
 * Model readiness pagination regression tests.
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
 * Proves onboarding readiness considers every persisted bot page.
 */
final class ModelReadinessPaginationTest extends TestCase {
	/**
	 * A compatible bot after the first bounded repository page still completes onboarding.
	 */
	public function test_readiness_checks_later_bot_pages(): void {
		$provider = $this->createMock( GenerationProvider::class );
		$provider->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
		$provider->method( 'available' )->willReturn( true );

		$catalog = $this->createMock( ModelCatalogProvider::class );
		$catalog->method( 'provider_id' )->willReturn( ProviderIds::OPENAI_DIRECT );
		$catalog->method( 'models' )->willReturn(
			array( new ModelInfo( ProviderIds::OPENAI_DIRECT, 'chat-ready', 'Chat Ready', array( 'text' ), array( 'text' ), array( 'generation' ) ) )
		);

		$registry = new ProviderRegistry();
		$registry->register( ProviderIds::OPENAI_DIRECT, $provider, $catalog );

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( null );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( new Secret( 'sk-test-only' ) );
		$configuration = new ProviderConfigurationService( $registry, new CredentialResolver( $reader, $store ) );

		$incompatible = new Bot(
			new BotId( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
			'Incompatible bot',
			true,
			ProviderIds::OPENAI_DIRECT,
			'other-model',
			1,
			'2026-09-07 10:00:00',
			'2026-09-07 10:00:00'
		);
		$compatible   = new Bot(
			new BotId( 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' ),
			'Ready bot',
			true,
			ProviderIds::OPENAI_DIRECT,
			'chat-ready',
			1,
			'2026-09-07 10:01:00',
			'2026-09-07 10:01:00'
		);

		$bots = $this->createMock( BotRepository::class );
		$bots->expects( self::exactly( 2 ) )
			->method( 'list' )
			->willReturnCallback(
				static function ( int $page, int $per_page ) use ( $incompatible, $compatible ): array {
					self::assertSame( 100, $per_page );
					if ( 1 === $page ) {
						return array(
							'items'    => array_fill( 0, 100, $incompatible ),
							'total'    => 101,
							'page'     => 1,
							'per_page' => 100,
						);
					}

					self::assertSame( 2, $page );
					return array(
						'items'    => array( $compatible ),
						'total'    => 101,
						'page'     => 2,
						'per_page' => 100,
					);
				}
			);

		$resource = new ModelReadinessRestResource( $registry, $configuration, $bots );

		self::assertSame(
			array(
				'ready'     => true,
				'next_step' => 'complete',
			),
			$resource->readiness()
		);
	}
}