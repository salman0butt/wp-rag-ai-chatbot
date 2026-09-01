<?php
/**
 * Migration execution status.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Non-error migration outcomes.
 */
enum MigrationStatus: string {
	case UP_TO_DATE = 'up_to_date';
	case MIGRATED   = 'migrated';
	case LOCKED     = 'locked';
}
