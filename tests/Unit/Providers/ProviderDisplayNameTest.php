<?php
/**
 * Provider display-name tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\ProviderConfigurationService;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\ProviderRegistry;

/**
 * Verifies the admin-facing provider catalog uses simple human-readable names.
 */
final class ProviderDisplayNameTest extends TestCase {
	/**
	 * Direct provider descriptors expose the same names shown in the admin UI.
	 */
	public function test_direct_provider_display_names_are_friendly(): void {
		$registry = new ProviderRegistry();
		$expected = array(
			ProviderIds::OPENAI_DIRECT     => 'OpenAI',
			ProviderIds::GEMINI_DIRECT     => 'Google Gemini',
			ProviderIds::GROQ_DIRECT       => 'Groq',
			ProviderIds::OPENROUTER_DIRECT => 'OpenRouter',
		);

		foreach ( array_keys( $expected ) as $provider_id ) {
			$provider = $this->createMock( GenerationProvider::class );
			$provider->method( 'provider_id' )->willReturn( $provider_id );
			$registry->register( $provider_id, $provider );
		}

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( null );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( null );
		$service = new ProviderConfigurationService(
			$registry,
			new CredentialResolver( $reader, $store )
		);

		foreach ( $expected as $provider_id => $display_name ) {
			self::assertSame( $display_name, $service->describe( $provider_id )->display_name );
		}
	}
}
