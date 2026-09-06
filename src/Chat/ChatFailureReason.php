<?php
/**
 * Stable chat failure reasons.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

/**
 * Application-level failure categories safe for transport mapping.
 */
enum ChatFailureReason: string {
	case INVALID_REQUEST        = 'invalid_request';
	case CONVERSATION_NOT_FOUND = 'conversation_not_found';
	case CONVERSATION_FORBIDDEN = 'conversation_forbidden';
	case RATE_LIMITED           = 'rate_limited';
	case BUDGET_EXCEEDED        = 'budget_exceeded';
	case RETRIEVAL_UNAVAILABLE  = 'retrieval_unavailable';
	case INSUFFICIENT_EVIDENCE  = 'insufficient_evidence';
	case GENERATION_UNAVAILABLE = 'generation_unavailable';
	case GENERATION_FAILED      = 'generation_failed';
	case INVALID_CITATIONS      = 'invalid_citations';
	case PERSISTENCE_FAILED     = 'persistence_failed';
	case CANCELLED              = 'cancelled';
}
