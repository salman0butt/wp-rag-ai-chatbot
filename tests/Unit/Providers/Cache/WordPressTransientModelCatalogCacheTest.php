<?php
/**
 * WordPress transient model catalog cache tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Cache;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Cache\WordPressTransientModelCatalogCache;
use WpRagAiChatbot\Providers\ModelInfo;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Verifies fixed-key transient persistence for normalized model catalogs.
 */
final class WordPressTransientModelCatalogCacheTest extends TestCase {
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
	 * Valid transient arrays are reconstructed into normalized model objects.
	 */
	public function test_get_reconstructs_models_from_valid_transient(): void {
		$this->require_cache();
		Functions\expect( 'get_transient' )
			->once()
			->with( 'wp_rag_ai_models_openai_direct_v1' )
			->andReturn( array( $this->model_payload( ProviderIds::OPENAI_DIRECT, 'gpt-test' ) ) );

		$models = ( new WordPressTransientModelCatalogCache() )->get( ProviderIds::OPENAI_DIRECT );

		self::assertIsArray( $models );
		self::assertCount( 1, $models );
		self::assertInstanceOf( ModelInfo::class, $models[0] );
		self::assertSame( 'gpt-test', $models[0]->model_id );
		self::assertSame( array( 'text' ), $models[0]->input_modalities );
		self::assertSame( array( 'temperature' ), $models[0]->capabilities );
		self::assertSame( 128000, $models[0]->context_window );
		self::assertSame( array( 'owned_by' => 'provider' ), $models[0]->provider_metadata );
	}

	/**
	 * Missing transients are cache misses.
	 */
	public function test_get_returns_null_when_transient_is_missing(): void {
		$this->require_cache();
		Functions\expect( 'get_transient' )
			->once()
			->with( 'wp_rag_ai_models_openrouter_direct_v1' )
			->andReturn( false );

		self::assertNull( ( new WordPressTransientModelCatalogCache() )->get( ProviderIds::OPENROUTER_DIRECT ) );
	}

	/**
	 * Model catalogs use the exact 900-second transient TTL.
	 */
	public function test_put_serializes_models_with_exact_ttl(): void {
		$this->require_cache();
		$model = $this->model( ProviderIds::OPENAI_DIRECT, 'gpt-test' );
		Functions\expect( 'set_transient' )
			->once()
			->with(
				'wp_rag_ai_models_openai_direct_v1',
				array( $this->model_payload( ProviderIds::OPENAI_DIRECT, 'gpt-test' ) ),
				900
			)
			->andReturn( true );

		( new WordPressTransientModelCatalogCache() )->put( ProviderIds::OPENAI_DIRECT, array( $model ) );
		self::addToAssertionCount( 1 );
	}

	/**
	 * Invalidation removes only the fixed provider transient.
	 */
	public function test_invalidate_deletes_fixed_provider_transient(): void {
		$this->require_cache();
		Functions\expect( 'delete_transient' )
			->once()
			->with( 'wp_rag_ai_models_openrouter_direct_v1' )
			->andReturn( true );

		( new WordPressTransientModelCatalogCache() )->invalidate( ProviderIds::OPENROUTER_DIRECT );
		self::addToAssertionCount( 1 );
	}

	/**
	 * Malformed cached data is deleted and treated as a cache miss.
	 */
	public function test_get_deletes_malformed_transient_and_returns_null(): void {
		$this->require_cache();
		Functions\expect( 'get_transient' )
			->once()
			->with( 'wp_rag_ai_models_openai_direct_v1' )
			->andReturn( array( array( 'provider_id' => ProviderIds::OPENAI_DIRECT ) ) );
		Functions\expect( 'delete_transient' )
			->once()
			->with( 'wp_rag_ai_models_openai_direct_v1' )
			->andReturn( true );

		self::assertNull( ( new WordPressTransientModelCatalogCache() )->get( ProviderIds::OPENAI_DIRECT ) );
	}

	/**
	 * Build one normalized model fixture.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id Model identifier.
	 */
	private function model( string $provider_id, string $model_id ): ModelInfo {
		return new ModelInfo(
			$provider_id,
			$model_id,
			'Test Model',
			array( 'text' ),
			array( 'text' ),
			array( 'temperature' ),
			128000,
			array( 'owned_by' => 'provider' )
		);
	}

	/**
	 * Build the plain normalized transient payload for one model.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id Model identifier.
	 * @return array<string, mixed>
	 */
	private function model_payload( string $provider_id, string $model_id ): array {
		return array(
			'provider_id'       => $provider_id,
			'model_id'          => $model_id,
			'display_name'      => 'Test Model',
			'input_modalities'  => array( 'text' ),
			'output_modalities' => array( 'text' ),
			'capabilities'      => array( 'temperature' ),
			'context_window'    => 128000,
			'provider_metadata' => array( 'owned_by' => 'provider' ),
		);
	}

	/**
	 * Require the intended missing-class RED before cache implementation.
	 */
	private function require_cache(): void {
		self::assertTrue(
			class_exists( WordPressTransientModelCatalogCache::class ),
			'WordPressTransientModelCatalogCache must exist before transient cache behavior can pass.'
		);
	}
}
