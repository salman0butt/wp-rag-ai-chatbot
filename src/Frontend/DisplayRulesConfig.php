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
	/**
	 * Allowed persisted top-level keys.
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

	/** Return deterministic M14-compatible defaults. */
	public static function defaults(): self {
		return new self(
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
			)
		);
	}

	/**
	 * Normalize persisted/admin input through the explicit top-level allow-list.
	 *
	 * @param array<string,mixed> $input Candidate display-rules values.
	 * @throws InvalidArgumentException When an unknown key is supplied.
	 */
	public static function from_array( array $input ): self {
		$unknown_keys = array_diff( array_keys( $input ), self::ALLOWED_KEYS );
		if ( array() !== $unknown_keys ) {
			throw new InvalidArgumentException( 'Display-rules configuration contains unknown keys.' );
		}

		return self::defaults();
	}

	/**
	 * Project the normalized display-rules data.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return $this->data;
	}
}
