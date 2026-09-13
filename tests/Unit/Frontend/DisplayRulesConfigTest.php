<?php
/**
 * M15 immutable display-rules configuration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Frontend\DisplayRulesConfig;

/** Defines the bounded PHP persistence contract for M15 rules. */
final class DisplayRulesConfigTest extends TestCase {
	/** Missing M15 config must preserve M14-compatible defaults. */
	public function test_defaults_are_safe_and_deterministic(): void {
		self::assertTrue( class_exists( DisplayRulesConfig::class ), 'M15 Task 2A requires DisplayRulesConfig.' );

		$config = DisplayRulesConfig::defaults();

		self::assertSame(
			array(
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
			),
			$config->to_array()
		);
	}

	/** Supported scalar and finite-enum values are normalized deterministically. */
	public function test_from_array_normalizes_supported_values(): void {
		$config = DisplayRulesConfig::from_array(
			array(
				'enabled'      => false,
				'visibility'   => array(
					'post_types' => array( ' PAGE ', 'product' ),
					'audience'   => 'selected_roles',
					'roles'      => array( ' Editor ', 'shop_manager' ),
					'woo_areas'  => array( 'product', 'cart' ),
					'devices'    => array( 'desktop', 'mobile' ),
				),
				'proactive'    => array(
					'enabled'          => true,
					'first_visit_only' => true,
					'delay_ms'         => 1500,
					'scroll_percent'   => 60,
					'exit_intent'      => true,
					'inactivity_ms'    => 30000,
				),
				'localization' => array(
					'locale'    => ' UR_PK ',
					'direction' => 'rtl',
				),
			)
		);

		$normalized = $config->to_array();
		self::assertFalse( $normalized['enabled'] );
		self::assertSame( array( 'page', 'product' ), $normalized['visibility']['post_types'] );
		self::assertSame( 'selected_roles', $normalized['visibility']['audience'] );
		self::assertSame( array( 'editor', 'shop_manager' ), $normalized['visibility']['roles'] );
		self::assertSame( array( 'product', 'cart' ), $normalized['visibility']['woo_areas'] );
		self::assertSame( array( 'desktop', 'mobile' ), $normalized['visibility']['devices'] );
		self::assertTrue( $normalized['proactive']['enabled'] );
		self::assertTrue( $normalized['proactive']['first_visit_only'] );
		self::assertSame( 1500, $normalized['proactive']['delay_ms'] );
		self::assertSame( 60, $normalized['proactive']['scroll_percent'] );
		self::assertTrue( $normalized['proactive']['exit_intent'] );
		self::assertSame( 30000, $normalized['proactive']['inactivity_ms'] );
		self::assertSame( 'ur-pk', $normalized['localization']['locale'] );
		self::assertSame( 'rtl', $normalized['localization']['direction'] );
	}

	/** Unknown schema keys must fail closed at the persistence boundary. */
	public function test_from_array_rejects_unknown_keys(): void {
		self::assertTrue( class_exists( DisplayRulesConfig::class ), 'M15 Task 2A requires DisplayRulesConfig.' );

		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array( array( 'provider_override' => 'openai' ) );
	}

	/** Nested configuration sections must also reject unknown keys. */
	public function test_from_array_rejects_unknown_nested_keys(): void {
		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array(
			array(
				'visibility' => array(
					'provider_override' => 'openai',
				),
			)
		);
	}

	/** Proactive timers must stay within the ten-minute design bound. */
	public function test_from_array_rejects_out_of_range_timer(): void {
		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array(
			array(
				'proactive' => array(
					'delay_ms' => 600001,
				),
			)
		);
	}
}
