<?php
/**
 * Public production-executor resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Chat\ChatRequest;
use WpRagAiChatbot\Chat\ChatResponder;
use WpRagAiChatbot\Chat\ChatResult;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\PublicChatAccessContextResolver;
use WpRagAiChatbot\Frontend\PublicChatProductionExecutorResolver;
use WpRagAiChatbot\Frontend\PublicChatRequest;
use WpRagAiChatbot\Frontend\PublicChatRuntime;
use WpRagAiChatbot\Retrieval\Lexical\ChunkLookupStore;

/**
 * Specifies the thin persisted-policy bridge from runtime to the existing M11 responder graph.
 */
final class PublicChatProductionExecutorResolverTest extends TestCase {
	/** Persisted model/access authority is supplied to one production executor without caller overrides. */
	public function test_resolves_executor_from_persisted_runtime_and_existing_responder(): void {
		self::assertTrue( class_exists( PublicChatProductionExecutorResolver::class ), 'PublicChatProductionExecutorResolver is missing.' );

		$runtime = new PublicChatRuntime(
			new Bot(
				new BotId( '0123456789abcdef0123456789abcdef' ),
				'Support bot',
				true,
				'openai',
				'gpt-4.1-mini',
				1,
				'2026-09-12 00:00:00',
				'2026-09-12 00:00:00'
			),
			new BotRetrievalBinding( 42, 'support-en-v1' )
		);
		$request = PublicChatRequest::from_array(
			array(
				'bot_id'   => '0123456789abcdef0123456789abcdef',
				'question' => 'How do I reset my password?',
			)
		);

		$responder = $this->createMock( ChatResponder::class );
		$responder->expects( self::once() )
			->method( 'respond' )
			->with(
				self::callback(
					static fn ( ChatRequest $chat ): bool => 'gpt-4.1-mini' === $chat->model_id
						&& 'How do I reset my password?' === $chat->question
				),
				self::callback(
					static fn ( $access ): bool => 'public:0123456789abcdef0123456789abcdef' === $access->owner_scope
						&& 42 === $access->lexical_filter->source_id
						&& 'support-en-v1' === $access->lexical_filter->collection_id
				)
			)
			->willReturn( new ChatResult( 'Use Account Settings.', false, null, array(), null ) );

		$factory_calls = 0;
		$factory       = static function ( PublicChatRuntime $received ) use ( $runtime, $responder, &$factory_calls ): ChatResponder {
			++$factory_calls;
			self::assertSame( $runtime, $received );
			return $responder;
		};
		$chunks        = $this->createMock( ChunkLookupStore::class );
		$resolver      = new PublicChatProductionExecutorResolver(
			$factory,
			new PublicChatAccessContextResolver( $chunks )
		);

		$response = $resolver->resolve( $runtime )->execute( $request );

		self::assertSame( 1, $factory_calls );
		self::assertSame( 'Use Account Settings.', $response->answer );
	}
}
