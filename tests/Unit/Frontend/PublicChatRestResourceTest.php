<?php
/**
 * Public chat REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Frontend\BotRetrievalBindingRepository;
use WpRagAiChatbot\Frontend\ProductionPublicChatExecutor;
use WpRagAiChatbot\Frontend\PublicChatAbuseGuard;
use WpRagAiChatbot\Frontend\PublicChatRateLimitStore;
use WpRagAiChatbot\Frontend\PublicChatRequest;
use WpRagAiChatbot\Frontend\PublicChatRestResource;
use WpRagAiChatbot\Frontend\PublicChatRuntime;
use WpRagAiChatbot\Frontend\PublicChatRuntimeResolver;

/**
 * Specifies the cheap-before-expensive public REST execution ordering.
 */
final class PublicChatRestResourceTest extends TestCase {
	/** A denied request must never resolve persisted runtime or compose production chat. */
	public function test_rate_limit_denial_happens_before_runtime_resolution(): void {
		self::assertTrue( class_exists( PublicChatRestResource::class ), 'PublicChatRestResource is missing.' );

		$store = $this->createMock( PublicChatRateLimitStore::class );
		$store->expects( self::once() )
			->method( 'consume' )
			->willReturn( false );

		$bots = $this->createMock( BotRepository::class );
		$bots->expects( self::never() )->method( 'find' );

		$bindings = $this->createMock( BotRetrievalBindingRepository::class );
		$bindings->expects( self::never() )->method( 'find' );

		$factory_calls = 0;
		$resource      = new PublicChatRestResource(
			new PublicChatAbuseGuard( $store ),
			new PublicChatRuntimeResolver( $bots, $bindings ),
			static function ( PublicChatRuntime $runtime ) use ( &$factory_calls ): ProductionPublicChatExecutor {
				unset( $runtime );
				++$factory_calls;
				throw new \RuntimeException( 'Executor factory must not run for a denied request.' );
			}
		);

		$request = PublicChatRequest::from_array(
			array(
				'bot_id'   => 'public-bot',
				'question' => 'What is this?',
			)
		);

		self::assertSame(
			array( 'error' => array( 'code' => 'rate_limited' ) ),
			$resource->run( $request, str_repeat( 'a', 64 ) )
		);
		self::assertSame( 0, $factory_calls );
	}
}
