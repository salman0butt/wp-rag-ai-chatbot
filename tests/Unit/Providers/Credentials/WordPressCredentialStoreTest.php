<?php
/**
 * WordPress provider credential store tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Credentials;

use Brain\Monkey;
use Brain\Monkey\Functions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialCipher;
use WpRagAiChatbot\Providers\Credentials\Secret;
use WpRagAiChatbot\Providers\Credentials\WordPressCredentialStore;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Verifies encrypted non-autoloaded provider credential option storage.
 */
final class WordPressCredentialStoreTest extends TestCase {
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
	 * Missing options load as no configured plugin-managed secret.
	 */
	public function test_load_returns_null_when_option_is_missing(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		$cipher->expects( self::never() )->method( 'decrypt' );
		Functions\expect( 'get_option' )
			->once()
			->with( 'wp_rag_ai_openai_api_key', null )
			->andReturn( null );

		self::assertNull( ( new WordPressCredentialStore( $cipher ) )->load( ProviderIds::OPENAI_DIRECT ) );
	}

	/**
	 * Stored envelopes are decrypted behind the Secret boundary.
	 */
	public function test_load_decrypts_string_envelope_and_returns_secret(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		$cipher->expects( self::once() )
			->method( 'decrypt' )
			->with( ProviderIds::OPENROUTER_DIRECT, 'encrypted-envelope' )
			->willReturn( 'decrypted-secret' );
		Functions\expect( 'get_option' )
			->once()
			->with( 'wp_rag_ai_openrouter_api_key', null )
			->andReturn( 'encrypted-envelope' );

		$secret = ( new WordPressCredentialStore( $cipher ) )->load( ProviderIds::OPENROUTER_DIRECT );

		self::assertInstanceOf( Secret::class, $secret );
		self::assertSame( 'decrypted-secret', $this->reveal( $secret ) );
	}

	/**
	 * Corrupt option types fail closed before reaching the cipher.
	 */
	public function test_load_rejects_non_string_option_values(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		$cipher->expects( self::never() )->method( 'decrypt' );
		Functions\expect( 'get_option' )
			->once()
			->with( 'wp_rag_ai_openai_api_key', null )
			->andReturn( array( 'unexpected' ) );

		$this->expect_configuration_exception();
		( new WordPressCredentialStore( $cipher ) )->load( ProviderIds::OPENAI_DIRECT );
	}

	/**
	 * Blank saves delete the option without invoking encryption.
	 */
	public function test_blank_save_deletes_option_without_encrypting(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		$cipher->expects( self::never() )->method( 'encrypt' );
		Functions\expect( 'delete_option' )
			->once()
			->with( 'wp_rag_ai_openai_api_key' )
			->andReturn( true );

		( new WordPressCredentialStore( $cipher ) )->save( ProviderIds::OPENAI_DIRECT, " \t\n " );
		self::addToAssertionCount( 1 );
	}

	/**
	 * New credentials are encrypted and added with autoload disabled.
	 */
	public function test_new_secret_is_encrypted_and_added_without_autoload(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		$cipher->expects( self::once() )
			->method( 'encrypt' )
			->with( ProviderIds::OPENAI_DIRECT, ' plaintext-is-preserved ' )
			->willReturn( 'encrypted-envelope' );
		Functions\expect( 'get_option' )
			->once()
			->with( 'wp_rag_ai_openai_api_key', null )
			->andReturn( null );
		Functions\expect( 'add_option' )
			->once()
			->with( 'wp_rag_ai_openai_api_key', 'encrypted-envelope', '', false )
			->andReturn( true );
		Functions\expect( 'update_option' )->never();

		( new WordPressCredentialStore( $cipher ) )->save( ProviderIds::OPENAI_DIRECT, ' plaintext-is-preserved ' );
		self::addToAssertionCount( 1 );
	}

	/**
	 * A failed new-option write is surfaced as a configuration error.
	 */
	public function test_failed_add_is_rejected(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		$cipher->method( 'encrypt' )->willReturn( 'encrypted-envelope' );
		Functions\expect( 'get_option' )
			->once()
			->with( 'wp_rag_ai_openai_api_key', null )
			->andReturn( null );
		Functions\expect( 'add_option' )
			->once()
			->with( 'wp_rag_ai_openai_api_key', 'encrypted-envelope', '', false )
			->andReturn( false );

		$this->expect_configuration_exception();
		( new WordPressCredentialStore( $cipher ) )->save( ProviderIds::OPENAI_DIRECT, 'secret' );
	}

	/**
	 * Existing options are replaced with autoload disabled.
	 */
	public function test_existing_secret_is_updated_without_autoload(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		$cipher->method( 'encrypt' )->willReturn( 'new-envelope' );
		Functions\expect( 'get_option' )
			->once()
			->with( 'wp_rag_ai_openrouter_api_key', null )
			->andReturn( 'old-envelope' );
		Functions\expect( 'update_option' )
			->once()
			->with( 'wp_rag_ai_openrouter_api_key', 'new-envelope', false )
			->andReturn( true );
		Functions\expect( 'add_option' )->never();

		( new WordPressCredentialStore( $cipher ) )->save( ProviderIds::OPENROUTER_DIRECT, 'secret' );
		self::addToAssertionCount( 1 );
	}

	/**
	 * WordPress false-update no-change semantics are accepted after exact readback.
	 */
	public function test_false_update_is_accepted_when_readback_matches(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		$cipher->method( 'encrypt' )->willReturn( 'desired-envelope' );
		Functions\expect( 'get_option' )
			->twice()
			->with( 'wp_rag_ai_openai_api_key', null )
			->andReturn( 'old-envelope', 'desired-envelope' );
		Functions\expect( 'update_option' )
			->once()
			->with( 'wp_rag_ai_openai_api_key', 'desired-envelope', false )
			->andReturn( false );

		( new WordPressCredentialStore( $cipher ) )->save( ProviderIds::OPENAI_DIRECT, 'secret' );
		self::addToAssertionCount( 1 );
	}

	/**
	 * A false update with stale readback is rejected.
	 */
	public function test_false_update_with_stale_readback_is_rejected(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		$cipher->method( 'encrypt' )->willReturn( 'desired-envelope' );
		Functions\expect( 'get_option' )
			->twice()
			->with( 'wp_rag_ai_openai_api_key', null )
			->andReturn( 'old-envelope' );
		Functions\expect( 'update_option' )
			->once()
			->with( 'wp_rag_ai_openai_api_key', 'desired-envelope', false )
			->andReturn( false );

		$this->expect_configuration_exception();
		( new WordPressCredentialStore( $cipher ) )->save( ProviderIds::OPENAI_DIRECT, 'secret' );
	}

	/**
	 * Delete uses only the fixed provider option name.
	 */
	public function test_delete_removes_fixed_provider_option(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		Functions\expect( 'delete_option' )
			->once()
			->with( 'wp_rag_ai_openrouter_api_key' )
			->andReturn( true );

		( new WordPressCredentialStore( $cipher ) )->delete( ProviderIds::OPENROUTER_DIRECT );
		self::addToAssertionCount( 1 );
	}

	/**
	 * Unsupported providers cannot select arbitrary option names.
	 */
	public function test_unsupported_provider_is_rejected(): void {
		$this->require_store();
		$cipher = $this->createMock( CredentialCipher::class );
		Functions\expect( 'get_option' )->never();

		$this->expectException( InvalidArgumentException::class );
		( new WordPressCredentialStore( $cipher ) )->load( 'unsupported' );
	}

	/**
	 * Read secret plaintext only inside the callback boundary for assertions.
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
	 * Configure the expected normalized storage configuration error.
	 */
	private function expect_configuration_exception(): void {
		$this->expectException( ProviderException::class );
		$this->expectExceptionObject(
			new ProviderException(
				ProviderErrorCode::CONFIGURATION,
				ProviderIds::OPENAI_DIRECT,
				'Provider credential storage configuration is invalid.'
			)
		);
	}

	/**
	 * Require the intended missing-class RED before store implementation.
	 */
	private function require_store(): void {
		self::assertTrue(
			class_exists( WordPressCredentialStore::class ),
			'WordPressCredentialStore must exist before option-store behavior can pass.'
		);
	}
}
