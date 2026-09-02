<?php
/**
 * Provider health status.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

/**
 * Stable provider health/configuration states.
 */
enum ProviderHealthStatus: string {
	case UNAVAILABLE           = 'unavailable';
	case UNCONFIGURED          = 'unconfigured';
	case CONFIGURED            = 'configured';
	case AUTHENTICATION_FAILED = 'authentication_failed';
	case RATE_LIMITED          = 'rate_limited';
	case UPSTREAM_ERROR        = 'upstream_error';
	case HEALTHY               = 'healthy';
}
