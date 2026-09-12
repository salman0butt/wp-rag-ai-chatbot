<?php
/**
 * Playground citation REST boundary tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundExecutionResult;
use WpRagAiChatbot\Admin\Rest\PlaygroundExecutor;
use WpRagAiChatbot\Admin\Rest\PlaygroundRestResource;
use WpRagAiChatbot\Chat\ChatResult;
use WpRagAiChatbot\Citations\Citation;
use WpRagAiChatbot\Debug\DebugTrace;

/**
 * Keeps citation lineage strings bounded when they cross the administrator REST boundary.
 */
final class PlaygroundCitationBoundsTest extends TestCase {
	/** Citation lineage identifiers must use the same scalar bound as Task 5 debug evidence. */
	public function test_bounds_citation_lineage_identifiers(): void {
		$citation = new Citation(
			'C1',
			str_repeat( '界', 100 ),
			str_repeat( 'd', 300 ),
			9,
			'Refund policy',
			'https://example.test/refunds'
		);
		$chat     = new ChatResult( 'Refunds are available. [C1]', false, null, array( $citation ) );
		$trace    = new DebugTrace( str_repeat( 'd', 64 ), 16, array(), array(), 'not_requested', array() );
		$result   = new PlaygroundExecutionResult( $chat, $trace, 'model-test', 3 );
		$executor = new class( $result ) implements PlaygroundExecutor {
			/**
			 * Store the typed execution result.
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

		$response    = ( new PlaygroundRestResource( $executor ) )->run( 'Bound citation lineage.' );
		$chunk_id    = $response['citations'][0]['chunk_id'] ?? null;
		$document_id = $response['citations'][0]['document_id'] ?? null;

		self::assertIsString( $chunk_id );
		self::assertLessThanOrEqual( 256, strlen( $chunk_id ) );
		self::assertSame( 1, preg_match( '//u', $chunk_id ) );
		self::assertIsString( $document_id );
		self::assertLessThanOrEqual( 256, strlen( $document_id ) );
	}
}
