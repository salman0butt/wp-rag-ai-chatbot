<?php
/**
 * Shared M15 display-rules configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use InvalidArgumentException;

/**
 * Immutable normalized display-rules configuration value.
 */
final readonly class DisplayRulesConfig {
	/**
	 * Allowed persisted display-rules keys.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_KEYS = array(
		'enabled',
		'visibility',
		'proactive',
		'starters',
		'localization',
	);

	/**
	 * Create one normalized display-rules value.
	 *
	 * @param array<string,mixed> $data Normalized display-rules data.
	 */
	private function __construct( private array $data ) {
	}

	/**
	 * Return deterministic M14-compatible defaults.
	 */
	public static function defaults(): self {
		return new self( self::default_data() );
	}

	/**
	 * Normalize the currently-supported M15 persistence fields.
	 *
	 * @param array<string,mixed> $input Candidate display-rules values.
	 * @throws InvalidArgumentException When an unknown or invalid value is supplied.
	 */
	public static function from_array( array $input ): self {
		$unknown_keys = array_diff( array_keys( $input ), self::ALLOWED_KEYS );
		if ( array() !== $unknown_keys ) {
			throw new InvalidArgumentException(
				'Display-rules configuration contains unknown keys.'
			);
		}

		$data = self::default_data();
		if ( array_key_exists( 'enabled', $input ) ) {
			$data['enabled'] = self::normalize_bool( $input['enabled'] );
		}

		if ( array_key_exists( 'visibility', $input ) ) {
			$visibility = self::normalize_array( $input['visibility'] );

			if ( array_key_exists( 'post_types', $visibility ) ) {
				$data['visibility']['post_types'] = self::normalize_slug_list(
					$visibility['post_types']
				);
			}
			if ( array_key_exists( 'audience', $visibility ) ) {
				$data['visibility']['audience'] = self::normalize_choice(
					$visibility['audience'],
					array( 'all', 'authenticated', 'anonymous', 'selected_roles' )
				);
			}
			if ( array_key_exists( 'roles', $visibility ) ) {
				$data['visibility']['roles'] = self::normalize_slug_list(
					$visibility['roles']
				);
			}
			if ( array_key_exists( 'woo_areas', $visibility ) ) {
				$data['visibility']['woo_areas'] = self::normalize_choice_list(
					$visibility['woo_areas'],
					array( 'shop', 'product', 'cart', 'checkout', 'account' )
				);
			}
			if ( array_key_exists( 'devices', $visibility ) ) {
				$data['visibility']['devices'] = self::normalize_choice_list(
					$visibility['devices'],
					array( 'desktop', 'tablet', 'mobile' )
				);
			}
		}

		if ( array_key_exists( 'proactive', $input ) ) {
			$proactive = self::normalize_array( $input['proactive'] );
			foreach ( array( 'enabled', 'first_visit_only', 'exit_intent' ) as $key ) {
				if ( array_key_exists( $key, $proactive ) ) {
					$data['proactive'][ $key ] = self::normalize_bool(
						$proactive[ $key ]
					);
				}
			}

			foreach ( array( 'delay_ms', 'scroll_percent', 'inactivity_ms' ) as $key ) {
				if ( array_key_exists( $key, $proactive ) ) {
					$data['proactive'][ $key ] = self::normalize_nullable_int(
						$proactive[ $key ]
					);
				}
			}
		}

		if ( array_key_exists( 'localization', $input ) ) {
			$localization = self::normalize_array( $input['localization'] );
			if ( array_key_exists( 'locale', $localization ) ) {
				$data['localization']['locale'] = self::normalize_locale(
					$localization['locale']
				);
			}
			if ( array_key_exists( 'direction', $localization ) ) {
				$data['localization']['direction'] = self::normalize_choice(
					$localization['direction'],
					array( 'auto', 'ltr', 'rtl' )
				);
			}
		}

		return new self( $data );
	}

	/**
	 * Project the normalized display-rules data.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return $this->data;
	}

	/**
	 * Return the full default schema.
	 *
	 * @return array<string,mixed>
	 */
	private static function default_data(): array {
		return array(
			'enabled'      => true,
			'visibility'   => array(
				'url_include' => array(),
				'url_exclude' => array(),
				'post_types'  => array(),
				'audience'    => 'all',
				'roles'       => array(),
				'woo_areas'   => array(),
				'devices'     => array(),
				'schedule'    => null,
			),
			'proactive'    => array(
				'enabled'          => false,
				'first_visit_only' => false,
				'delay_ms'         => null,
				'scroll_percent'   => null,
				'exit_intent'      => false,
				'inactivity_ms'    => null,
				'click_selector'   => null,
			),
			'starters'     => array(
				'default' => array(),
				'by_page' => array(),
			),
			'localization' => array(
				'locale'    => 'site',
				'direction' => 'auto',
			),
		);
	}

	/**
	 * Normalize one nested configuration object.
	 *
	 * @param mixed $value Candidate object.
	 * @return array<string,mixed>
	 * @throws InvalidArgumentException When the value is not an array.
	 */
	private static function normalize_array( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( 'Configuration section must be an array.' );
		}

		return $value;
	}

	/**
	 * Normalize a strict boolean.
	 *
	 * @param mixed $value Candidate value.
	 * @throws InvalidArgumentException When the value is not boolean.
	 */
	private static function normalize_bool( mixed $value ): bool {
		if ( ! is_bool( $value ) ) {
			throw new InvalidArgumentException( 'Configuration value must be boolean.' );
		}

		return $value;
	}

	/**
	 * Normalize one enum-like string.
	 *
	 * @param mixed $value Candidate value.
	 * @param array $allowed Explicit allowed values.
	 * @phpstan-param list<string> $allowed
	 * @throws InvalidArgumentException When the value is unsupported.
	 */
	private static function normalize_choice( mixed $value, array $allowed ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'Configuration option must be a string.' );
		}

		$value = strtolower( trim( $value ) );
		if ( ! in_array( $value, $allowed, true ) ) {
			throw new InvalidArgumentException( 'Configuration option is not supported.' );
		}

		return $value;
	}

	/**
	 * Normalize a list of slug-like strings.
	 *
	 * @param mixed $value Candidate value.
	 * @return list<string>
	 * @throws InvalidArgumentException When a list value is invalid.
	 */
	private static function normalize_slug_list( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( 'Configuration value must be a list.' );
		}

		$normalized = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) || '' === trim( $item ) ) {
				throw new InvalidArgumentException( 'Configuration list contains an invalid value.' );
			}

			$item = strtolower( trim( $item ) );
			if ( ! in_array( $item, $normalized, true ) ) {
				$normalized[] = $item;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize a list of enum-like strings.
	 *
	 * @param mixed $value Candidate value.
	 * @param array $allowed Explicit allowed values.
	 * @phpstan-param list<string> $allowed
	 * @return list<string>
	 * @throws InvalidArgumentException When a list value is invalid.
	 */
	private static function normalize_choice_list( mixed $value, array $allowed ): array {
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( 'Configuration value must be a list.' );
		}

		$normalized = array();
		foreach ( $value as $item ) {
			$item = self::normalize_choice( $item, $allowed );
			if ( ! in_array( $item, $normalized, true ) ) {
				$normalized[] = $item;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize one nullable integer.
	 *
	 * @param mixed $value Candidate value.
	 * @throws InvalidArgumentException When the value is not an integer or null.
	 */
	private static function normalize_nullable_int( mixed $value ): ?int {
		if ( null === $value ) {
			return null;
		}
		if ( ! is_int( $value ) ) {
			throw new InvalidArgumentException( 'Configuration value must be an integer.' );
		}

		return $value;
	}

	/**
	 * Normalize one locale identifier.
	 *
	 * @param mixed $value Candidate locale.
	 * @throws InvalidArgumentException When the value is not a string.
	 */
	private static function normalize_locale( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'Locale must be a string.' );
		}

		return strtolower( str_replace( '_', '-', trim( $value ) ) );
	}
}
