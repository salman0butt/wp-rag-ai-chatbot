<?php
/**
 * Production public-chat executor tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ChatRequest;
use WpRagAiChatbot\Chat\ChatResponder;
use WpRagAiChatbot\Chat\ChatResult;
use WpRagAiChatbot\Citations\Citation;
use WpRagAiChatbot\Frontend\ProductionPublicChatExecutor;
use WpRagAiChatbot\Frontend\PublicChatCitation;
use WpRagAiChatbot\Frontend\PublicChatRequest;
use WpRagAiChatbot\Frontend\PublicChatResponse;
use WpRagAiChatbot\RAG\GroundingMode;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Specifies the thin public adapter over the existing M11 chat responder.
 */
final class ProductionPublicChatExecutorTest extends TestCase {
	/** Public execution uses persisted policy once and exposes only widget-safe result data. */
	public function test_executes_existing_chat_responder_once_with_server_owned_policy(): void {
		self::assertTrue( interface_exists( ChatResponder::class ), 'ChatResponder is missing.' );
		self::assertTrue( class_exists( ProductionPublicChatExecutor::class ), 'ProductionPublicChatExecutor is missing.' );
		self::assertTrue( class_exists( PublicChatResponse::class ), 'PublicChatResponse is missing.' );
		self::assertTrue( class_exists( PublicChatCitation::class ), 'PublicChatCitation is missing.' );

		$request  = PublicChatRequest::from_array(
			array(
				'bot_id'          => '0123456789abcdef0123456789abcdef',
				'question'        => 'How do I reset my password?',
				'conversation_id' => 'conversation-1',
			)
		);
		$access   = new ChatAccessContext(
			'public:0123456789abcdef0123456789abcdef',
			new SemanticRetrievalContext(
				new RetrievalFilter( null, null, array( 42 ) ),
				static fn ( string $chunk_id ): ?ChunkSearchRecord => '' === $chunk_id ? null : null
			),
			new LexicalFilter( 'support-en-v1', null, 42 ),
			false
		);
		$citation = new Citation(
			'C1',
			'chunk-internal',
			'document-internal',
			42,
			'Password reset',
			'https://example.test/help/password-reset'
		);

		$responder = $this->createMock( ChatResponder::class );
		$responder->expects( self::once() )
			->method( 'respond' )
			->with(
				self::callback(
					static fn ( ChatRequest $chat ): bool =>
						$request->question === $chat->question
						&& 'gpt-4.1-mini' === $chat->model_id
						&& GroundingMode::STRICT === $chat->grounding_mode
						&& $request->conversation_id === $chat->conversation_id
						&& 4096 === $chat->max_output_tokens
				),
				$access
			)
			->willReturn(
				new ChatResult(
					'Reset it from Account Settings. [C1]',
					false,
					null,
					array( $citation ),
					'conversation-1',
					'message-internal'
				)
			);

		$response = ( new ProductionPublicChatExecutor( $responder, $access, 'gpt-4.1-mini' ) )->execute( $request );

		self::assertSame( 'Reset it from Account Settings. [C1]', $response->answer );
		self::assertSame( 'conversation-1', $response->conversation_id );
		self::assertCount( 1, $response->citations );
		self::assertInstanceOf( PublicChatCitation::class, $response->citations[0] );
		self::assertSame( 'C1', $response->citations[0]->id );
		self::assertSame( 'Password reset', $response->citations[0]->title );
		self::assertSame( 'https://example.test/help/password-reset', $response->citations[0]->url );
		self::assertFalse( property_exists( $response, 'usage' ) );
		self::assertFalse( property_exists( $response, 'message_id' ) );
		self::assertFalse( property_exists( $response->citations[0], 'chunk_id' ) );
		self::assertFalse( property_exists( $response->citations[0], 'document_id' ) );
		self::assertFalse( property_exists( $response->citations[0], 'source_id' ) );
	}
}
