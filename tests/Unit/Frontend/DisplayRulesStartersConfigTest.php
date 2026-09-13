<?php
/**
 * M15 starter-prompt persistence tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Frontend\DisplayRulesConfig;

/** Defines bounded starter-prompt persistence behavior. */
final class DisplayRulesStartersConfigTest extends TestCase {
	/** Starter prompts and page mappings normalize deterministically. */
	public function test_from_array_normalizes_starter_prompts(): void {
		$config = DisplayRulesConfig::from_array(
			array(
				'starters' => array(
					'default' => array( ' Ask about pricing ', 'Get help' ),
					'by_page' => array(
						array(
							'pattern' => ' pricing ',
							'prompts' => array( ' Pricing help ' ),
						),
						array(
							'pattern' => '/docs/*',
							'prompts' => array( 'Docs help' ),
						),
					),
				),
			)
		);

		self::assertSame(
			array(
				'default' => array( 'Ask about pricing', 'Get help' ),
				'by_page' => array(
					array(
						'pattern' => '/pricing',
						'prompts' => array( 'Pricing help' ),
					),
					array(
						'pattern' => '/docs/*',
						'prompts' => array( 'Docs help' ),
					),
				),
			),
			$config->to_array()['starters']
		);
	}

	/** Each starter set is limited to four prompts. */
	public function test_from_array_rejects_too_many_starter_prompts(): void {
		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array(
			array(
				'starters' => array(
					'default' => array( 'One', 'Two', 'Three', 'Four', 'Five' ),
				),
			)
		);
	}

	/** Page-specific starter mappings are limited to eight entries. */
	public function test_from_array_rejects_too_many_starter_mappings(): void {
		$mappings = array();
		for ( $index = 1; $index <= 9; $index++ ) {
			$mappings[] = array(
				'pattern' => '/page-' . $index,
				'prompts' => array( 'Help' ),
			);
		}

		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array(
			array(
				'starters' => array(
					'by_page' => $mappings,
				),
			)
		);
	}

	/** Prompt length uses Unicode codepoints and rejects values above 160. */
	public function test_from_array_rejects_too_long_unicode_prompt(): void {
		$this->expectException( InvalidArgumentException::class );
		DisplayRulesConfig::from_array(
			array(
				'starters' => array(
					'default' => array( str_repeat( '✓', 161 ) ),
				),
			)
		);
	}
}
