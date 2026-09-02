<?php
/**
 * Provider credential-resolution tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Credentials;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSource;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\Credentials\DirectProviderCredentialConfig;
use WpRagAiChatbot\Providers\Credentials\ResolvedCredential;
use WpRagAiChatbot\Providers\Credentials\Secret;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Verifies fixed credential names and environment-to-option precedence.
 */
final class CredentialResolverTest extends TestCase {
	/**
	 * Direct provider mappings are fixed and unknown providers are rejected.
	 */
	public function test_direct_provider_config_uses_fixed_names(): void {
		$this->require_credential_contracts();

		$openai = DirectProviderCredentialConfig::for_provider( ProviderIds::OPENAI_DIRECT );
		self::assertSame( ProviderIds::OPENAI_DIRECT, $openai->provider_id );
		self::assertSame( 'OPENAI_API_KEY', $openai->environment_name );
		self::assertSame( 'OPENAI_API_KEY', $openai->constant_name );
		self::assertSame( 'wp_rag_ai_openai_api_key', $openai->option_name );

		$openrouter = DirectProviderCredentialConfig::for_provider( ProviderIds::OPENROUTER_DIRECT );
		self::assertSame( 'OPENROUTER_API_KEY', $openrouter->environment_name );
		self::assertSame( 'OPENROUTER_API_KEY', $openrouter->constant_name );
		self::assertSame( 'wp_rag_ai_openrouter_api_key', $openrouter->option_name );

		$this->expectException( InvalidArgumentException::class );
		DirectProviderCredentialConfig::for_provider( 'unsupported' );
	}

	/**
	 * A non-empty environment credential wins and is outer-trimmed.
	 */
	public function test_environment_wins_over_constant_and_option(): void {
		$this->require_credential_contracts();

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( '  env-secret  ' );
		$reader->method( 'constant' )->willReturn( 'constant-secret' );
		$store->expects( self::never() )->method( 'load' );

		$resolved = ( new CredentialResolver( $reader, $store ) )->resolve( ProviderIds::OPENAI_DIRECT );

		self::assertInstanceOf( ResolvedCredential::class, $resolved );
		self::assertSame( CredentialSource::ENVIRONMENT, $resolved->source );
		self::assertSame( 'env-secret', $this->reveal( $resolved->secret ) );
	}

	/**
	 * A blank environment falls through to a non-empty constant.
	 */
	public function test_blank_environment_falls_through_to_constant(): void {
		$this->require_credential_contracts();

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( " \t\n " );
		$reader->method( 'constant' )->willReturn( '  constant-secret  ' );
		$store->expects( self::never() )->method( 'load' );

		$resolved = ( new CredentialResolver( $reader, $store ) )->resolve( ProviderIds::OPENROUTER_DIRECT );

		self::assertInstanceOf( ResolvedCredential::class, $resolved );
		self::assertSame( CredentialSource::CONSTANT, $resolved->source );
		self::assertSame( 'constant-secret', $this->reveal( $resolved->secret ) );
	}

	/**
	 * Blank runtime sources fall through to encrypted option storage.
	 */
	public function test_blank_runtime_sources_fall_through_to_option_then_none(): void {
		$this->require_credential_contracts();

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( null );
		$reader->method( 'constant' )->willReturn( '   ' );
		$store->method( 'load' )->willReturnOnConsecutiveCalls( new Secret( 'stored-secret' ), null );
		$resolver = new CredentialResolver( $reader, $store );

		$resolved = $resolver->resolve( ProviderIds::OPENAI_DIRECT );
		self::assertInstanceOf( ResolvedCredential::class, $resolved );
		self::assertSame( CredentialSource::OPTION, $resolved->source );
		self::assertSame( 'stored-secret', $this->reveal( $resolved->secret ) );
		self::assertNull( $resolver->resolve( ProviderIds::OPENAI_DIRECT ) );
	}

	/**
	 * Read secret plaintext only inside its callback boundary for assertions.
	 *
	 * @param Secret $secret Secret under test.
	 */
	private function reveal( Secret $secret ): string {
		$value = '';
		$secret->with_value(
			static function ( string $plaintext ) use ( &$value ): void {
				$value = $plaintext;
			}
		);
		return $value;
	}

	/**
	 * Require the intended missing-contract RED before resolver implementation.
	 */
	private function require_credential_contracts(): void {
		foreach ( array(
			CredentialResolver::class,
			CredentialSource::class,
			CredentialSourceReader::class,
			CredentialStore::class,
			DirectProviderCredentialConfig::class,
			ResolvedCredential::class,
		) as $class_name ) {
			self::assertTrue(
				class_exists( $class_name ) || interface_exists( $class_name ) || enum_exists( $class_name ),
				sprintf( '%s must exist before credential-resolution behavior can pass.', $class_name )
			);
		}
	}
}
