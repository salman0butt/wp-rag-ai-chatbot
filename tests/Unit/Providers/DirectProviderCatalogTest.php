<?php
/**
 * Direct provider catalog tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\DirectProviderCredentialConfig;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Verifies user-facing direct providers have stable credential contracts.
 */
final class DirectProviderCatalogTest extends TestCase {
	/**
	 * Gemini and Groq expose stable provider IDs and WordPress-safe credentials.
	 */
	public function test_gemini_and_groq_direct_provider_credentials(): void {
		$gemini = DirectProviderCredentialConfig::for_provider( ProviderIds::GEMINI_DIRECT );
		$groq   = DirectProviderCredentialConfig::for_provider( ProviderIds::GROQ_DIRECT );

		self::assertSame( 'gemini_direct', $gemini->provider_id );
		self::assertSame( 'GEMINI_API_KEY', $gemini->environment_name );
		self::assertSame( 'GEMINI_API_KEY', $gemini->constant_name );
		self::assertSame( 'wp_rag_ai_gemini_api_key', $gemini->option_name );

		self::assertSame( 'groq_direct', $groq->provider_id );
		self::assertSame( 'GROQ_API_KEY', $groq->environment_name );
		self::assertSame( 'GROQ_API_KEY', $groq->constant_name );
		self::assertSame( 'wp_rag_ai_groq_api_key', $groq->option_name );
	}
}
