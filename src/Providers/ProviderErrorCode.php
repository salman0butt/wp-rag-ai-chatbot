<?php
/**
 * Provider error code.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

/**
 * Stable provider-neutral error categories.
 */
enum ProviderErrorCode: string {
	case CONFIGURATION = 'configuration';
	case AUTHENTICATION = 'authentication';
	case AUTHORIZATION = 'authorization';
	case RATE_LIMIT = 'rate_limit';
	case TIMEOUT = 'timeout';
	case TRANSPORT = 'transport';
	case MALFORMED_RESPONSE = 'malformed_response';
	case UNSUPPORTED_CAPABILITY = 'unsupported_capability';
	case UPSTREAM_SERVER = 'upstream_server';
	case UNKNOWN = 'unknown';
}
