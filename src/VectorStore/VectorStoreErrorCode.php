<?php
/**
 * Vector-store error codes.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

/**
 * Stable infrastructure-neutral error categories.
 */
enum VectorStoreErrorCode: string {
	case INVALID_REQUEST        = 'invalid_request';
	case INCOMPATIBLE_PROFILE   = 'incompatible_profile';
	case UNSUPPORTED_CAPABILITY = 'unsupported_capability';
	case UNAVAILABLE            = 'unavailable';
	case LOCAL_SCALE_LIMIT      = 'local_scale_limit';
	case OPERATION_FAILED       = 'operation_failed';
}
