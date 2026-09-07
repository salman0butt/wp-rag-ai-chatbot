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
		$this->requireText( $name, 'Bot name' );
		$this->requireText( $provider_id, 'Provider identifier' );
		$this->requireText( $model_id, 'Model identifier' );
		$this->requireText( $created_at, 'Created timestamp' );
		$this->requireText( $updated_at, 'Updated timestamp' );
		if ( $version < 1 ) {
			throw new InvalidArgumentException( 'Bot version must be positive.' );
		}
	}

	/**
	 * Require non-blank bounded persisted text.
	 *
	 * @param string $value Raw value.
	 * @param string $label Validation label.
	 */
	private function requireText( string $value, string $label ): void {
		if ( '' === trim( $value ) ) {
			throw new InvalidArgumentException( $label . ' must not be blank.' );
		}
		if ( strlen( $value ) > self::MAX_TEXT_BYTES ) {
			throw new InvalidArgumentException( $label . ' exceeds the persistence limit.' );
		}
	}
}
