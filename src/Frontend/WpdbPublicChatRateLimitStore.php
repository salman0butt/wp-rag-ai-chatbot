<?php
/**
 * WordPress database-backed public chat rate-limit store.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Database\Connection;

/**
 * Atomically consumes opaque public-chat buckets in the site options table.
 */
final readonly class WpdbPublicChatRateLimitStore implements PublicChatRateLimitStore {
	/**
	 * Create one rate-limit store.
	 *
	 * @param Connection $connection Existing WordPress database connection.
	 */
	public function __construct( private Connection $connection ) {
	}

	/**
	 * Consume one request from an opaque bucket using one atomic upsert.
	 *
	 * The stored value is `window_expires_unix:count`. MySQL's unique index on
	 * `option_name` serializes concurrent insert/update attempts for this bucket.
	 * A denied request leaves the row unchanged, producing zero affected rows.
	 *
	 * @param string $bucket Opaque storage key without raw client identity.
	 * @param int    $limit Maximum allowed requests in the window.
	 * @param int    $window_seconds Window length in seconds.
	 */
	public function consume( string $bucket, int $limit, int $window_seconds ): bool {
		if ( '' === $bucket || strlen( $bucket ) > 191 || $limit < 1 || $window_seconds < 1 ) {
			return false;
		}

		$prefix = $this->connection->prefix();
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_]+\z/D', $prefix ) ) {
			return false;
		}

		$table      = $prefix . 'options';
		$now        = time();
		$expires_at = $now + $window_seconds;

		/**
		 * Atomic options-table upsert.
		 *
		 * @var literal-string $sql
		 */
		$sql = "INSERT INTO {$table} (option_name, option_value, autoload)\n"
			. "VALUES (%s, CONCAT(%d, ':1'), 'no')\n"
			. 'ON DUPLICATE KEY UPDATE option_value = CASE '
			. "WHEN CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) <= %d THEN VALUES(option_value) "
			. "WHEN CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) < %d THEN "
			. "CONCAT(SUBSTRING_INDEX(option_value, ':', 1), ':', CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) + 1) "
			. 'ELSE option_value END';

		$prepared = $this->connection->prepare( $sql, $bucket, $expires_at, $now, $limit );
		$affected = $this->connection->query( $prepared );

		return is_int( $affected ) && $affected > 0;
	}
}
