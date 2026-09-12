<?php
/**
 * M14 shared chatbot appearance configuration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Frontend\AppearanceConfig;

/**
 * Defines the shared bounded appearance schema used by runtime and preview.
 */
final class AppearanceConfigTest extends TestCase {
	/** Defaults must be deterministic so runtime and customizer preview match. */
	public function test_defaults_define_safe_runtime_appearance(): void {
		self::assertTrue( class_exists( AppearanceConfig::class ), 'M14 Task 1 requires AppearanceConfig.' );

		$config = AppearanceConfig::defaults();

		self::assertSame( '#2563eb', $config->primary_color );
		self::assertSame( 'system', $config->color_mode );
		self::assertSame( 'bottom-right', $config->position );
		self::assertSame( 'bubble', $config->launcher_style );
		self::assertSame( 'medium', $config->panel_size );
		self::assertSame( 16, $config->radius_px );
		self::assertSame( 'system', $config->font_family );
	}

	/** Persisted input is normalized into the same explicit browser-safe projection. */
	public function test_from_array_normalizes_allow_listed_values(): void {
		self::assertTrue( class_exists( AppearanceConfig::class ), 'M14 Task 1 requires AppearanceConfig.' );

		$config = AppearanceConfig::from_array(
			array(
				'primary_color' => ' #0EA5E9 ',
				'color_mode' => 'dark',
				'position' => 'bottom-left',
				'launcher_style' => 'icon',
				'panel_size' => 'large',
				'radius_px' => 24,
				'font_family' => 'system',
			)
		);

		self::assertSame( '#0ea5e9', $config->primary_color );
		self::assertSame( 'dark', $config->color_mode );
		self::assertSame( 'bottom-left', $config->position );
		self::assertSame( 'icon', $config->launcher_style );
		self::assertSame( 'large', $config->panel_size );
		self::assertSame( 24, $config->radius_px );
		self::assertSame( 'system', $config->font_family );
		self::assertSame(
			array(
				'primary_color' => '#0ea5e9',
				'color_mode' => 'dark',
				'position' => 'bottom-left',
				'launcher_style' => 'icon',
				'panel_size' => 'large',
				'radius_px' => 24,
				'font_family' => 'system',
			),
			$config->to_array()
		);
	}

	/** Arbitrary CSS or unrecognized schema keys must fail closed. */
	public function test_from_array_rejects_unknown_keys(): void {
		self::assertTrue( class_exists( AppearanceConfig::class ), 'M14 Task 1 requires AppearanceConfig.' );

		$this->expectException( InvalidArgumentException::class );
		AppearanceConfig::from_array(
			array(
				'primary_color' => '#2563eb',
				'custom_css' => 'body{display:none}',
			)
		);
	}

	/** Primary color is limited to an explicit six-digit hex color. */
	public function test_from_array_rejects_unsafe_primary_color(): void {
		self::assertTrue( class_exists( AppearanceConfig::class ), 'M14 Task 1 requires AppearanceConfig.' );

		$this->expectException( InvalidArgumentException::class );
		AppearanceConfig::from_array( array( 'primary_color' => 'url(javascript:alert(1))' ) );
	}

	/** Numeric visual values are bounded rather than becoming arbitrary CSS input. */
	public function test_from_array_rejects_out_of_range_radius(): void {
		self::assertTrue( class_exists( AppearanceConfig::class ), 'M14 Task 1 requires AppearanceConfig.' );

		$this->expectException( InvalidArgumentException::class );
		AppearanceConfig::from_array( array( 'radius_px' => 999 ) );
	}
}
