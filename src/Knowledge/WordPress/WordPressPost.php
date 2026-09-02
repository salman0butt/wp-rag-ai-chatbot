<?php
/**
 * Immutable WordPress post value.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\WordPress;

/**
 * Normalized primitive WordPress post data returned by the gateway.
 */
final readonly class WordPressPost {
	/**
	 * @param int                                                   $id Post ID.
	 * @param string                                                $type Post type.
	 * @param string                                                $status Post status.
	 * @param string                                                $title Post title.
	 * @param string                                                $excerpt Post excerpt.
	 * @param string                                                $content Post content.
	 * @param string|null                                           $url Canonical permalink.
	 * @param string                                                $modifiedGmt WordPress modified GMT value.
	 * @param string|null                                           $language Language when available.
	 * @param bool                                                  $passwordProtected Whether a password is set.
	 * @param int                                                   $authorId Author ID.
	 * @param array<string, list<array{name:string,slug:string}>>   $taxonomyLabels Selected taxonomy labels.
	 */
	public function __construct(
		public int $id,
		public string $type,
		public string $status,
		public string $title,
		public string $excerpt,
		public string $content,
		public ?string $url,
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Public immutable value follows the approved M04 contract.
		public string $modifiedGmt,
		public ?string $language,
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Public immutable value follows the approved M04 contract.
		public bool $passwordProtected,
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Public immutable value follows the approved M04 contract.
		public int $authorId,
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Public immutable value follows the approved M04 contract.
		public array $taxonomyLabels
	) {
	}
}
