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
	 * Allowed visibility configuration keys.
	 *
	 * @var list<string>
	 */
	private const VISIBILITY_KEYS = array(
		'url_include',
		'url_exclude',
		'post_types',
		'audience',
		'roles',
		'woo_areas',
		'devices',
		'schedule',
	);

	/**
	 * Allowed proactive configuration keys.
	 *
	 * @var list<string>
	 */
	private const PROACTIVE_KEYS = array(
		'enabled',
		'first_visit_only',
		'delay_ms',
		'scroll_percent',
		'exit_intent',
		'inactivity_ms',
		'click_selector',
	);

	/**
	 * Allowed localization configuration keys.
	 *
	 * @var list<string>
	 */
	private const LOCALIZATION_KEYS = array(
		'locale',
		'direction',
	);

	private const MAX_LOCALE_LENGTH      = 35;
	private const MAX_SLUG_LENGTH        = 64;
	private const MAX_SLUG_VALUES        = 16;
	private const MAX_TIMER_MS           = 600000;
	private const MAX_URL_PATTERNS       = 32;
	private const MAX_URL_PATTERN_LENGTH = 256;
	private const MAX_URL_WILDCARDS      = 4;

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
		self::assert_allowed_keys( $input, self::ALLOWED_KEYS );

		$data = self::default_data();
		if ( array_key_exists( 'enabled', $input ) ) {
			$data['enabled'] = self::normalize_bool( $input['enabled'] );
		}

		if ( array_key_exists( 'visibility', $input ) ) {
			$visibility = self::normalize_array( $input['visibility'] );
			self::assert_allowed_keys( $visibility, self::VISIBILITY_KEYS );

			$url_include = array_key_exists( 'url_include', $visibility )
				? self::normalize_path_patterns( $visibility['url_include'] )
				: array();
			$url_exclude = array_key_exists( 'url_exclude', $visibility )
				? self::normalize_path_patterns( $visibility['url_exclude'] )
				: array();
			if ( count( $url_include ) + count( $url_exclude ) > self::MAX_URL_PATTERNS ) {
				throw new InvalidArgumentException( 'Too many URL display-rule patterns.' );
			}
			$data['visibility']['url_include'] = $url_include;
			$data['visibility']['url_exclude'] = $url_exclude;

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
			self::assert_allowed_keys( $proactive, self::PROACTIVE_KEYS );

			foreach ( array( 'enabled', 'first_visit_only', 'exit_intent' ) as $key ) {
				if ( array_key_exists( $key, $proactive ) ) {
					$data['proactive'][ $key ] = self::normalize_bool(
						$proactive[ $key ]
					);
				}
			}

			foreach ( array( 'delay_ms', 'inactivity_ms' ) as $key ) {
				if ( array_key_exists( $key, $proactive ) ) {
					$data['proactive'][ $key ] = self::normalize_nullable_int_range(
						$proactive[ $key ],
						0,
						self::MAX_TIMER_MS
					);
				}
			}

			if ( array_key_exists( 'scroll_percent', $proactive ) ) {
				$data['proactive']['scroll_percent'] = self::normalize_nullable_int_range(
					$proactive['scroll_percent'],
					1,
					100
				);
			}
		}

		if ( array_key_exists( 'localization', $input ) ) {
			$localization = self::normalize_array( $input['localization'] );
			self::assert_allowed_keys( $localization, self::LOCALIZATION_KEYS );

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
	 * Reject unknown keys in one configuration scope.
	 *
	 * @param array<string,mixed> $input Candidate configuration scope.
	 * @param array               $allowed Allowed keys.
	 * @phpstan-param list<string> $allowed
	 * @throws InvalidArgumentException When an unknown key is supplied.
	 */
	private static function assert_allowed_keys( array $input, array $allowed ): void {
		$unknown_keys = array_diff( array_keys( $input ), $allowed );
		if ( array() !== $unknown_keys ) {
			throw new InvalidArgumentException(
				'Display-rules configuration contains unknown keys.'
			);
		}
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
	 * Normalize a bounded list of deterministic URL/path patterns.
	 *
	 * @param mixed $value Candidate value.
	 * @return list<string>
	 * @throws InvalidArgumentException When a path pattern is invalid.
	 */
	private static function normalize_path_patterns( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( 'URL patterns must be a list.' );
		}

		$patterns = array();
		foreach ( $value as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				throw new InvalidArgumentException( 'URL pattern must be a string.' );
			}

			$pattern = trim( $candidate );
			if (
				'' === $pattern ||
				strlen( $pattern ) > self::MAX_URL_PATTERN_LENGTH ||
				substr_count( $pattern, '*' ) > self::MAX_URL_WILDCARDS
			) {
				throw new InvalidArgumentException( 'URL pattern is outside the supported bounds.' );
			}

			if ( '/' !== $pattern[0] && '*' !== $pattern[0] ) {
				$pattern = '/' . $pattern;
			}

			if ( ! in_array( $pattern, $patterns, true ) ) {
				$patterns[] = $pattern;
			}
		}

		return $patterns;
	}

	/**
	 * Normalize a bounded list of slug-like strings.
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
			if (
				strlen( $item ) > self::MAX_SLUG_LENGTH ||
				1 !== preg_match( '/^[a-z0-9_-]+$/', $item )
			) {
				throw new InvalidArgumentException( 'Configuration slug is outside the supported grammar.' );
			}

			if ( ! in_array( $item, $normalized, true ) ) {
				$normalized[] = $item;
				if ( count( $normalized ) > self::MAX_SLUG_VALUES ) {
					throw new InvalidArgumentException( 'Too many configuration slug values.' );
				}
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
	 * Normalize one nullable bounded integer.
	 *
	 * @param mixed $value Candidate value.
	 * @param int   $minimum Inclusive minimum.
	 * @param int   $maximum Inclusive maximum.
	 * @throws InvalidArgumentException When the value is not null or a bounded integer.
	 */
	private static function normalize_nullable_int_range(
		mixed $value,
		int $minimum,
		int $maximum
	): ?int {
		if ( null === $value ) {
			return null;
		}
		if ( ! is_int( $value ) || $value < $minimum || $value > $maximum ) {
			throw new InvalidArgumentException( 'Configuration integer is outside the supported range.' );
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

		$locale = strtolower( str_replace( '_', '-', trim( $value ) ) );
		if ( in_array( $locale, array( 'site', 'auto' ), true ) ) {
			return $locale;
		}
		if (
			'' === $locale ||
			strlen( $locale ) > self::MAX_LOCALE_LENGTH ||
			1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $locale )
		) {
			throw new InvalidArgumentException( 'Locale is outside the supported grammar.' );
		}

		return $locale;
	}
}
