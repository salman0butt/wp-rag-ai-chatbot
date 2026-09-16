<?php
/**
 * Knowledge source creation REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Knowledge\WordPress\WordPressContentGateway;
use WpRagAiChatbot\Tests\Support\WooCommerce\FakeWooCommerceCatalogGateway;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceCatalogGateway;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceProduct;
use WpRagAiChatbot\Admin\Rest\KnowledgeSourceCreateResource;

/**
 * Verifies the create boundary owns validation, profile configuration, and queue orchestration.
 */
final class KnowledgeSourceCreateResourceTest extends TestCase {
	/** Start WordPress function isolation. */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/** Tear WordPress function isolation down. */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Manual text persists an active source with the server-owned profile and queues one sync job. */
	public function test_creates_manual_text_with_server_owned_profile_and_job(): void {
		$now       = new DateTimeImmutable( '2026-09-16T10:00:00+00:00' );
		$sources   = $this->createMock( KnowledgeSourceRepository::class );
		$jobs      = $this->createMock( JobRepository::class );
		$clock     = $this->clock( $now );
		$saved     = null;
		$source_id = 41;

		$sources->expects( self::once() )->method( 'findByKey' )->willReturn( null );
		$sources->expects( self::once() )
			->method( 'save' )
			->willReturnCallback(
				static function ( KnowledgeSourceRecord $record ) use ( &$saved, $source_id ): KnowledgeSourceRecord {
					$saved = $record;
					return $record->withId( $source_id );
				}
			);
		$jobs->expects( self::once() )
			->method( 'enqueue' )
			->with(
				self::callback(
					static fn ( JobRequest $request ): bool => 'sync.source' === $request->type
						&& 41 === $request->payload['source_id']
						&& 'wp-rag-default' === $request->payload['collection_id']
						&& 'gemini-embedding-001-3072-cosine-v1' === $request->payload['configuration_id']
						&& is_string( $request->payload['generation'] )
				),
				$now
			)
			->willReturn( $this->job( $now ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_single_event' )->once()->andReturn( true );

		$response = $this->resource( $sources, $jobs, $clock )->create(
			array(
				'source_type' => 'manual_text',
				'title'       => "  Owner\n guide ",
				'config'      => array( 'text' => "  Useful\nknowledge.  " ),
			)
		);

		self::assertInstanceOf( KnowledgeSourceRecord::class, $saved );
		self::assertSame( 'active', $saved->status );
		self::assertSame( 'Owner guide', $saved->title );
		self::assertSame( "Useful\nknowledge.", $saved->config['text'] );
		self::assertSame(
			array(
				'collection_id'         => 'wp-rag-default',
				'configuration_id'      => 'gemini-embedding-001-3072-cosine-v1',
				'embedding_provider_id' => 'gemini_direct',
				'embedding_model_id'    => 'gemini-embedding-001',
				'dimensions'            => 3072,
				'normalization'         => 'none',
				'distance'              => 'cosine',
				'vector_store_id'       => 'local-wordpress',
			),
			$saved->config['semantic_retrieval']
		);
		self::assertArrayHasKey( 'source', $response );
		self::assertArrayHasKey( 'job', $response );
		self::assertArrayNotHasKey( 'config', $response['source'] );
		self::assertSame( 'sync.source', $response['job']['type'] );
	}

	/** WordPress content defaults to public post and page synchronization. */
	public function test_wordpress_defaults_are_persisted_without_browser_profile_values(): void {
		$now     = new DateTimeImmutable( '2026-09-16T10:00:00+00:00' );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$jobs    = $this->createMock( JobRepository::class );
		$saved   = null;

		$sources->method( 'findByKey' )->willReturn( null );
		$sources->expects( self::once() )->method( 'save' )->willReturnCallback(
			static function ( KnowledgeSourceRecord $record ) use ( &$saved ): KnowledgeSourceRecord {
				$saved = $record;
				return $record->withId( 42 );
			}
		);
		$jobs->method( 'enqueue' )->willReturn( $this->job( $now ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( true );
		$clock = $this->clock( $now );

		$this->resource( $sources, $jobs, $clock )->create( array( 'source_type' => 'wordpress_posts' ) );

		self::assertInstanceOf( KnowledgeSourceRecord::class, $saved );
		$config = $saved->config;
		unset( $config['semantic_retrieval'] );
		self::assertSame( array( 'include_private' => false ), $config );
	}

	/** FAQ rows must be complete and reject malformed trust-boundary values before persistence. */
	public function test_faq_validation_rejects_incomplete_rows(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock() )->create(
			array(
				'source_type' => 'faq',
				'title'       => 'Support FAQ',
				'config'      => array( 'items' => array( array( 'question' => 'Missing answer' ) ) ),
			)
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
	}

	/** WooCommerce selection must be either a valid product list or catalog mode. */
	public function test_woocommerce_validation_rejects_ambiguous_selection(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock() )->create(
			array(
				'source_type' => 'woocommerce_product',
				'title'       => 'Products',
				'config'      => array(
					'product_ids' => array( 12 ),
					'catalog'     => true,
				),
			)
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
	}

	/** Equivalent normalized title/config values must address one stable source key. */
	public function test_source_key_is_stable_for_equivalent_normalized_input(): void {
		$now           = new DateTimeImmutable( '2026-09-16T10:00:00+00:00' );
		$first         = $this->createMock( KnowledgeSourceRepository::class );
		$second        = $this->createMock( KnowledgeSourceRepository::class );
		$first_record  = null;
		$second_record = null;

		foreach ( array( $first, $second ) as $index => $repository ) {
			$repository->method( 'findByKey' )->willReturn( null );
			$repository->method( 'save' )->willReturnCallback(
				static function ( KnowledgeSourceRecord $record ) use ( &$first_record, &$second_record, $index ): KnowledgeSourceRecord {
					if ( 0 === $index ) {
						$first_record = $record;
					} else {
						$second_record = $record;
					}
					return $record->withId( $index + 1 );
				}
			);
		}
		$jobs = $this->createMock( JobRepository::class );
		$jobs->method( 'enqueue' )->willReturn( $this->job( $now ) );
		$clock = $this->clock( $now );
		Functions\when( 'wp_next_scheduled' )->justReturn( true );

		$this->resource( $first, $jobs, $clock )->create(
			array(
				'source_type' => 'manual_text',
				'title'       => ' Guide ',
				'config'      => array( 'text' => 'Answer' ),
			)
		);
		$this->resource( $second, $jobs, $clock )->create(
			array(
				'source_type' => 'manual_text',
				'title'       => "\nGuide\t",
				'config'      => array( 'text' => ' Answer ' ),
			)
		);

		self::assertInstanceOf( KnowledgeSourceRecord::class, $first_record );
		self::assertInstanceOf( KnowledgeSourceRecord::class, $second_record );
		self::assertSame( $first_record->sourceKey, $second_record->sourceKey );
	}

	/** A duplicate stable key returns a safe conflict without a second save or queue write. */
	public function test_duplicate_source_key_returns_conflict_without_duplicate(): void {
		$existing = $this->source( 9, 'existing-key' );
		$sources  = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::once() )->method( 'findByKey' )->willReturn( $existing );
		$sources->expects( self::never() )->method( 'save' );
		$jobs = $this->createMock( JobRepository::class );
		$jobs->expects( self::never() )->method( 'enqueue' );

		$response = $this->resource( $sources, $jobs, $this->clock() )->create(
			array(
				'source_type' => 'manual_text',
				'title'       => 'Existing',
				'config'      => array( 'text' => 'Text' ),
			)
		);

		self::assertSame( 'conflict', $response['error']['code'] );
	}

	/** Database failures while checking duplicates map to the stable database error. */
	public function test_duplicate_lookup_database_failure_returns_database_error(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::once() )->method( 'findByKey' )->willThrowException( new DatabaseException( 'offline' ) );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock() )->create(
			array(
				'source_type' => 'manual_text',
				'title'       => 'Manual',
				'config'      => array( 'text' => 'Text' ),
			)
		);

		self::assertSame( 'database_error', $response['error']['code'] );
	}

	/** A durable queue insert failure compensates the newly active source. */
	public function test_queue_insert_failure_deletes_new_source(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'findByKey' )->willReturn( null );
		$sources->method( 'save' )->willReturn( $this->source( 17, 'new-key' ) );
		$sources->expects( self::once() )->method( 'delete' )->with( 17 );
		$jobs = $this->createMock( JobRepository::class );
		$jobs->expects( self::once() )->method( 'enqueue' )->willThrowException( new JobQueueException( 'queue down' ) );

		$response = $this->resource( $sources, $jobs, $this->clock() )->create(
			array(
				'source_type' => 'manual_text',
				'title'       => 'Manual',
				'config'      => array( 'text' => 'Text' ),
			)
		);

		self::assertSame( 'queue_error', $response['error']['code'] );
	}

	/** A unique-key race is reported as a conflict after the authoritative re-read. */
	public function test_save_race_re_read_maps_duplicate_to_conflict(): void {
		$existing = $this->source( 19, 'raced-key' );
		$sources  = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::exactly( 2 ) )->method( 'findByKey' )->willReturnOnConsecutiveCalls( null, $existing );
		$sources->expects( self::once() )->method( 'save' )->willThrowException( new DatabaseException( 'duplicate key' ) );
		$jobs = $this->createMock( JobRepository::class );

		$response = $this->resource( $sources, $jobs, $this->clock() )->create(
			array(
				'source_type' => 'manual_text',
				'title'       => 'Manual',
				'config'      => array( 'text' => 'Text' ),
			)
		);

		self::assertSame( 'conflict', $response['error']['code'] );
	}

	/** Syntactically valid but unsupported post types are rejected by the public-type authority. */
	public function test_wordpress_rejects_unknown_public_post_type(): void {
		$gateway = $this->createMock( WordPressContentGateway::class );
		$gateway->expects( self::once() )->method( 'publicPostTypes' )->willReturn( array( 'post', 'page' ) );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock(), $gateway )->create(
			array(
				'source_type' => 'wordpress_posts',
				'config'      => array( 'post_types' => array( 'valid_custom' ) ),
			)
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
	}

	/** Public custom post types remain valid when supplied by the local authority. */
	public function test_wordpress_accepts_public_custom_post_type(): void {
		$gateway = $this->createMock( WordPressContentGateway::class );
		$gateway->expects( self::once() )->method( 'publicPostTypes' )->willReturn( array( 'page', 'kb_article', 'post' ) );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'findByKey' )->willReturn( null );
		$sources->method( 'save' )->willReturn( $this->source( 21, 'custom-key' ) );
		$jobs = $this->createMock( JobRepository::class );
		$jobs->method( 'enqueue' )->willReturn( $this->job( new DateTimeImmutable( '2026-09-16T10:00:00+00:00' ) ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( true );

		$response = $this->resource( $sources, $jobs, $this->clock(), $gateway )->create(
			array(
				'source_type' => 'wordpress_posts',
				'config'      => array( 'post_types' => array( 'kb_article' ) ),
			)
		);

		self::assertArrayHasKey( 'source', $response );
	}

	/** WooCommerce-unavailable installations reject explicit selection locally. */
	public function test_woocommerce_unavailable_is_rejected(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock(), null, new FakeWooCommerceCatalogGateway( false ) )->create(
			array(
				'source_type' => 'woocommerce_product',
				'config'      => array( 'product_ids' => array( 12 ) ),
			)
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
	}

	/** WooCommerce explicit selections reject missing or non-public products. */
	public function test_woocommerce_rejects_all_invalid_products(): void {
		$gateway = new FakeWooCommerceCatalogGateway( true );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock(), null, $gateway )->create(
			array(
				'source_type' => 'woocommerce_product',
				'config'      => array( 'product_ids' => array( 12, 13 ) ),
			)
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
		self::assertSame( array( 12 ), $gateway->product_calls );
	}

	/** WooCommerce keeps only normalized valid explicit product IDs and preserves catalog mode. */
	public function test_woocommerce_accepts_valid_product_and_catalog_mode(): void {
		$product = $this->product( 12 );
		$gateway = new FakeWooCommerceCatalogGateway( true, array(), array( 12 => $product ) );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'findByKey' )->willReturn( null );
		$sources->method( 'save' )->willReturnCallback( static fn ( KnowledgeSourceRecord $record ): KnowledgeSourceRecord => $record->withId( 22 ) );
		$jobs = $this->createMock( JobRepository::class );
		$jobs->method( 'enqueue' )->willReturn( $this->job( new DateTimeImmutable( '2026-09-16T10:00:00+00:00' ) ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( true );

		$response = $this->resource( $sources, $jobs, $this->clock(), null, $gateway )->create(
			array(
				'source_type' => 'woocommerce_product',
				'config'      => array( 'product_ids' => array( 12 ) ),
			)
		);

		self::assertArrayHasKey( 'source', $response );
		self::assertSame( array( 12 ), $gateway->product_calls );

		$catalog = $this->resource( $sources, $jobs, $this->clock(), null, $gateway )->create(
			array(
				'source_type' => 'woocommerce_product',
				'config'      => array( 'catalog' => true ),
			)
		);
		self::assertArrayHasKey( 'source', $catalog );
	}

	/** Browser-supplied file paths are never accepted as file source configuration. */
	public function test_file_path_injection_is_rejected(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock() )->create(
			array(
				'source_type' => 'file',
				'title'       => 'Unsafe file',
				'config'      => array( 'path' => '/tmp/secret.txt' ),
			)
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
	}

	/** Invalid multipart uploads are rejected before a source is persisted. */
	public function test_invalid_uploaded_file_is_rejected(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock() )->create(
			array(
				'source_type' => 'file',
				'title'       => 'Upload',
			),
			array(
				'name'     => '../escape.txt',
				'tmp_name' => '/tmp/upload.txt',
				'type'     => 'text/plain',
				'size'     => 12,
				'error'    => UPLOAD_ERR_OK,
			)
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
	}

	/** A valid multipart upload is moved into the plugin-owned upload directory. */
	public function test_valid_multipart_upload_creates_file_source(): void {
		$root    = $this->configureUpload( 'guide.txt', 'same bytes' );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'findByKey' )->willReturn( null );
		$sources->method( 'save' )->willReturn( $this->source( 31, 'file-key' ) );
		$jobs = $this->createMock( JobRepository::class );
		$jobs->method( 'enqueue' )->willReturn( $this->job( new DateTimeImmutable( '2026-09-16T10:00:00+00:00' ) ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( true );

		$response = $this->resource( $sources, $jobs, $this->clock() )->create(
			array( 'source_type' => 'file' ),
			$this->upload( 'guide.txt', 10 )
		);

		self::assertArrayHasKey( 'source', $response );
		self::assertFileExists( $root . '/wp-rag-ai-chatbot/guide.txt' );
		$this->removeDirectory( $root );
	}

	/** A WordPress upload API rejection maps to validation_error. */
	public function test_multipart_mime_rejection_is_validation_error(): void {
		$root    = $this->configureUpload( 'guide.exe', 'bytes', 'application/octet-stream', array( 'error' => 'type' ) );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock() )->create(
			array( 'source_type' => 'file' ),
			$this->upload( 'guide.exe', 5 )
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
		$this->removeDirectory( $root );
	}

	/** Oversized uploads are rejected before WordPress moves them. */
	public function test_multipart_size_rejection_is_validation_error(): void {
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock() )->create(
			array( 'source_type' => 'file' ),
			$this->upload( 'guide.txt', 10485761 )
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
	}

	/** A file outside the plugin-owned root is rejected without deleting the outside file. */
	public function test_multipart_containment_rejection_preserves_outside_file(): void {
		$root    = $this->configureUpload( 'guide.txt', 'bytes' );
		$outside = tempnam( sys_get_temp_dir(), 'wp-rag-outside-' );
		self::assertIsString( $outside );
		Functions\when( 'wp_handle_upload' )->alias( static fn (): array => array( 'file' => $outside ) );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::never() )->method( 'save' );

		$response = $this->resource( $sources, $this->createMock( JobRepository::class ), $this->clock() )->create(
			array( 'source_type' => 'file' ),
			$this->upload( 'guide.txt', 5 )
		);

		self::assertSame( 'validation_error', $response['error']['code'] );
		self::assertFileExists( $outside );
		unlink( $outside );
		$this->removeDirectory( $root );
	}

	/** A post-move queue failure removes the newly uploaded file and source. */
	public function test_post_move_failure_cleans_uploaded_file(): void {
		$root    = $this->configureUpload( 'guide.txt', 'same bytes' );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->method( 'findByKey' )->willReturn( null );
		$sources->method( 'save' )->willReturn( $this->source( 32, 'file-key' ) );
		$sources->expects( self::once() )->method( 'delete' )->with( 32 );
		$jobs = $this->createMock( JobRepository::class );
		$jobs->method( 'enqueue' )->willThrowException( new JobQueueException( 'queue down' ) );

		$response = $this->resource( $sources, $jobs, $this->clock() )->create(
			array( 'source_type' => 'file' ),
			$this->upload( 'guide.txt', 10 )
		);

		self::assertSame( 'queue_error', $response['error']['code'] );
		self::assertFileDoesNotExist( $root . '/wp-rag-ai-chatbot/guide.txt' );
		$this->removeDirectory( $root );
	}

	/** Identical file bytes keep one identity across collision-renamed upload paths. */
	public function test_identical_file_bytes_conflict_and_cleanup_collision_upload(): void {
		$root      = $this->configureUpload( 'guide.txt', 'same bytes' );
		$existing  = null;
		$first_key = null;
		$sources   = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::exactly( 2 ) )->method( 'findByKey' )->willReturnCallback(
			function ( string $source_key ) use ( &$existing, &$first_key ): ?KnowledgeSourceRecord {
				if ( null === $first_key ) {
					$first_key = $source_key;
					return null;
				}
				self::assertSame( $first_key, $source_key );
				return $existing;
			}
		);
		$sources->expects( self::once() )->method( 'save' )->willReturnCallback(
			static function ( KnowledgeSourceRecord $record ) use ( &$existing ): KnowledgeSourceRecord {
				$existing = $record->withId( 33 );
				return $existing;
			}
		);
		$jobs = $this->createMock( JobRepository::class );
		$jobs->method( 'enqueue' )->willReturn( $this->job( new DateTimeImmutable( '2026-09-16T10:00:00+00:00' ) ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( true );

		$this->resource( $sources, $jobs, $this->clock() )->create(
			array( 'source_type' => 'file' ),
			$this->upload( 'guide.txt', 10 )
		);
		Functions\when( 'wp_handle_upload' )->alias(
			static function () use ( $root ): array {
				$path = $root . '/wp-rag-ai-chatbot/guide-1.txt';
				file_put_contents( $path, 'same bytes' );
				return array(
					'file' => $path,
					'url'  => 'https://example.test/guide-1.txt',
					'type' => 'text/plain',
				);
			}
		);
		$response = $this->resource( $sources, $jobs, $this->clock() )->create(
			array( 'source_type' => 'file' ),
			$this->upload( 'guide.txt', 10 )
		);

		self::assertSame( 'conflict', $response['error']['code'] );
		self::assertFileDoesNotExist( $root . '/wp-rag-ai-chatbot/guide-1.txt' );
		$this->removeDirectory( $root );
	}

	/** Build the create resource through the existing queue boundary. */
	private function resource( KnowledgeSourceRepository $sources, JobRepository $jobs, Clock $clock, ?WordPressContentGateway $wordpress = null, ?WooCommerceCatalogGateway $woocommerce = null ): KnowledgeSourceCreateResource {
		$wordpress ??= $this->createMock( WordPressContentGateway::class );
		$wordpress->method( 'publicPostTypes' )->willReturn( array( 'page', 'post' ) );
		$woocommerce ??= new FakeWooCommerceCatalogGateway( true );

		return new KnowledgeSourceCreateResource( $sources, $jobs, $clock, $wordpress, $woocommerce );
	}

	/** Build a deterministic clock fixture. */
	private function clock( ?DateTimeImmutable $now = null ): Clock {
		$clock = $this->createMock( Clock::class );
		$clock->method( 'now' )->willReturn( $now ?? new DateTimeImmutable( '2026-09-16T10:00:00+00:00' ) );
		return $clock;
	}

	/** Build one queued source-sync record fixture. */
	private function job( DateTimeImmutable $now ): JobRecord {
		return new JobRecord(
			id: 1,
			job_key: 'sync-job-1',
			type: 'sync.source',
			status: JobStatus::QUEUED,
			idempotency_key: null,
			payload: array(
				'source_id'        => 41,
				'collection_id'    => 'wp-rag-default',
				'configuration_id' => 'gemini-embedding-001-3072-cosine-v1',
				'generation'       => 'generation-1',
			),
			attempts: 0,
			max_attempts: 3,
			available_at: $now,
			lease_owner: null,
			lease_expires_at: null,
			cancel_requested_at: null,
			progress_current: null,
			progress_total: null,
			progress_message: null,
			last_error_code: null,
			last_error_message: null,
			started_at: null,
			completed_at: null,
			created_at: $now,
			updated_at: $now
		);
	}

	/** Build an existing safe source fixture. */
	private function source( int $id, string $source_key ): KnowledgeSourceRecord {
		$now = new DateTimeImmutable( '2026-09-16T10:00:00+00:00', new DateTimeZone( 'UTC' ) );
		return new KnowledgeSourceRecord( $id, $source_key, 'manual_text', null, 'Existing', null, 'active', array( 'text' => 'Text' ), str_repeat( 'a', 64 ), null, $now, $now );
	}

	/** Build one eligible WooCommerce product fixture. */
	private function product( int $id ): WooCommerceProduct {
		return new WooCommerceProduct( $id, 'simple', 'publish', 'visible', 'Product', '', 'Description', null, 'https://example.test/product/' . $id, array(), array(), array(), array(), '2026-09-16T00:00:00+00:00' );
	}

	/** Configure the WordPress upload seam for a deterministic moved file. */
	private function configureUpload( string $stored_name, string $contents, string $type = 'text/plain', array $result = array() ): string {
		$root = sys_get_temp_dir() . '/wp-rag-upload-' . bin2hex( random_bytes( 4 ) );
		mkdir( $root . '/wp-rag-ai-chatbot', 0777, true );
		Functions\when( 'wp_upload_dir' )->justReturn(
			array(
				'basedir' => $root,
				'baseurl' => 'https://example.test/uploads',
			)
		);
		Functions\when( 'wp_mkdir_p' )->alias( static fn ( string $path ): bool => is_dir( $path ) || mkdir( $path, 0777, true ) );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'remove_filter' )->justReturn( true );
		Functions\when( 'wp_handle_upload' )->alias(
			static function () use ( $root, $stored_name, $contents, $type, $result ): array {
				if ( array() !== $result ) {
					return $result;
				}
				$path = $root . '/wp-rag-ai-chatbot/' . $stored_name;
				file_put_contents( $path, $contents );
				return array(
					'file' => $path,
					'url'  => 'https://example.test/' . $stored_name,
					'type' => $type,
				);
			}
		);

		return $root;
	}

	/** Build one upload fixture accepted by the request boundary. */
	private function upload( string $name, int $size ): array {
		return array(
			'name'     => $name,
			'tmp_name' => '/tmp/upload-' . bin2hex( random_bytes( 4 ) ),
			'type'     => 'text/plain',
			'size'     => $size,
			'error'    => UPLOAD_ERR_OK,
		);
	}

	/** Remove one temporary upload fixture. */
	private function removeDirectory( string $root ): void {
		$files = glob( $root . '/wp-rag-ai-chatbot/*' );
		if ( false === $files ) {
			$files = array();
		}
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		@rmdir( $root . '/wp-rag-ai-chatbot' );
		@rmdir( $root );
	}
}
