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
use WpRagAiChatbot\Providers\OpenAiCompatible\OpenAiCompatibleChatProvider;
use WpRagAiChatbot\Providers\OpenRouter\OpenRouterProvider;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Providers\WordPressAi\WordPressAiClientProvider;

/**
 * Builds provider services without issuing provider requests.
 */
final class ProviderBootstrap {
	private const GEMINI_GENERATION_URL = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
	private const GEMINI_MODELS_URL     = 'https://generativelanguage.googleapis.com/v1beta/openai/models';
	private const GROQ_GENERATION_URL   = 'https://api.groq.com/openai/v1/chat/completions';
	private const GROQ_MODELS_URL       = 'https://api.groq.com/openai/v1/models';

	/**
	 * Composed provider registry.
	 *
	 * @var ProviderRegistry|null
	 */
	private static ?ProviderRegistry $registry = null;

	/**
	 * Composed non-secret provider configuration service.
	 *
	 * @var ProviderConfigurationService|null
	 */
	private static ?ProviderConfigurationService $configuration = null;

	/**
	 * Compose all provider infrastructure once.
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
		$gemini     = new OpenAiCompatibleChatProvider(
			ProviderIds::GEMINI_DIRECT,
			self::GEMINI_GENERATION_URL,
			self::GEMINI_MODELS_URL,
			$credentials,
			$http,
			$redactor
		);
		$groq       = new OpenAiCompatibleChatProvider(
			ProviderIds::GROQ_DIRECT,
			self::GROQ_GENERATION_URL,
			self::GROQ_MODELS_URL,
			$credentials,
			$http,
			$redactor
		);
		$openrouter = new OpenRouterProvider( $credentials, $http, $redactor );
		$core       = new WordPressAiClientProvider( $redactor );

		$registry = new ProviderRegistry();
		$registry->register(
			ProviderIds::OPENAI_DIRECT,
			$openai,
			new CachedModelCatalogProvider( $openai, $cache ),
			$openai
		);
		$registry->register(
			ProviderIds::GEMINI_DIRECT,
			$gemini,
			new CachedModelCatalogProvider( $gemini, $cache )
		);
		$registry->register(
			ProviderIds::GROQ_DIRECT,
			$groq,
			new CachedModelCatalogProvider( $groq, $cache )
		);
		$registry->register(
			ProviderIds::OPENROUTER_DIRECT,
			$openrouter,
			new CachedModelCatalogProvider( $openrouter, $cache ),
			$openrouter
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
