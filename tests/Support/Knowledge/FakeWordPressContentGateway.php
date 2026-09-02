<?php
/**
 * Fake WordPress content gateway for source tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Knowledge;

use WpRagAiChatbot\Knowledge\WordPress\WordPressContentGateway;
use WpRagAiChatbot\Knowledge\WordPress\WordPressPost;

/**
 * Deterministic in-memory gateway with observable pagination calls.
 */
final class FakeWordPressContentGateway implements WordPressContentGateway {
	/** @var array<int, array{postTypes:array<int,string>,includePrivate:bool,page:int,perPage:int}> */
	public array $calls = array();

	/**
	 * @param array<int, string>                         $public_post_types Public post types.
	 * @param array<int, array<int, WordPressPost>>      $pages Posts keyed by one-based page.
	 */
	public function __construct(
		private array $public_post_types,
		private array $pages
	) {
	}

	/** @return array<int, string> */
	public function publicPostTypes(): array {
		return $this->public_post_types;
	}

	/** @return array<int, WordPressPost> */
	public function posts( array $post_types, bool $include_private, int $page, int $per_page ): array {
		$this->calls[] = array(
			'postTypes'      => $post_types,
			'includePrivate' => $include_private,
			'page'           => $page,
			'perPage'        => $per_page,
		);

		return $this->pages[ $page ] ?? array();
	}
}
