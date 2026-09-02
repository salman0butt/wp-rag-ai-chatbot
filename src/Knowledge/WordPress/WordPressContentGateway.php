<?php
/**
 * WordPress content gateway contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\WordPress;

/**
 * Isolates WordPress content APIs from source normalization.
 */
interface WordPressContentGateway {
	/**
	 * Return public WordPress post-type names.
	 *
	 * @return list<string>
	 */
	public function publicPostTypes(): array;

	/**
	 * Return one bounded page of WordPress posts.
	 *
	 * @param list<string> $post_types Post types to query.
	 * @param bool         $include_private Whether private posts may be included.
	 * @param int          $page One-based page number.
	 * @param int          $per_page Maximum posts per page.
	 * @return list<WordPressPost>
	 */
	public function posts( array $post_types, bool $include_private, int $page, int $per_page ): array;
}
