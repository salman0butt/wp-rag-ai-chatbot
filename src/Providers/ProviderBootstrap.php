<?php
/**
 * Provider runtime composition root.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use LogicException;
use WpRagAiChatbot\Providers\Cache\CachedModelCatalogProvider;
use WpRagAiChatbot\Providers\Cache\WordPressTransientModelCatalogCache;
use WpRagAiChatbot\Providers\Credentials\AuthenticatedCredentialCipher;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\RuntimeCredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\RuntimeCryptoCapabilities;
use WpRagAiChatbot\Providers\Credentials\WordPressCredentialStore;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\Http\WordPressHttpTransport;
use WpRagAiChatbot\Providers\OpenAI\OpenAiProvider;
use WpRagAiChatbot\Providers\OpenRouter\OpenRouterProvider;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Providers\WordPressAi\WordPressAiClientProvider;

/**
 * Builds provider services without issuing provider requests.
 */
final class ProviderBootstrap {
	/**
	 * Composed provider registry.
	 */
	private static ?ProviderRegistry $registry = null;

	/**
	 * Composed non-secret provider configuration service.
	 */
	private static ?ProviderConfigurationService $configuration = null;

	/**
	 * Compose all M03 provider infrastructure once.
	 */
	public static function register(): void {
		if ( null !== self::$registry ) {
			return;
		}

		$redactor    = new SecretRedactor();
		$transport   = new WordPressHttpTransport();
		$http        = new ProviderHttpClient( $transport );
		$cipher      = new AuthenticatedCredentialCipher( new RuntimeCryptoCapabilities() );
		$store       = new WordPressCredentialStore( $cipher );
		$credentials = new CredentialResolver( new RuntimeCredentialSourceReader(), $store );
		$cache       = new WordPressTransientModelCatalogCache();

		$openai     = new OpenAiProvider( $credentials, $http, $redactor );
		$openrouter = new OpenRouterProvider( $credentials, $http, $redactor );
		$core       = new WordPressAiClientProvider( $redactor );

		$registry = new ProviderRegistry();
		$registry->register(
			ProviderIds::OPENAI_DIRECT,
			$openai,
			new CachedModelCatalogProvider( $openai, $cache )
		);
		$registry->register(
			ProviderIds::OPENROUTER_DIRECT,
			$openrouter,
			new CachedModelCatalogProvider( $openrouter, $cache )
		);
		$registry->register( ProviderIds::WORDPRESS_AI_CLIENT, $core );

		self::$registry      = $registry;
		self::$configuration = new ProviderConfigurationService( $registry, $credentials );
	}

	/**
	 * Return the composed provider registry.
	 *
	 * @throws LogicException When provider infrastructure has not been registered.
	 */
	public static function registry(): ProviderRegistry {
		if ( null === self::$registry ) {
			throw new LogicException( 'Provider infrastructure has not been registered.' );
		}
		return self::$registry;
	}

	/**
	 * Return the composed non-secret configuration service.
	 *
	 * @throws LogicException When provider infrastructure has not been registered.
	 */
	public static function configuration(): ProviderConfigurationService {
		if ( null === self::$configuration ) {
			throw new LogicException( 'Provider infrastructure has not been registered.' );
		}
		return self::$configuration;
	}
}
