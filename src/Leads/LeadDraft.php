<?php
/**
 * Bounded lead capture draft.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Leads;

use InvalidArgumentException;

/**
 * Immutable normalized lead payload accepted by persistence boundaries.
 */
final readonly class LeadDraft {
	private const MAX_ID_LENGTH     = 64;
	private const MAX_NAME_LENGTH   = 160;
	private const MAX_EMAIL_LENGTH  = 254;
	private const MAX_PHONE_LENGTH  = 64;
	private const MAX_NOTE_LENGTH   = 2000;
	private const MAX_SOURCE_LENGTH = 100;

	/** Conversation identifier validated by the public ownership boundary. */
	public string $conversation_id;

	/** Explicit bot identifier associated with the conversation. */
	public string $bot_id;

	/** Optional visitor name. */
	public ?string $name;

	/** Optional normalized email address. */
	public ?string $email;

	/** Optional visitor phone number. */
	public ?string $phone;

	/** Optional visitor note. */
	public ?string $note;

	/** Optional finite-origin metadata supplied by the capture surface. */
	public ?string $source;

	/**
	 * Create a normalized bounded lead draft.
	 *
	 * @param string      $conversation_id Conversation identifier.
	 * @param string      $bot_id Explicit bot identifier.
	 * @param string|null $name Optional visitor name.
	 * @param string|null $email Optional visitor email.
	 * @param string|null $phone Optional visitor phone.
	 * @param string|null $note Optional visitor note.
	 * @param string|null $source Optional capture-source label.
	 *
	 * @throws InvalidArgumentException When required identities or bounded contact fields are invalid.
	 */
	public function __construct(
		string $conversation_id,
		string $bot_id,
		?string $name = null,
		?string $email = null,
		?string $phone = null,
		?string $note = null,
		?string $source = null
	) {
		$this->conversation_id = self::normalize_required( $conversation_id, self::MAX_ID_LENGTH, 'Conversation identifier' );
		$this->bot_id          = self::normalize_required( $bot_id, self::MAX_ID_LENGTH, 'Bot identifier' );
		$this->name            = self::normalize_optional( $name, self::MAX_NAME_LENGTH, 'Lead name' );
		$this->email           = self::normalize_email( $email );
		$this->phone           = self::normalize_optional( $phone, self::MAX_PHONE_LENGTH, 'Lead phone' );
		$this->note            = self::normalize_optional( $note, self::MAX_NOTE_LENGTH, 'Lead note' );
		$this->source          = self::normalize_optional( $source, self::MAX_SOURCE_LENGTH, 'Lead source' );
	}

	/**
	 * Normalize a required bounded identifier.
	 *
	 * @param string $value Raw value.
	 * @param int    $max_length Maximum Unicode length.
	 * @param string $label Error label.
	 *
	 * @return string
	 */
	private static function normalize_required( string $value, int $max_length, string $label ): string {
		$value = trim( $value );

		if ( '' === $value || mb_strlen( $value ) > $max_length ) {
			throw new InvalidArgumentException( $label . ' is invalid.' );
		}

		return $value;
	}

	/**
	 * Normalize optional bounded text without persisting blank metadata.
	 *
	 * @param string|null $value Raw optional value.
	 * @param int         $max_length Maximum Unicode length.
	 * @param string      $label Error label.
	 *
	 * @return string|null
	 */
	private static function normalize_optional( ?string $value, int $max_length, string $label ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		if ( mb_strlen( $value ) > $max_length ) {
			throw new InvalidArgumentException( $label . ' exceeds its maximum length.' );
		}

		return $value;
	}

	/**
	 * Normalize and validate an optional email address.
	 *
	 * @param string|null $email Raw optional email.
	 *
	 * @return string|null
	 */
	private static function normalize_email( ?string $email ): ?string {
		$email = self::normalize_optional( $email, self::MAX_EMAIL_LENGTH, 'Lead email' );
		if ( null === $email ) {
			return null;
		}

		$email = mb_strtolower( $email );
		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new InvalidArgumentException( 'Lead email is invalid.' );
		}

		return $email;
	}
}
