<?php
/**
 * Bot configuration aggregate.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Bots;

use InvalidArgumentException;

/**
 * Persisted M12 bot configuration without future knowledge or appearance fields.
 */
final class Bot {
	private const MAX_TEXT_BYTES = 191;

	/**
	 * Create a bot aggregate.
	 *
	 * @param BotId  $id Stable bot identifier.
	 * @param string $name Human-readable name.
	 * @param bool   $enabled Whether the bot is enabled.
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id Model identifier.
	 * @param int    $version Optimistic persistence version.
	 * @param string $created_at UTC creation timestamp.
	 * @param string $updated_at UTC update timestamp.
	 * @throws InvalidArgumentException When persisted bot data is invalid.
	 */
	public function __construct(
		public readonly BotId $id,
		public readonly string $name,
		public readonly bool $enabled,
		public readonly string $provider_id,
		public readonly string $model_id,
		public readonly int $version,
		public readonly string $created_at,
		public readonly string $updated_at
	) {
		$this->require_text( $name );
		$this->require_text( $provider_id );
		$this->require_text( $model_id );
		$this->require_text( $created_at );
		$this->require_text( $updated_at );
		if ( $version < 1 ) {
			throw new InvalidArgumentException( 'Bot version must be positive.' );
		}
	}

	/**
	 * Require non-blank bounded persisted text.
	 *
	 * @param string $value Raw value.
	 * @throws InvalidArgumentException When a value is blank or too large.
	 */
	private function require_text( string $value ): void {
		if ( '' === trim( $value ) ) {
			throw new InvalidArgumentException( 'Bot text field must not be blank.' );
		}
		if ( strlen( $value ) > self::MAX_TEXT_BYTES ) {
			throw new InvalidArgumentException( 'Bot text field exceeds the persistence limit.' );
		}
	}
}
