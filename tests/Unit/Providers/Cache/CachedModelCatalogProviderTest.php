<?php
/**
 * Cached model catalog provider tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Cache;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Providers\Cache\CachedModelCatalogProvider;
use WpRagAiChatbot\Providers\Cache\ModelCatalogCache;
use WpRagAiChatbot\Providers\ModelCatalogProvider;
use WpRagAiChatbot\Providers\ModelInfo;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Verifies cache hit, miss, refresh, and invalidation behavior.
 */
final class CachedModelCatalogProviderTest extends TestCase {
	/**
	 * Cached catalogs are returned without calling the upstream provider.
	 */
	public function test_models_returns_valid_cache_hit_without_upstream_call(): void {
		$this->require_decorator();
		$cached_models = array( $this->model( 'cached-model' ) );
		$cache         = $this->createMock( ModelCatalogCache::class );
		$upstream      = $this->createMock( ModelCatalogProvider::class );
		$cache->expects( self::once() )
			->method( 'get' )
			->with( ProviderIds::OPENAI_DIRECT )
			->willReturn( $cached_models );
		$cache->expects( self::never() )->method( 'put' );
		$upstream->expects( self::once() )
			->method( 'provider_id' )
			->willReturn( ProviderIds::OPENAI_DIRECT );
		$upstream->expects( self::never() )->method( 'models' );

		$models = ( new CachedModelCatalogProvider( $upstream, $cache ) )->models();

		self::assertSame( $cached_models, $models );
	}

	/**
	 * Cache misses fetch upstream once and persist the successful catalog.
	 */
	public function test_models_fetches_and_caches_on_miss(): void {
		$this->require_decorator();
		$fresh_models = array( $this->model( 'fresh-model' ) );
		$cache        = $this->createMock( ModelCatalogCache::class );
		$upstream     = $this->createMock( ModelCatalogProvider::class );
		$cache->expects( self::once() )
			->method( 'get' )
			->with( ProviderIds::OPENAI_DIRECT )
			->willReturn( null );
		$cache->expects( self::once() )
			->method( 'put' )
			->with( ProviderIds::OPENAI_DIRECT, $fresh_models );
		$upstream->expects( self::once() )
			->method( 'provider_id' )
			->willReturn( ProviderIds::OPENAI_DIRECT );
		$upstream->expects( self::once() )
			->method( 'models' )
			->willReturn( $fresh_models );

		$models = ( new CachedModelCatalogProvider( $upstream, $cache ) )->models();

		self::assertSame( $fresh_models, $models );
	}

	/**
	 * Forced refresh bypasses the cache read and replaces it after success.
	 */
	public function test_refresh_replaces_cache_only_after_successful_upstream_result(): void {
		$this->require_decorator();
		$fresh_models = array( $this->model( 'refreshed-model' ) );
		$cache        = $this->createMock( ModelCatalogCache::class );
		$upstream     = $this->createMock( ModelCatalogProvider::class );
		$cache->expects( self::never() )->method( 'get' );
		$cache->expects( self::once() )
			->method( 'put' )
			->with( ProviderIds::OPENAI_DIRECT, $fresh_models );
		$upstream->expects( self::once() )
			->method( 'provider_id' )
			->willReturn( ProviderIds::OPENAI_DIRECT );
		$upstream->expects( self::once() )
			->method( 'models' )
			->willReturn( $fresh_models );

		$models = ( new CachedModelCatalogProvider( $upstream, $cache ) )->refresh();

		self::assertSame( $fresh_models, $models );
	}

	/**
	 * Failed forced refreshes preserve the existing cache by avoiding writes.
	 */
	public function test_refresh_rethrows_and_preserves_cache_when_upstream_fails(): void {
		$this->require_decorator();
		$cache    = $this->createMock( ModelCatalogCache::class );
		$upstream = $this->createMock( ModelCatalogProvider::class );
		$cache->expects( self::never() )->method( 'get' );
		$cache->expects( self::never() )->method( 'put' );
		$cache->expects( self::never() )->method( 'invalidate' );
		$upstream->expects( self::once() )
			->method( 'provider_id' )
			->willReturn( ProviderIds::OPENAI_DIRECT );
		$upstream->expects( self::once() )
			->method( 'models' )
			->willThrowException( new RuntimeException( 'Upstream discovery failed.' ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Upstream discovery failed.' );
		( new CachedModelCatalogProvider( $upstream, $cache ) )->refresh();
	}

	/**
	 * Explicit invalidation delegates to the provider-scoped cache entry.
	 */
	public function test_invalidate_deletes_provider_cache_entry(): void {
		$this->require_decorator();
		$cache    = $this->createMock( ModelCatalogCache::class );
		$upstream = $this->createMock( ModelCatalogProvider::class );
		$cache->expects( self::once() )
			->method( 'invalidate' )
			->with( ProviderIds::OPENAI_DIRECT );
		$upstream->expects( self::once() )
			->method( 'provider_id' )
			->willReturn( ProviderIds::OPENAI_DIRECT );

		( new CachedModelCatalogProvider( $upstream, $cache ) )->invalidate();
		self::addToAssertionCount( 1 );
	}

	/**
	 * Build one normalized model fixture.
	 */
	private function model( string $model_id ): ModelInfo {
		return new ModelInfo(
			ProviderIds::OPENAI_DIRECT,
			$model_id,
			'Test Model',
			array( 'text' ),
			array( 'text' )
		);
	}

	/**
	 * Require the intended missing-class RED before decorator implementation.
	 */
	private function require_decorator(): void {
		self::assertTrue(
			class_exists( CachedModelCatalogProvider::class ),
			'CachedModelCatalogProvider must exist before model catalog cache behavior can pass.'
		);
	}
}
