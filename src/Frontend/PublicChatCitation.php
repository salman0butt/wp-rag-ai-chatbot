<?php
/**
 * Public-safe chat citation projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Citations\Citation;

/**
 * Exposes only citation fields required by the public widget.
 */
final readonly class PublicChatCitation {
	/**
	 * Create one public citation projection.
	 *
	 * @param string      $id Request-local citation identifier.
	 * @param string|null $title Optional display title.
	 * @param string|null $url Optional canonical display URL.
	 */
	public function __construct(
		public string $id,
		public ?string $title,
		public ?string $url
	) {
	}

	/** Project one trusted M11 citation without internal lineage identifiers. */
	public static function from_citation( Citation $citation ): self {
		return new self( $citation->id, $citation->title, $citation->canonical_url );
	}
}
