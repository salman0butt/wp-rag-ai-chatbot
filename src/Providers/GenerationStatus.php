<?php
/**
 * Normalized generation status.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

/**
 * Provider-neutral generation completion state.
 */
enum GenerationStatus: string {
	case COMPLETED = 'completed';
	case INCOMPLETE = 'incomplete';
	case FAILED = 'failed';
	case UNKNOWN = 'unknown';
}
