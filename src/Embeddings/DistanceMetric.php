<?php
/**
 * Vector distance metrics.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Embeddings;

/**
 * Distance/similarity metric forming part of index compatibility.
 */
enum DistanceMetric: string {
	case COSINE      = 'cosine';
	case DOT_PRODUCT = 'dot-product';
}
