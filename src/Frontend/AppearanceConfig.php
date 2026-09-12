<?php
/**
 * Shared M14 chatbot appearance configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use InvalidArgumentException;

/**
 * Immutable, browser-safe appearance schema shared by runtime and preview.
 */
final readonly class AppearanceConfig {
	private const DEFAULT_PRIMARY_COLOR = '#2563eb';
	private const DEFAULT_COLOR_MODE    = 'system';
	private const DEFAULT_POSITION      = 'bottom-right';
	private const DEFAULT_LAUNCHER      = 'bubble';
	private const DEFAULT_PANEL_SIZE    = 'medium';
	private const DEFAULT_RADIUS_PX     = 16;
	private const DEFAULT_FONT_FAMILY   = 'system';
	private const MAX_RADIUS_PX         = 32;

	/**
	 * Allowed persisted appearance keys.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_KEYS = array(
		'primary_color',
		'color_mode',
		'position',
		'launcher_style',
		'panel_size',
		'radius_px',
		'font_family',
	);

	/**
	 * Supported color modes.
	 *
	 * @var list<string>
	 */
	private const COLOR_MODES = array( 'light', 'dark', 'system' );

	/**
	 * Supported launcher positions.
	 *
	 * @var list<string>
	 */
	private const POSITIONS = array( 'bottom-left', 'bottom-right' );

	/**
	 * Supported launcher styles.
	 *
	 * @var list<string>
	 */
	private const LAUNCHER_STYLES = array( 'bubble', 'icon', 'text' );

	/**
	 * Supported panel sizes.
	 *
	 * @var list<string>
	 */
	private const PANEL_SIZES = array( 'small', 'medium', 'large' );

	/**
	 * Supported browser-safe font families.
	 *
	 * @var list<string>
	 */
	private const FONT_FAMILIES = array( 'system', 'sans', 'serif', 'mono' );

	/**
	 * Create one normalized appearance value.
	 *
	 * @param string $primary_color  Six-digit hexadecimal primary color.
	 * @param string $color_mode     Supported color mode.
	 * @param string $position       Supported launcher position.
	 * @param string $launcher_style Supported launcher style.
	 * @param string $panel_size     Supported panel size.
	 * @param int    $radius_px      Bounded panel radius in pixels.
	 * @param string $font_family    Supported browser-safe font family.
	 */
	private function __construct(
		public string $primary_color,
		public string $color_mode,
		public string $position,
		public string $launcher_style,
		public string $panel_size,
		public int $radius_px,
		public string $font_family
	) {
	}

	/**
	 * Return deterministic runtime/customizer defaults.
	 */
	public static function defaults(): self {
		return new self(
			self::DEFAULT_PRIMARY_COLOR,
			self::DEFAULT_COLOR_MODE,
			self::DEFAULT_POSITION,
			self::DEFAULT_LAUNCHER,
			self::DEFAULT_PANEL_SIZE,
			self::DEFAULT_RADIUS_PX,
			self::DEFAULT_FONT_FAMILY
		);
	}

	/**
	 * Normalize persisted/admin input through the explicit appearance allow-list.
	 *
	 * @param array<string,mixed> $input Candidate appearance values.
	 * @throws InvalidArgumentException When an unknown or invalid value is supplied.
	 */
	public static function from_array( array $input ): self {
		$unknown_keys = array_diff( array_keys( $input ), self::ALLOWED_KEYS );
		if ( array() !== $unknown_keys ) {
			throw new InvalidArgumentException( 'Appearance configuration contains unknown keys.' );
		}

		$defaults = self::defaults();

		return new self(
			self::normalize_color( $input['primary_color'] ?? $defaults->primary_color ),
			self::normalize_choice(
				$input['color_mode'] ?? $defaults->color_mode,
				self::COLOR_MODES
			),
			self::normalize_choice(
				$input['position'] ?? $defaults->position,
				self::POSITIONS
			),
			self::normalize_choice(
				$input['launcher_style'] ?? $defaults->launcher_style,
				self::LAUNCHER_STYLES
			),
			self::normalize_choice(
				$input['panel_size'] ?? $defaults->panel_size,
				self::PANEL_SIZES
			),
			self::normalize_radius( $input['radius_px'] ?? $defaults->radius_px ),
			self::normalize_choice(
				$input['font_family'] ?? $defaults->font_family,
				self::FONT_FAMILIES
			)
		);
	}

	/**
	 * Project the same explicit schema consumed by browser runtime and preview.
	 *
	 * @return array{
	 *     primary_color:string,
	 *     color_mode:string,
	 *     position:string,
	 *     launcher_style:string,
	 *     panel_size:string,
	 *     radius_px:int,
	 *     font_family:string
	 * }
	 */
	public function to_array(): array {
		return array(
			'primary_color'  => $this->primary_color,
			'color_mode'     => $this->color_mode,
			'position'       => $this->position,
			'launcher_style' => $this->launcher_style,
			'panel_size'     => $this->panel_size,
			'radius_px'      => $this->radius_px,
			'font_family'    => $this->font_family,
		);
	}

	/**
	 * Normalize a six-digit hexadecimal color.
	 *
	 * @param mixed $value Candidate color value.
	 * @throws InvalidArgumentException When the value is not a safe six-digit color.
	 */
	private static function normalize_color( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'Primary color must be a string.' );
		}

		$value = strtolower( trim( $value ) );
		if ( 1 !== preg_match( '/^#[0-9a-f]{6}$/', $value ) ) {
			throw new InvalidArgumentException( 'Primary color must be a six-digit hexadecimal color.' );
		}

		return $value;
	}

	/**
	 * Normalize one enum-like string value.
	 *
	 * @param mixed $value   Candidate value.
	 * @param array $allowed Explicit allowed values.
	 * @phpstan-param list<string> $allowed
	 * @throws InvalidArgumentException When the candidate is not an allowed string.
	 */
	private static function normalize_choice( mixed $value, array $allowed ): string {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'Appearance option must be a string.' );
		}

		$value = strtolower( trim( $value ) );
		if ( ! in_array( $value, $allowed, true ) ) {
			throw new InvalidArgumentException( 'Appearance option is not supported.' );
		}

		return $value;
	}

	/**
	 * Normalize the bounded corner radius.
	 *
	 * @param mixed $value Candidate radius value.
	 * @throws InvalidArgumentException When the radius is outside the supported range.
	 */
	private static function normalize_radius( mixed $value ): int {
		if ( ! is_int( $value ) || $value < 0 || $value > self::MAX_RADIUS_PX ) {
			throw new InvalidArgumentException( 'Radius must be an integer between 0 and 32.' );
		}

		return $value;
	}
}
