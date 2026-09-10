<?php
/**
 * Typed production execution result for the administrator Playground.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Chat\ChatResult;
use WpRagAiChatbot\Debug\DebugTrace;

/**
 * Carries only production chat output, redacted retrieval diagnostics, and explicit request metadata.
 */
final readonly class PlaygroundExecutionResult {
	/**
	 * Create one typed Playground execution result.
	 *
	 * @param ChatResult $chat Normalized M11 chat result.
	 * @param DebugTrace $debug Redacted Task 5 retrieval trace.
	 * @param string     $model_id Explicit selected model identifier.
	 * @param int        $latency_ms Total request latency in milliseconds.
	 */
	public function __construct(
		public ChatResult $chat,
		public DebugTrace $debug,
		public string $model_id,
		public int $latency_ms
	) {
	}
}
