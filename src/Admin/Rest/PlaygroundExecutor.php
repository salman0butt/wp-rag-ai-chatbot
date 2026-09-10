<?php
/**
 * Typed production execution boundary for the administrator Playground.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

/**
 * Executes one validated Playground question through the production chat pipeline.
 */
interface PlaygroundExecutor {
	/**
	 * Execute one Playground question.
	 *
	 * @param string $question Validated administrator question.
	 */
	public function execute( string $question ): PlaygroundExecutionResult;
}
