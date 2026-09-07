<?php
/**
 * Sanitized chat analytics hook.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

/**
 * Receives normalized text-free chat analytics from the application boundary.
 */
interface ChatAnalyticsHook {
	/**
	 * Record one sanitized analytics event.
	 *
	 * Implementations must not require transcript, evidence, owner-scope, credential,
	 * or raw provider diagnostic data.
	 *
	 * @param ChatAnalyticsEvent $event Safe normalized analytics event.
	 */
	public function record( ChatAnalyticsEvent $event ): void;
}
