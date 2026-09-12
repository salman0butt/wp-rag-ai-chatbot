<?php
/**
 * Playground retrieval capture tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalCapture;
use WpRagAiChatbot\Chat\ChatRetrievalObserver;
use WpRagAiChatbot\Retrieval\RetrievalResult;
use WpRagAiChatbot\Retrieval\RetrievalTrace;

/**
 * Verifies the request-local Playground observer captures the exact production result.
 */
final class PlaygroundRetrievalCaptureTest extends TestCase {
	/** Capture starts empty and exposes the exact observed retrieval object. */
	public function test_capture_exposes_exact_observed_retrieval_result(): void {
		$capture = new PlaygroundRetrievalCapture();
		$result  = new RetrievalResult(
			array(),
			new RetrievalTrace( str_repeat( 'a', 64 ), 8, array() )
		);

		$this->assertInstanceOf( ChatRetrievalObserver::class, $capture );
		$this->assertNull( $capture->result() );

		$capture->observe( $result );

		$this->assertSame( $result, $capture->result() );
	}
}
