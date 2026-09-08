<?php
/**
 * Provider credential REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Admin\Rest\ProviderCredentialRestResource;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\Credentials\Secret;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Verifies write-only provider credential administration behavior.
 */
final class ProviderCredentialRestResourceTest extends TestCase {
	/**
	 * Configured reads expose only safe state/source metadata.
	 */
	public function test_read_reports_configuration_without_secret_material(): void {
		$this->require_resource();

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( null );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( new Secret( 'sk-provider-secret' ) );

		$response = ( new ProviderCredentialRestResource( $reader, $store ) )->read( ProviderIds::OPENAI_DIRECT );

		self::assertSame( true, $response['configured'] );
		self::assertSame( 'option', $response['source'] );
		self::assertArrayNotHasKey( 'credential', $response );
		self::assertArrayNotHasKey( 'secret', $response );
		self::assertArrayNotHasKey( 'ciphertext', $response );
	}

	/**
	 * Writes persist the submitted credential but never echo it.
	 */
	public function test_write_persists_secret_without_echoing_it(): void {
		$this->require_resource();

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$store->expects( self::once() )->method( 'save' )->with( ProviderIds::OPENROUTER_DIRECT, 'sk-new-secret' );

		$response = ( new ProviderCredentialRestResource( $reader, $store ) )->write(
			ProviderIds::OPENROUTER_DIRECT,
			array( 'credential' => 'sk-new-secret' )
		);

		self::assertSame( true, $response['managed'] );
		self::assertArrayNotHasKey( 'credential', $response );
		self::assertArrayNotHasKey( 'secret', $response );
	}

	/**
	 * Deletes reset only the plugin-managed credential.
	 */
	public function test_delete_resets_managed_credential(): void {
		$this->require_resource();

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$store->expects( self::once() )->method( 'delete' )->with( ProviderIds::OPENAI_DIRECT );

		$response = ( new ProviderCredentialRestResource( $reader, $store ) )->delete( ProviderIds::OPENAI_DIRECT );

		self::assertSame( false, $response['managed'] );
	}

	/**
	 * Unsafe storage failures are normalized before crossing REST boundaries.
	 */
	public function test_storage_failures_are_normalized_without_sensitive_messages(): void {
		$this->require_resource();

		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$store->method( 'save' )->willThrowException( new RuntimeException( 'sk-secret-internal-storage-error' ) );

		$response = ( new ProviderCredentialRestResource( $reader, $store ) )->write(
			ProviderIds::OPENAI_DIRECT,
			array( 'credential' => 'sk-secret' )
		);

		self::assertSame( 'credential_operation_failed', $response['error']['code'] );
		self::assertSame( 'Provider credential operation failed.', $response['error']['message'] );
	}

	/**
	 * Invalid provider IDs fail with a stable safe error.
	 */
	public function test_invalid_provider_is_normalized(): void {
		$this->require_resource();

		$reader   = $this->createMock( CredentialSourceReader::class );
		$store    = $this->createMock( CredentialStore::class );
		$response = ( new ProviderCredentialRestResource( $reader, $store ) )->read( 'unsupported-provider' );

		self::assertSame( 'invalid_provider', $response['error']['code'] );
	}

	/**
	 * Require the intended missing resource for genuine RED evidence.
	 */
	private function require_resource(): void {
		self::assertTrue(
			class_exists( ProviderCredentialRestResource::class ),
			'ProviderCredentialRestResource must exist before Task 4 behavior can pass.'
		);
	}
}
