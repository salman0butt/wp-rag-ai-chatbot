<?php
/**
 * Server-only provider secret value object.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

use Closure;
use JsonSerializable;
use SensitiveParameterValue;

/**
 * Holds plaintext only behind an explicit callback boundary.
 */
final class Secret implements JsonSerializable {
	/**
	 * Plaintext secret protected from ordinary export/serialization surfaces.
	 *
	 * @var SensitiveParameterValue
	 */
	private SensitiveParameterValue $value;

	/**
	 * Create a secret.
	 *
	 * @param string $value Plaintext secret.
	 */
	public function __construct( string $value ) {
		$this->value = new SensitiveParameterValue( $value );
	}

	/**
	 * Execute a consumer with plaintext without returning plaintext.
	 *
	 * @param Closure $consumer Plaintext consumer.
	 */
	public function with_value( Closure $consumer ): void {
		$value = $this->value->getValue();
		if ( ! is_string( $value ) ) {
			return;
		}

		$consumer( $value );
	}

	/**
	 * Return a safe string representation.
	 */
	public function __toString(): string {
		return '[REDACTED]';
	}

	/**
	 * Return a safe JSON representation.
	 */
	public function jsonSerialize(): string {
		return '[REDACTED]';
	}

	/**
	 * Return safe debug metadata.
	 *
	 * @return array{value:string}
	 */
	public function __debugInfo(): array {
		return array( 'value' => '[REDACTED]' );
	}
}
