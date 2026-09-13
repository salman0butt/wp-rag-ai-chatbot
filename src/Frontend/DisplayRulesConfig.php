<?php
/**
 * Shared M15 display-rules configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use InvalidArgumentException;

/** Immutable normalized display-rules configuration value. */
final readonly class DisplayRulesConfig {
	/** @var list<string> */
	private const ALLOWED_KEYS = array(
		'enabled',
		'visibility',
		'proactive',
		'starters',
		'localization',
	);

	/** @var list<string> */
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

	/** @var list<string> */
	private const PROACTIVE_KEYS = array(
		'enabled',
		'first_visit_only',
		'delay_ms',
		'scroll_percent',
		'exit_intent',
		'inactivity_ms',
		'click_selector',
	);

	/** @var list<string> */
	private const LOCALIZATION_KEYS = array( 'locale', 'direction' );

	/**
	 * Create one normalized display-rules value.
	 *
	 * @param array<string,mixed> $data Normalized display-rules data.
	 */
	private function __construct( private array $data ) {
	}

	/** Return deterministic M14-compatible defaults. */
	public static function defaults(): self {
		return new self( self::default_data() );
	}

	/**
	 * Normalize persisted/admin input through explicit allow-lists.
	 *
	 * @param array<string,mixed> $input Candidate display-rules values.
	 * @throws InvalidArgumentException When input violates the bounded schema.
	 */
	public static function from_array( array $input ): self {
		self::assert_known_keys( $input, self::ALLOWED_KEYS, 'display-rules' );
		$data = self::default_data();

		if ( array_key_exists( 'enabled', $input ) ) {
			$data['enabled'] = self::normalize_bool( $input['enabled'], 'enabled' );
		}

		if ( array_key_exists( 'visibility', $input ) ) {
			$visibility = self::normalize_section( $input['visibility'], 'visibility' );
			self::assert_known_keys( $visibility, self::VISIBILITY_KEYS, 'visibility' );
			if ( array_key_exists( 'post_types', $visibility ) ) {
				$data['visibility']['post_types'] = self::normalize_slug_list( $visibility['post_types'], 'post_types' );
			}
			if ( array_key_exists( 'audience', $visibility ) ) {
				$data['visibility']['audience'] = self::normalize_choice(
					$visibility['audience'],
					array( 'all', 'authenticated', 'anonymous', 'selected_roles' ),
					'audience'
				);
			}
			if ( array_key_exists( 'roles', $visibility ) ) {
				$data['visibility']['roles'] = self::normalize_slug_list( $visibility['roles'], 'roles' );
			}
			if ( array_key_exists( 'woo_areas', $visibility ) ) {
				$data['visibility']['woo_areas'] = self::normalize_choice_list(
					$visibility['woo_areas'],
					array( 'shop', 'product', 'cart', 'checkout', 'account' ),
					'woo_areas'
				);
			}
			if ( array_key_exists( 'devices', $visibility ) ) {
				$data['visibility']['devices'] = self::normalize_choice_list(
					$visibility['devices'],
					array( 'desktop', 'tablet', 'mobile' ),
					'devices'
				);
			}
		}

		if ( array_key_exists( 'proactive', $input ) ) {
			$proactive = self::normalize_section( $input['proactive'], 'proactive' );
			self::assert_known_keys( $proactive, self::PROACTIVE_KEYS, 'proactive' );
			foreach ( array( 'enabled', 'first_visit_only', 'exit_intent' ) as $key ) {
				if ( array_key_exists( $key, $proactive ) ) {
					$data['proactive'][ $key ] = self::normalize_bool( $proactive[ $key ], $key );
				}
			}
			if ( array_key_exists( 'delay_ms', $proactive ) ) {
				$data['proactive']['delay_ms'] = self::normalize_nullable_int( $proactive['delay_ms'], 0, 600000, 'delay_ms' );
			}
			if ( array_key_exists( 'scroll_percent', $proactive ) ) {
				$data['proactive']['scroll_percent'] = self::normalize_nullable_int( $proactive['scroll_percent'], 1, 100, 'scroll_percent' );
			}
			if ( array_key_exists( 'inactivity_ms', $proactive ) ) {
				$data['proactive']['inactivity_ms'] = self::normalize_nullable_int( $proactive['inactivity_ms'], 0, 600000, 'inactivity_ms' );
			}
		}

		if ( array_key_exists( 'localization', $input ) ) {
			$localization = self::normalize_section( $input['localization'], 'localization' );
			self::assert_known_keys( $localization, self::LOCALIZATION_KEYS, 'localization' );
			if ( array_key_exists( 'locale', $localization ) ) {
				$data['localization']['locale'] = self::normalize_locale( $localization['locale'] );
			}
			if ( array_key_exists( 'direction', $localization ) ) {
				$data['localization']['direction'] = self::normalize_choice(
					$localization['direction'],
					array( 'auto', 'ltr', 'rtl' ),
					'direction'
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
	 * Reject unknown object keys.
	 *
	 * @param array<string,mixed> $input Candidate object.
	 * @param list<string>        $allowed Allowed keys.
	 */
	private static function assert_known_keys( array $input, array $allowed, string $label ): void {
		if ( array() !== array_diff( array_keys( $input ), $allowed ) ) {
			throw new InvalidArgumentException( $label . ' configuration contains unknown keys.' );
		}
	}

	/** @return array<string,mixed> */
	private static function normalize_section( mixed $value, string $label ): array {
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( $label . ' must be an object.' );
		}

		/** @var array<string,mixed> $value */
		return $value;
	}

	private static function normalize_bool( mixed $value, string $label ): bool {
		if ( ! is_bool( $value ) ) {
			throw new InvalidArgumentException( $label . ' must be boolean.' );
		}

		return $value;
	}

	/** @param list<string> $allowed */
	private static function normalize_choice( mixed $value, array $allowed, string $label ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( $label . ' must be a supported value.' );
		}

		$normalized = strtolower( trim( $value ) );
		if ( ! in_array( $normalized, $allowed, true ) ) {
			throw new InvalidArgumentException( $label . ' must be a supported value.' );
		}

		return $normalized;
	}

	/** @return list<string> */
	private static function normalize_slug_list( mixed $value, string $label ): array {
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( $label . ' must be a list.' );
		}

		$normalized = array();
		foreach ( $value as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				throw new InvalidArgumentException( $label . ' contains an invalid value.' );
			}

			$slug = strtolower( trim( $candidate ) );
			if ( '' === $slug || strlen( $slug ) > 64 || 1 !== preg_match( '/^[a-z0-9_-]+$/', $slug ) ) {
				throw new InvalidArgumentException( $label . ' contains an invalid value.' );
			}

			if ( ! in_array( $slug, $normalized, true ) ) {
				$normalized[] = $slug;
			}
		}

		if ( count( $normalized ) > 16 ) {
			throw new InvalidArgumentException( $label . ' contains too many values.' );
		}

		return $normalized;
	}

	/**
	 * @param list<string> $allowed Allowed values.
	 * @return list<string>
	 */
	private static function normalize_choice_list( mixed $value, array $allowed, string $label ): array {
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( $label . ' must be a list.' );
		}

		$normalized = array();
		foreach ( $value as $candidate ) {
			$choice = self::normalize_choice( $candidate, $allowed, $label );
			if ( ! in_array( $choice, $normalized, true ) ) {
				$normalized[] = $choice;
			}
		}

		return $normalized;
	}

	private static function normalize_nullable_int( mixed $value, int $minimum, int $maximum, string $label ): ?int {
		if ( null === $value ) {
			return null;
		}

		if ( ! is_int( $value ) || $value < $minimum || $value > $maximum ) {
			throw new InvalidArgumentException( $label . ' is out of range.' );
		}

		return $value;
	}

	private static function normalize_locale( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'locale must be a supported value.' );
		}

		$normalized = strtolower( str_replace( '_', '-', trim( $value ) ) );
		if ( 'site' === $normalized || 'auto' === $normalized ) {
			return $normalized;
		}

		if ( strlen( $normalized ) > 35 || 1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $normalized ) ) {
			throw new InvalidArgumentException( 'locale must be a supported value.' );
		}

		return $normalized;
	}
}
