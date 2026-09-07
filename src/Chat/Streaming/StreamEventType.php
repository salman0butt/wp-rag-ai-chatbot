<?php
/**
 * Normalized streaming event types.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat\Streaming;

/**
 * Stable provider-neutral stream event vocabulary for M11.
 */
enum StreamEventType: string {
	case MESSAGE_START    = 'message.start';
	case MESSAGE_DELTA    = 'message.delta';
	case CITATION         = 'citation';
	case MESSAGE_COMPLETE = 'message.complete';
	case ERROR            = 'error';
}
