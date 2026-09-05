<?php
/**
 * Database schema constants.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Defines the current plugin database schema identity.
 */
final class DatabaseSchema {
	public const VERSION            = 5;
	public const VERSION_OPTION     = 'wp_rag_ai_db_version';
	public const DELETE_DATA_OPTION = 'wp_rag_ai_delete_data_on_uninstall';

	/**
	 * Static constants only.
	 */
	private function __construct() {
	}
}
