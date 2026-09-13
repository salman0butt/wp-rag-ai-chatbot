<?php
/**
 * Public-safe widget configuration projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

/** Immutable allow-listed configuration exposed to the public widget. */
final readonly class WidgetConfig {
	/**
	 * Create one public widget configuration.
	 *
	 * @param string             $bot_id Stable public bot identifier.
	 * @param string             $name Public bot name.
	 * @param AppearanceConfig   $appearance Normalized browser-safe appearance.
	 * @param DisplayRulesConfig $display_rules Normalized browser-safe display rules.
	 */
	public function __construct(
		public string $bot_id,
		public string $name,
		public AppearanceConfig $appearance,
		public DisplayRulesConfig $display_rules
	) {
	}

	/**
	 * Project only explicitly public widget fields.
	 *
	 * @return array{bot_id:string,name:string,appearance:array<string,mixed>,display_rules:array<string,mixed>}
	 */
	public function to_array(): array {
		return array(
			'bot_id'        => $this->bot_id,
			'name'          => $this->name,
			'appearance'    => $this->appearance->to_array(),
			'display_rules' => $this->display_rules->to_array(),
		);
	}
}
