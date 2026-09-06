<?php
/**
 * RAG grounding mode.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\RAG;

/**
 * Controls whether generation requires deterministic evidence sufficiency.
 */
enum GroundingMode: string {
	case STRICT   = 'strict';
	case ASSISTED = 'assisted';
}
