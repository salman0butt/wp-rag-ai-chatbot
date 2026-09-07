<?php
/**
 * Stable bot identifier.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Bots;

use InvalidArgumentException;

/**
 * Canonical 128-bit lowercase hexadecimal bot identifier.
 */
final class BotId {
	/**
	 * Create a bot identifier.
	 *
	 * @param string $value Canonical lowercase hexadecimal identifier.
	 */
	public function __construct( public readonly string $value ) {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $value ) ) {
			throw new InvalidArgumentException( 'Bot identifier must be 32 lowercase hexadecimal characters.' );
		}
	}
}
