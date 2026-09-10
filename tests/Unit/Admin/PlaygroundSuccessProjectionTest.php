<?php
/**
 * M13 Playground success projection tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundExecutionResult;
use WpRagAiChatbot\Admin\Rest\PlaygroundExecutor;
use WpRagAiChatbot\Chat\ChatResult;
use WpRagAiChatbot\Citations\Citation;
use WpRagAiChatbot\Debug\DebugTrace;
use WpRagAiChatbot\Providers\Usage;

/**
 * Verifies successful Playground responses expose only explicit safe fields.
 */
final class PlaygroundSuccessProjectionTest extends TestCase {
	/** Successful execution must not recursively expose arbitrary application values. */
	public function test_run_projects_only_allow_listed_success_fields(): void {
		$citation = new Citation(
			'C1',
			'chunk-1',
			'document-1',
			8,
			'Refund policy',
			'https://example.test/refunds'
		);
		$chat     = new ChatResult(
			'Refunds are available. [C1]',
			false,
			new Usage( 10, 5, 15, array( 'provider_secret' => 'USAGE-SECRET-SENTINEL' ) ),
			array( $citation, array( 'secret' => 'CITATION-SECRET-SENTINEL' ) ),
			'conversation-secret',
			'message-secret'
		);
		$trace    = new DebugTrace(
			str_repeat( 'a', 64 ),
			28,
			array(
				'semantic' => 1,
				'lexical'  => 1,
			),
			array(),
			'applied',
			array()
		);
		$result   = new PlaygroundExecutionResult( $chat, $trace, 'model-test', 25 );
		$executor = new class( $result ) implements PlaygroundExecutor {
			/**
			 * Store the typed result.
			 *
			 * @param PlaygroundExecutionResult $result Typed execution result.
			 */
			public function __construct( private readonly PlaygroundExecutionResult $result ) {
			}

			/**
			 * Return the typed execution result.
			 *
			 * @param string $question Question input.
			 */
			public function execute( string $question ): PlaygroundExecutionResult {
				unset( $question );

				return $this->result;
			}
		};
		$class    = 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundRestResource';
		self::assertTrue( class_exists( $class ) );
		$resource = new $class( $executor );
		$response = call_user_func( array( $resource, 'run' ), 'What is the refund policy?' );
		self::assertIsArray( $response );

		self::assertSame(
			array(
				'ok'          => true,
				'answer'      => 'Refunds are available. [C1]',
				'no_answer'   => false,
				'citations'   => array(
					array(
						'id'            => 'C1',
						'chunk_id'      => 'chunk-1',
						'document_id'   => 'document-1',
						'source_id'     => 8,
						'title'         => 'Refund policy',
						'canonical_url' => 'https://example.test/refunds',
					),
				),
				'model_id'    => 'model-test',
				'latency_ms'  => 25,
				'usage'       => array(
					'input_tokens'  => 10,
					'output_tokens' => 5,
					'total_tokens'  => 15,
				),
				'debug_trace' => $trace->to_array(),
			),
			$response
		);

		$serialized = wp_json_encode( $response );
		self::assertIsString( $serialized );
		self::assertStringNotContainsString( 'USAGE-SECRET-SENTINEL', $serialized );
		self::assertStringNotContainsString( 'CITATION-SECRET-SENTINEL', $serialized );
		self::assertStringNotContainsString( 'conversation-secret', $serialized );
		self::assertStringNotContainsString( 'message-secret', $serialized );
	}
}
