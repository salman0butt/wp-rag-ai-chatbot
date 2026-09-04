<?php
/**
 * Embedding normalization modes.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Embeddings;

/**
 * Vector normalization applied before indexing.
 */
enum NormalizationMode: string {
	case NONE = 'none';
	case L2   = 'l2';
}
