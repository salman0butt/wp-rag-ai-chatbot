<?php
/**
 * WordPress post/page/public-CPT knowledge source.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\Sources;

use JsonException;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\WordPress\WordPressContentGateway;
use WpRagAiChatbot\Knowledge\WordPress\WordPressPost;

/**
 * Normalizes WordPress posts, pages, and configured public post types.
 */
final class WordPressPostSource implements KnowledgeSource {
	private const PAGE_SIZE = 100;

	/**
	 * Create a WordPress post source.
	 *
	 * @param WordPressContentGateway $gateway WordPress content gateway.
	 */
	public function __construct( private WordPressContentGateway $gateway ) {
	}

	/**
	 * Return the stable source type.
	 */
	public function type(): string {
		return 'wordpress_posts';
	}

	/**
	 * Normalize WordPress content into canonical documents.
	 *
	 * @param KnowledgeSourceRecord $source Persisted WordPress content source.
	 * @return iterable<int, DocumentRecord>
	 * @throws KnowledgeSourceException When the source or configuration is invalid.
	 */
	public function documents( KnowledgeSourceRecord $source ): iterable {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved domain/gateway records intentionally use camelCase public properties.
		if ( $this->type() !== $source->sourceType ) {
			throw new KnowledgeSourceException( 'WordPress post source type does not match.' );
		}
		if ( null === $source->id || $source->id < 1 ) {
			throw new KnowledgeSourceException( 'WordPress post source must be persisted before normalization.' );
		}

		$include_private = $source->config['include_private'] ?? false;
		if ( ! is_bool( $include_private ) ) {
			throw new KnowledgeSourceException( 'WordPress post source include_private must be boolean.' );
		}

		$post_types = $this->postTypes( $source );
		$page       = 1;

		do {
			$posts = $this->gateway->posts( $post_types, $include_private, $page, self::PAGE_SIZE );
			foreach ( $posts as $post ) {
				if ( ! in_array( $post->type, $post_types, true ) || $post->passwordProtected ) {
					continue;
				}
				if ( 'publish' !== $post->status && ( ! $include_private || 'private' !== $post->status ) ) {
					continue;
				}

				yield $this->document( $source, $post );
			}

			++$page;
		} while ( count( $posts ) >= self::PAGE_SIZE );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Resolve and validate selected public post types.
	 *
	 * @param KnowledgeSourceRecord $source Source configuration.
	 * @return array<int, string>
	 * @throws KnowledgeSourceException When an explicitly configured type is invalid or non-public.
	 */
	private function postTypes( KnowledgeSourceRecord $source ): array {
		$public_post_types = array_values( array_unique( array_map( 'strval', $this->gateway->publicPostTypes() ) ) );
		sort( $public_post_types, SORT_STRING );

		$configured = $source->config['post_types'] ?? null;
		if ( null === $configured ) {
			$post_types = array_values( array_intersect( array( 'page', 'post' ), $public_post_types ) );
			sort( $post_types, SORT_STRING );

			return $post_types;
		}
		if ( ! is_array( $configured ) || ! array_is_list( $configured ) || array() === $configured ) {
			throw new KnowledgeSourceException( 'WordPress post source post_types must be a non-empty list.' );
		}

		$post_types = array();
		foreach ( $configured as $post_type ) {
			if ( ! is_string( $post_type ) || '' === trim( $post_type ) ) {
				throw new KnowledgeSourceException( 'WordPress post source contains an invalid post type.' );
			}
			$post_type = trim( $post_type );
			if ( ! in_array( $post_type, $public_post_types, true ) ) {
				throw new KnowledgeSourceException( 'WordPress post source contains a non-public or unsupported post type.' );
			}
			$post_types[] = $post_type;
		}

		$post_types = array_values( array_unique( $post_types ) );
		sort( $post_types, SORT_STRING );

		return $post_types;
	}

	/**
	 * Build one canonical document.
	 *
	 * @param KnowledgeSourceRecord $source Persisted source.
	 * @param WordPressPost         $post Gateway post.
	 * @throws KnowledgeSourceException When hashing fails.
	 */
	private function document( KnowledgeSourceRecord $source, WordPressPost $post ): DocumentRecord {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved domain/gateway records intentionally use camelCase public properties.
		$taxonomy_labels = $this->taxonomyLabels( $post->taxonomyLabels );
		$title           = $this->normalizeText( $post->title );
		$content         = $this->content( $title, $post->excerpt, $post->content, $taxonomy_labels );
		$document_key    = 'wp-post:' . $post->type . ':' . $post->id;
		$source_version  = $post->modifiedGmt . ':' . $post->id;
		$visibility      = 'private' === $post->status ? 'private' : 'public';
		$metadata        = array(
			'source_type' => $this->type(),
			'post_id'     => $post->id,
			'post_type'   => $post->type,
			'post_status' => $post->status,
			'author_id'   => $post->authorId,
			'taxonomies'  => $taxonomy_labels,
		);

		try {
			$content_hash = DocumentHasher::hash(
				array(
					'document_key'   => $document_key,
					'external_id'    => (string) $post->id,
					'document_type'  => $this->type(),
					'title'          => $title,
					'canonical_url'  => $post->url,
					'content'        => $content,
					'metadata'       => $metadata,
					'source_version' => $source_version,
					'language'       => $post->language,
					'visibility'     => $visibility,
				)
			);
		} catch ( JsonException $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Previous Throwable is not rendered output.
			throw new KnowledgeSourceException( 'WordPress post source could not be hashed.', 0, $exception );
		}

		return new DocumentRecord(
			null,
			$document_key,
			$source->id,
			(string) $post->id,
			$this->type(),
			$title,
			$post->url,
			$content,
			$metadata,
			$source_version,
			$content_hash,
			$post->language,
			$visibility,
			$source->updatedAt,
			$source->updatedAt
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Normalize content sections and deterministic taxonomy labels.
	 *
	 * @param string                                                    $title Normalized title.
	 * @param string                                                    $excerpt Post excerpt.
	 * @param string                                                    $body Post content.
	 * @param array<string, array<int, array{name:string,slug:string}>> $taxonomy_labels Taxonomy labels.
	 */
	private function content( string $title, string $excerpt, string $body, array $taxonomy_labels ): string {
		$sections = array();
		foreach ( array( $title, $this->normalizeText( $excerpt ), $this->normalizeText( $body ) ) as $section ) {
			if ( '' !== $section ) {
				$sections[] = $section;
			}
		}

		$taxonomy_lines = array();
		foreach ( $taxonomy_labels as $taxonomy => $terms ) {
			$names = array_map( static fn ( array $term ): string => $term['name'], $terms );
			if ( array() !== $names ) {
				$taxonomy_lines[] = $taxonomy . ': ' . implode( ', ', $names );
			}
		}
		if ( array() !== $taxonomy_lines ) {
			$sections[] = implode( "\n", $taxonomy_lines );
		}

		return implode( "\n\n", $sections );
	}

	/**
	 * Normalize taxonomy labels into deterministic taxonomy/term order.
	 *
	 * @param array<string, array<int, array{name:string,slug:string}>> $taxonomy_labels Raw gateway labels.
	 * @return array<string, array<int, array{name:string,slug:string}>>
	 */
	private function taxonomyLabels( array $taxonomy_labels ): array {
		ksort( $taxonomy_labels, SORT_STRING );
		foreach ( $taxonomy_labels as &$terms ) {
			usort(
				$terms,
				static fn ( array $left, array $right ): int => array( $left['name'], $left['slug'] ) <=> array( $right['name'], $right['slug'] )
			);
		}
		unset( $terms );

		return $taxonomy_labels;
	}

	/**
	 * Normalize surrounding whitespace and line endings without changing meaning.
	 *
	 * @param string $text Text value.
	 */
	private function normalizeText( string $text ): string {
		return trim( str_replace( array( "\r\n", "\r" ), "\n", $text ) );
	}
}
