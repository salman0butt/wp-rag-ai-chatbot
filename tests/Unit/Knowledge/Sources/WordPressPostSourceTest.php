<?php
/**
 * WordPress post knowledge source tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\Sources;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;
use WpRagAiChatbot\Knowledge\Sources\WordPressPostSource;
use WpRagAiChatbot\Knowledge\WordPress\WordPressPost;
use WpRagAiChatbot\Tests\Support\Knowledge\FakeWordPressContentGateway;

/**
 * Verifies deterministic WordPress post normalization.
 */
final class WordPressPostSourceTest extends TestCase {
	/**
	 * Default post/page content is normalized into traceable documents.
	 */
	public function test_normalizes_default_post_and_page_with_traceable_metadata(): void {
		$this->requireSource();
		$gateway = new FakeWordPressContentGateway(
			array( 'book', 'page', 'post' ),
			array(
				1 => array(
					$this->post(
						id: 12,
						type: 'post',
						title: '  Hello World  ',
						excerpt: ' Summary ',
						content: " Body\ntext ",
						taxonomy_labels: array(
							'post_tag' => array(
								array(
									'name' => 'Zed',
									'slug' => 'zed',
								),
							),
							'category' => array(
								array(
									'name' => 'Beta',
									'slug' => 'beta',
								),
								array(
									'name' => 'Alpha',
									'slug' => 'alpha',
								),
							),
						)
					),
					$this->post( id: 13, type: 'page', title: 'About', content: 'About body' ),
					$this->post( id: 14, type: 'book', title: 'Ignored CPT' ),
				),
			),
		);
		$source     = $this->source();
		$normalizer = new WordPressPostSource( $gateway );
		$documents  = iterator_to_array( $normalizer->documents( $source ) );

		self::assertCount( 2, $documents );
		self::assertSame( 'wordpress_posts', $normalizer->type() );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain records use the approved camelCase contract.
		self::assertSame( 'wp-post:post:12', $documents[0]->documentKey );
		self::assertSame( '12', $documents[0]->externalId );
		self::assertSame( 'Hello World', $documents[0]->title );
		self::assertSame( 'https://example.test/?p=12', $documents[0]->canonicalUrl );
		self::assertSame( "Hello World\n\nSummary\n\nBody\ntext\n\ncategory: Alpha, Beta\npost_tag: Zed", $documents[0]->content );
		self::assertSame( '2026-09-03 00:01:02:12', $documents[0]->sourceVersion );
		self::assertSame( 'en', $documents[0]->language );
		self::assertSame( 'public', $documents[0]->visibility );
		self::assertSame(
			array(
				'source_type' => 'wordpress_posts',
				'post_id'     => 12,
				'post_type'   => 'post',
				'post_status' => 'publish',
				'author_id'   => 7,
				'taxonomies'  => array(
					'category' => array(
						array(
							'name' => 'Alpha',
							'slug' => 'alpha',
						),
						array(
							'name' => 'Beta',
							'slug' => 'beta',
						),
					),
					'post_tag' => array(
						array(
							'name' => 'Zed',
							'slug' => 'zed',
						),
					),
				),
			),
			$documents[0]->metadata
		);
		self::assertSame( $source->updatedAt, $documents[0]->createdAt );
		self::assertSame( $source->updatedAt, $documents[0]->updatedAt );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		self::assertSame( array( 'page', 'post' ), $gateway->calls[0]['postTypes'] );
		self::assertFalse( $gateway->calls[0]['includePrivate'] );
		self::assertSame( 100, $gateway->calls[0]['perPage'] );
	}

	/**
	 * Explicit public CPTs are allowed while unsupported types fail closed.
	 */
	public function test_accepts_public_cpt_and_rejects_unsupported_type(): void {
		$this->requireSource();
		$gateway   = new FakeWordPressContentGateway(
			array( 'book', 'page', 'post' ),
			array( 1 => array( $this->post( id: 21, type: 'book', title: 'Book' ) ) )
		);
		$documents = iterator_to_array(
			( new WordPressPostSource( $gateway ) )->documents( $this->source( array( 'post_types' => array( 'book' ) ) ) )
		);
		self::assertCount( 1, $documents );
		self::assertSame( 'wp-post:book:21', $documents[0]->documentKey ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array(
			( new WordPressPostSource( $gateway ) )->documents( $this->source( array( 'post_types' => array( 'secret_type' ) ) ) )
		);
	}

	/**
	 * Only allowed status and access combinations are emitted.
	 */
	public function test_enforces_status_private_and_password_boundaries(): void {
		$this->requireSource();
		$gateway   = new FakeWordPressContentGateway(
			array( 'post' ),
			array(
				1 => array(
					$this->post( id: 1, status: 'draft' ),
					$this->post( id: 2, status: 'pending' ),
					$this->post( id: 3, status: 'trash' ),
					$this->post( id: 4, status: 'private' ),
					$this->post( id: 5, password_protected: true ),
					$this->post( id: 6, title: 'Allowed' ),
				),
			)
		);
		$documents = iterator_to_array(
			( new WordPressPostSource( $gateway ) )->documents( $this->source( array( 'post_types' => array( 'post' ) ) ) )
		);
		self::assertCount( 1, $documents );
		self::assertSame( 'wp-post:post:6', $documents[0]->documentKey ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$private_gateway   = new FakeWordPressContentGateway(
			array( 'post' ),
			array( 1 => array( $this->post( id: 7, status: 'private', title: 'Private' ) ) )
		);
		$private_documents = iterator_to_array(
			( new WordPressPostSource( $private_gateway ) )->documents(
				$this->source(
					array(
						'post_types'      => array( 'post' ),
						'include_private' => true,
					)
				)
			)
		);
		self::assertCount( 1, $private_documents );
		self::assertSame( 'private', $private_documents[0]->visibility ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		self::assertTrue( $private_gateway->calls[0]['includePrivate'] );
	}

	/**
	 * Equivalent taxonomy ordering produces an identical content hash.
	 */
	public function test_hash_is_stable_for_equivalent_taxonomy_order(): void {
		$this->requireSource();
		$first_gateway  = new FakeWordPressContentGateway(
			array( 'post' ),
			array(
				1 => array(
					$this->post(
						id: 41,
						taxonomy_labels: array(
							'topic' => array(
								array(
									'name' => 'Beta',
									'slug' => 'beta',
								),
								array(
									'name' => 'Alpha',
									'slug' => 'alpha',
								),
							),
						)
					),
				),
			)
		);
		$second_gateway = new FakeWordPressContentGateway(
			array( 'post' ),
			array(
				1 => array(
					$this->post(
						id: 41,
						taxonomy_labels: array(
							'topic' => array(
								array(
									'name' => 'Alpha',
									'slug' => 'alpha',
								),
								array(
									'name' => 'Beta',
									'slug' => 'beta',
								),
							),
						)
					),
				),
			)
		);
		$config = array( 'post_types' => array( 'post' ) );
		$first  = iterator_to_array( ( new WordPressPostSource( $first_gateway ) )->documents( $this->source( $config ) ) );
		$second = iterator_to_array( ( new WordPressPostSource( $second_gateway ) )->documents( $this->source( $config ) ) );
		self::assertSame( $first[0]->contentHash, $second[0]->contentHash ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Pagination is bounded at 100 records and continues until a short page.
	 */
	public function test_consumes_gateway_in_bounded_pages_until_short_page(): void {
		$this->requireSource();
		$page_one = array();
		for ( $id = 1; $id <= 100; $id++ ) {
			$page_one[] = $this->post( id: $id );
		}
		$gateway   = new FakeWordPressContentGateway(
			array( 'post' ),
			array(
				1 => $page_one,
				2 => array( $this->post( id: 101 ) ),
			)
		);
		$documents = iterator_to_array(
			( new WordPressPostSource( $gateway ) )->documents( $this->source( array( 'post_types' => array( 'post' ) ) ) )
		);
		self::assertCount( 101, $documents );
		self::assertCount( 2, $gateway->calls );
		self::assertSame( 2, $gateway->calls[1]['page'] );
		self::assertSame( 100, $gateway->calls[1]['perPage'] );
	}

	/**
	 * Source type and persistence invariants fail closed.
	 */
	public function test_rejects_wrong_type(): void {
		$this->requireSource();
		$gateway = new FakeWordPressContentGateway( array( 'post' ), array() );
		$this->expectException( KnowledgeSourceException::class );
		iterator_to_array( ( new WordPressPostSource( $gateway ) )->documents( $this->source( source_type: 'faq' ) ) );
	}

	/**
	 * Assert the implementation is available before behavior assertions run.
	 */
	private function requireSource(): void {
		self::assertTrue( class_exists( WordPressPostSource::class ), 'WordPressPostSource must exist.' );
	}

	/**
	 * Create a persisted WordPress source record.
	 *
	 * @param array<string, mixed> $config Source config.
	 * @param string               $source_type Source type.
	 */
	private function source( array $config = array(), string $source_type = 'wordpress_posts' ): KnowledgeSourceRecord {
		$time = new DateTimeImmutable( '2026-09-03 00:00:00', new DateTimeZone( 'UTC' ) );
		return new KnowledgeSourceRecord( 17, 'site-content', $source_type, null, 'Site content', null, 'active', $config, null, null, $time, $time );
	}

	/**
	 * Create a gateway post fixture.
	 *
	 * @param int                                                       $id Post ID.
	 * @param string                                                    $type Post type.
	 * @param string                                                    $status Status.
	 * @param string                                                    $title Title.
	 * @param string                                                    $excerpt Excerpt.
	 * @param string                                                    $content Content.
	 * @param string|null                                               $url URL.
	 * @param string                                                    $modified_gmt Modified GMT.
	 * @param string|null                                               $language Language.
	 * @param bool                                                      $password_protected Password flag.
	 * @param int                                                       $author_id Author ID.
	 * @param array<string, array<int, array{name:string,slug:string}>> $taxonomy_labels Taxonomy labels.
	 */
	private function post(
		int $id,
		string $type = 'post',
		string $status = 'publish',
		string $title = 'Title',
		string $excerpt = '',
		string $content = 'Body',
		?string $url = null,
		string $modified_gmt = '2026-09-03 00:01:02',
		?string $language = 'en',
		bool $password_protected = false,
		int $author_id = 7,
		array $taxonomy_labels = array()
	): WordPressPost {
		return new WordPressPost(
			$id,
			$type,
			$status,
			$title,
			$excerpt,
			$content,
			$url ?? 'https://example.test/?p=' . $id,
			$modified_gmt,
			$language,
			$password_protected,
			$author_id,
			$taxonomy_labels
		);
	}
}
