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

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
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

	/** Build the create resource through the existing queue boundary. */
	private function resource( KnowledgeSourceRepository $sources, JobRepository $jobs, Clock $clock ): KnowledgeSourceCreateResource {
		return new KnowledgeSourceCreateResource( $sources, $jobs, $clock );
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
		return new KnowledgeSourceRecord( $id, $source_key, 'manual_text', null, 'Existing', null, 'active', array( 'text' => 'Text' ), null, null, $now, $now );
	}
}
