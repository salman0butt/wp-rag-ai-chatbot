<?php
/**
 * Native WordPress content gateway.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\WordPress;

use WP_Post;
use WP_Term;

/**
 * Reads bounded, traceable content through WordPress core APIs.
 */
final class NativeWordPressContentGateway implements WordPressContentGateway {
	/**
	 * Return public post types in deterministic order.
	 *
	 * @return list<string>
	 */
	public function publicPostTypes(): array {
		$post_types = array_values( get_post_types( array( 'public' => true ), 'names' ) );
		$post_types = array_map( 'strval', $post_types );
		sort( $post_types, SORT_STRING );

		return $post_types;
	}

	/**
	 * Return one bounded page of normalized WordPress posts.
	 *
	 * @param list<string> $post_types Post types to query.
	 * @param bool         $include_private Whether private posts may be included.
	 * @param int          $page One-based page number.
	 * @param int          $per_page Maximum posts per page.
	 * @return list<WordPressPost>
	 */
	public function posts( array $post_types, bool $include_private, int $page, int $per_page ): array {
		$post_types = array_values( array_unique( array_map( 'strval', $post_types ) ) );
		sort( $post_types, SORT_STRING );

		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$statuses = $include_private ? array( 'publish', 'private' ) : array( 'publish' );

		$posts = get_posts(
			array(
				'post_type'           => $post_types,
				'post_status'         => $statuses,
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'has_password'        => false,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$result = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post && ! is_object( $post ) ) {
				continue;
			}

			$post_id   = (int) $post->ID;
			$post_type = (string) $post->post_type;
			$permalink = get_permalink( $post_id );

			$result[] = new WordPressPost(
				$post_id,
				$post_type,
				(string) $post->post_status,
				(string) $post->post_title,
				(string) $post->post_excerpt,
				(string) $post->post_content,
				is_string( $permalink ) ? $permalink : null,
				(string) $post->post_modified_gmt,
				null,
				'' !== (string) $post->post_password,
				(int) $post->post_author,
				$this->taxonomyLabels( $post_id, $post_type )
			);
		}

		return $result;
	}

	/**
	 * Return selected taxonomy labels for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 * @return array<string, list<array{name:string,slug:string}>>
	 */
	private function taxonomyLabels( int $post_id, string $post_type ): array {
		$taxonomies = array_values( get_object_taxonomies( $post_type, 'names' ) );
		$taxonomies = array_map( 'strval', $taxonomies );
		sort( $taxonomies, SORT_STRING );

		if ( array() === $taxonomies ) {
			return array();
		}

		$terms = wp_get_object_terms( $post_id, $taxonomies, array( 'fields' => 'all' ) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$labels = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term && ! is_object( $term ) ) {
				continue;
			}

			$taxonomy = (string) $term->taxonomy;
			$labels[ $taxonomy ][] = array(
				'name' => (string) $term->name,
				'slug' => (string) $term->slug,
			);
		}

		ksort( $labels, SORT_STRING );
		foreach ( $labels as &$taxonomy_labels ) {
			usort(
				$taxonomy_labels,
				static fn ( array $left, array $right ): int => array( $left['name'], $left['slug'] ) <=> array( $right['name'], $right['slug'] )
			);
		}
		unset( $taxonomy_labels );

		return $labels;
	}
}
