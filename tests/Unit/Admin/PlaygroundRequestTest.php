<?php
/**
 * M13 Playground request binding tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundRequest;

/**
 * Proves request-level Playground input is limited to persisted selectors plus the question.
 */
final class PlaygroundRequestTest extends TestCase {
	/** Valid persisted selectors and a bounded question are normalized into one request. */
	public function test_accepts_only_explicit_persisted_selectors_and_question(): void {
		$request = PlaygroundRequest::from_array(
			array(
				'bot_id'        => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
				'source_id'     => 7,
				'collection_id' => 'production-rag',
				'question'      => '  What is the refund policy?  ',
			)
		);

		self::assertNotNull( $request );
		self::assertSame( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $request->bot_id );
		self::assertSame( 7, $request->source_id );
		self::assertSame( 'production-rag', $request->collection_id );
		self::assertSame( 'What is the refund policy?', $request->question );
	}

	/** Arbitrary runtime/provider/retrieval overrides must fail closed. */
	public function test_rejects_unknown_request_level_overrides(): void {
		$request = PlaygroundRequest::from_array(
			array(
				'bot_id'        => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
				'source_id'     => 7,
				'collection_id' => 'production-rag',
				'question'      => 'What is the refund policy?',
				'provider_id'   => 'request-override',
			)
		);

		self::assertNull( $request );
	}

	/** Missing, malformed, or unbounded selectors/questions must fail closed. */
	public function test_rejects_malformed_or_unbounded_input(): void {
		self::assertNull( PlaygroundRequest::from_array( null ) );
		self::assertNull(
			PlaygroundRequest::from_array(
				array(
					'bot_id'        => '',
					'source_id'     => 0,
					'collection_id' => '',
					'question'      => '',
				)
			)
		);
		self::assertNull(
			PlaygroundRequest::from_array(
				array(
					'bot_id'        => str_repeat( 'a', 257 ),
					'source_id'     => 7,
					'collection_id' => str_repeat( 'c', 257 ),
					'question'      => str_repeat( 'q', 16385 ),
				)
			)
		);
	}
}
