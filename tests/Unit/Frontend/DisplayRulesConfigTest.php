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

	/** Unknown schema keys must fail closed at the persistence boundary. */
	public function test_from_array_rejects_unknown_keys(): void {
		self::assertTrue( class_exists( DisplayRulesConfig::class ), 'M15 Task 2A requires DisplayRulesConfig.' );

		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array( array( 'provider_override' => 'openai' ) );
	}
}
