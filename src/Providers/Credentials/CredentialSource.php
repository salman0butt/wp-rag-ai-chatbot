<?php
/**
 * Provider credential source.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

/**
 * Stable credential source identifiers.
 */
enum CredentialSource: string {
	case ENVIRONMENT = 'environment';
	case CONSTANT    = 'constant';
	case OPTION      = 'option';
	case CORE        = 'core';
	case NONE        = 'none';
}
