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

/** Defines the bounded PHP persistence/public-projection contract for M15 rules. */
final class DisplayRulesConfigTest extends TestCase {
	/** Missing M15 config must preserve M14-compatible presentation defaults. */
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

	/** Valid admin/persisted input is normalized into one browser-safe immutable shape. */
	public function test_from_array_normalizes_supported_values(): void {
		self::assertTrue( class_exists( DisplayRulesConfig::class ), 'M15 Task 2A requires DisplayRulesConfig.' );

		$config = DisplayRulesConfig::from_array(
			array(
				'enabled'    => false,
				'visibility' => array(
					'url_include' => array( ' pricing ', '/docs/*' ),
					'url_exclude' => array( '/docs/private/*' ),
					'post_types'  => array( ' PAGE ', 'product' ),
					'audience'    => 'selected_roles',
					'roles'       => array( ' Editor ', 'shop_manager' ),
					'woo_areas'   => array( 'product', 'cart' ),
					'devices'     => array( 'desktop', 'mobile' ),
					'schedule'    => array(
						'timezone' => 'site',
						'days'     => array( 1, 5 ),
						'start'    => '09:30',
						'end'      => '18:00',
					),
				),
				'proactive'  => array(
					'enabled'          => true,
					'first_visit_only' => true,
					'delay_ms'         => 1500,
					'scroll_percent'   => 60,
					'exit_intent'      => true,
					'inactivity_ms'    => 30000,
					'click_selector'   => ' .contact-cta ',
				),
				'starters'   => array(
					'default' => array( ' Ask about pricing ' ),
					'by_page' => array(
						array(
							'pattern' => ' /pricing ',
							'prompts' => array( ' Compare plans ' ),
						),
					),
				),
				'localization' => array(
					'locale'    => ' UR_PK ',
					'direction' => 'rtl',
				),
			)
		);

		$normalized = $config->to_array();
		self::assertFalse( $normalized['enabled'] );
		self::assertSame( array( '/pricing', '/docs/*' ), $normalized['visibility']['url_include'] );
		self::assertSame( array( 'page', 'product' ), $normalized['visibility']['post_types'] );
		self::assertSame( array( 'editor', 'shop_manager' ), $normalized['visibility']['roles'] );
		self::assertSame( 'selected_roles', $normalized['visibility']['audience'] );
		self::assertSame( 1500, $normalized['proactive']['delay_ms'] );
		self::assertSame( '.contact-cta', $normalized['proactive']['click_selector'] );
		self::assertSame( array( 'Ask about pricing' ), $normalized['starters']['default'] );
		self::assertSame( 'ur-pk', $normalized['localization']['locale'] );
		self::assertSame( 'rtl', $normalized['localization']['direction'] );
	}

	/** Unknown schema keys must fail closed at the PHP persistence boundary. */
	public function test_from_array_rejects_unknown_keys(): void {
		self::assertTrue( class_exists( DisplayRulesConfig::class ), 'M15 Task 2A requires DisplayRulesConfig.' );

		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array( array( 'provider_override' => 'openai' ) );
	}

	/** Proactive timers are finite and capped at the design maximum of ten minutes. */
	public function test_from_array_rejects_out_of_range_timer(): void {
		self::assertTrue( class_exists( DisplayRulesConfig::class ), 'M15 Task 2A requires DisplayRulesConfig.' );

		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array(
			array(
				'proactive' => array( 'delay_ms' => 600001 ),
			)
		);
	}

	/** Selectors must stay within the bounded ordinary-selector grammar. */
	public function test_from_array_rejects_unsafe_selector(): void {
		self::assertTrue( class_exists( DisplayRulesConfig::class ), 'M15 Task 2A requires DisplayRulesConfig.' );

		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array(
			array(
				'proactive' => array( 'click_selector' => '.cta, body *' ),
			)
		);
	}

	/** Starter prompts are plain bounded strings rather than an HTML execution channel. */
	public function test_from_array_rejects_oversized_prompt(): void {
		self::assertTrue( class_exists( DisplayRulesConfig::class ), 'M15 Task 2A requires DisplayRulesConfig.' );

		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array(
			array(
				'starters' => array(
					'default' => array( str_repeat( 'a', 161 ) ),
				),
			)
		);
	}
}
