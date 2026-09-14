<?php
/**
 * Bounded administrator conversation list request parser.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use DateTimeImmutable;
use InvalidArgumentException;
use WpRagAiChatbot\Conversations\ConversationListQuery;

/** Converts REST query parameters into the existing normalized conversation query authority. */
final class ConversationListRequest {
	private const MAX_PAGE_SIZE = 100;
	private const DATE_FORMAT   = 'Y-m-d H:i:s';

	/**
	 * Parse one administrator conversation-list request.
	 *
	 * @param array<string,mixed> $input Raw REST query parameters.
	 */
	public static function from_array( array $input ): ?ConversationListQuery {
		$page     = self::positive_int( $input['page'] ?? null, 1 );
		$per_page = self::positive_int( $input['per_page'] ?? null, 25 );
		if ( null === $page || null === $per_page || $per_page > self::MAX_PAGE_SIZE ) {
			return null;
		}

		foreach ( array( 'bot_id', 'date_from', 'date_to', 'search' ) as $key ) {
			if ( array_key_exists( $key, $input ) && null !== $input[ $key ] && ! is_string( $input[ $key ] ) ) {
				return null;
			}
		}

		$unassigned_only = self::boolean( $input['unassigned_only'] ?? null );
		if ( null === $unassigned_only ) {
			return null;
		}

		$bot_id    = self::optional_string( $input['bot_id'] ?? null );
		$date_from = self::optional_string( $input['date_from'] ?? null );
		$date_to   = self::optional_string( $input['date_to'] ?? null );
		$search    = self::optional_string( $input['search'] ?? null );

		if (
			( null !== $date_from && ! self::is_database_datetime( $date_from ) )
			|| ( null !== $date_to && ! self::is_database_datetime( $date_to ) )
		) {
			return null;
		}

		try {
			return new ConversationListQuery(
				$page,
				$per_page,
				$bot_id,
				$unassigned_only,
				$date_from,
				$date_to,
				$search
			);
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Normalize one positive integer without loose numeric coercion.
	 *
	 * @param mixed $value Raw request value.
	 * @param int   $default_value Value used when omitted.
	 */
	private static function positive_int( mixed $value, int $default_value ): ?int {
		if ( null === $value || '' === $value ) {
			return $default_value;
		}

		$validated = filter_var( $value, FILTER_VALIDATE_INT );
		if ( false === $validated || $validated < 1 ) {
			return null;
		}

		return $validated;
	}

	/**
	 * Normalize one explicit query boolean.
	 *
	 * Omission means false; only boolean, 0/1, and true/false string forms are accepted.
	 *
	 * @param mixed $value Raw request value.
	 */
	private static function boolean( mixed $value ): ?bool {
		if ( null === $value || '' === $value ) {
			return false;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( 0 === $value || '0' === $value || 'false' === strtolower( trim( (string) $value ) ) ) {
			return false;
		}

		if ( 1 === $value || '1' === $value || 'true' === strtolower( trim( (string) $value ) ) ) {
			return true;
		}

		return null;
	}

	/**
	 * Normalize one optional string after its scalar type has been validated.
	 *
	 * @param mixed $value Raw request value.
	 */
	private static function optional_string( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( (string) $value );

		return '' === $value ? null : $value;
	}

	/**
	 * Verify a date boundary uses the canonical persisted timestamp format and a real calendar value.
	 *
	 * @param string $value Normalized date boundary.
	 */
	private static function is_database_datetime( string $value ): bool {
		$date = DateTimeImmutable::createFromFormat( '!' . self::DATE_FORMAT, $value );

		return false !== $date && $date->format( self::DATE_FORMAT ) === $value;
	}
}
