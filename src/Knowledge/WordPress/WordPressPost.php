<?php
/**
 * Immutable WordPress post value.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\WordPress;

// phpcs:disable WordPress.NamingConventions -- Public record property names follow the approved M04 gateway contract.
/**
 * Normalized primitive WordPress post data returned by the gateway.
 */
final readonly class WordPressPost {
	/**
	 * Create a normalized WordPress post value.
	 *
	 * @param int                                                 $id Post ID.
	 * @param string                                              $type Post type.
	 * @param string                                              $status Post status.
	 * @param string                                              $title Post title.
	 * @param string                                              $excerpt Post excerpt.
	 * @param string                                              $content Post content.
	 * @param string|null                                         $url Canonical permalink.
	 * @param string                                              $modifiedGmt WordPress modified GMT value.
	 * @param string|null                                         $language Language when available.
	 * @param bool                                                $passwordProtected Whether a password is set.
	 * @param int                                                 $authorId Author ID.
	 * @param array<string, list<array{name:string,slug:string}>> $taxonomyLabels Selected taxonomy labels.
	 */
	public function __construct(
		public int $id,
		public string $type,
		public string $status,
		public string $title,
		public string $excerpt,
		public string $content,
		public ?string $url,
		public string $modifiedGmt,
		public ?string $language,
		public bool $passwordProtected,
		public int $authorId,
		public array $taxonomyLabels
	) {
	}
}
// phpcs:enable WordPress.NamingConventions
