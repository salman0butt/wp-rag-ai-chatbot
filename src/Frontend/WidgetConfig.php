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
	 * @param string           $bot_id Stable public bot identifier.
	 * @param string           $name Public bot name.
	 * @param AppearanceConfig $appearance Normalized browser-safe appearance.
	 */
	public function __construct(
		public string $bot_id,
		public string $name,
		public AppearanceConfig $appearance
	) {
	}

	/**
	 * Project only explicitly public widget fields.
	 *
	 * @return array{
	 *     bot_id:string,
	 *     name:string,
	 *     appearance:array{
	 *         primary_color:string,
	 *         color_mode:string,
	 *         position:string,
	 *         launcher_style:string,
	 *         panel_size:string,
	 *         radius_px:int,
	 *         font_family:string
	 *     }
	 * }
	 */
	public function to_array(): array {
		return array(
			'bot_id'     => $this->bot_id,
			'name'       => $this->name,
			'appearance' => $this->appearance->to_array(),
		);
	}
}
