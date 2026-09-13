<?php
/**
 * Production chat responder boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

/**
 * Executes one normalized chat request through the production chat graph.
 */
interface ChatResponder {
	/**
	 * Respond to one normalized request using trusted server-side access scope.
	 *
	 * @param ChatRequest       $request Normalized application request.
	 * @param ChatAccessContext $access Trusted server-side owner and retrieval scope.
	 */
	public function respond( ChatRequest $request, ChatAccessContext $access ): ChatResult;
}
