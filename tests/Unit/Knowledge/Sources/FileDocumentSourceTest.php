<?php
/**
 * File document knowledge-source tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\Sources;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\Extraction\DocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\DocumentExtractorRegistry;
use WpRagAiChatbot\Documents\Extraction\ExtractedDocument;
use WpRagAiChatbot\Documents\Extraction\ExtractionException;
use WpRagAiChatbot\Documents\Extraction\FileValidationPolicy;
use WpRagAiChatbot\Documents\Extraction\MimeTypeDetector;
use WpRagAiChatbot\Documents\Extraction\ValidatedFile;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\FileDocumentSource;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;

/**
 * Verifies local files normalize into canonical document records.
 */
final class FileDocumentSourceTest extends TestCase {
	private string $directory;
	private string $path;

	/**
	 * Create an isolated supported file for each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wp-rag-m05-' . uniqid( '', true );
		mkdir( $this->directory );
		$this->path = $this->directory . DIRECTORY_SEPARATOR . 'guide.txt';
		file_put_contents( $this->path, "Hello file\n" );
	}

	/**
	 * Remove isolated file fixtures.
	 */
	protected function tearDown(): void {
		if ( is_file( $this->path ) ) {
			unlink( $this->path );
		}
		if ( is_dir( $this->directory ) ) {
			rmdir( $this->directory );
		}

		parent::tearDown();
	}

	/**
	 * File sources validate, dispatch, extract, and normalize deterministically.
	 */
	public function test_documents_normalizes_validated_file_with_traceable_metadata(): void {
		$source    = $this->source();
		$documents = iterator_to_array( $source->documents( $this->record() ), false );

		self::assertCount( 1, $documents );
		$document = $documents[0];

		self::assertSame( 'file:file-source-1', $document->documentKey );
		self::assertSame( 42, $document->sourceId );
		self::assertSame( 'attachment:99', $document->externalId );
		self::assertSame( 'file', $document->documentType );
		self::assertSame( 'Guide title', $document->title );
		self::assertNull( $document->canonicalUrl );
		self::assertSame( 'Hello file', $document->content );
		self::assertSame( 'en', $document->language );
		self::assertSame( 'private', $document->visibility );
		self::assertSame( hash_file( 'sha256', $this->path ) . ':' . filesize( $this->path ), $document->sourceVersion );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $document->contentHash );
		self::assertSame( 'file', $document->metadata['source_type'] );
		self::assertSame( 'guide.txt', $document->metadata['filename'] );
		self::assertSame( 'txt', $document->metadata['extension'] );
		self::assertSame( 'text/plain', $document->metadata['mime_type'] );
		self::assertSame( filesize( $this->path ), $document->metadata['size'] );
		self::assertSame( hash_file( 'sha256', $this->path ), $document->metadata['file_sha256'] );
		self::assertSame( 'test', $document->metadata['parser'] );

		$again = iterator_to_array( $source->documents( $this->record() ), false )[0];
		self::assertSame( $document->sourceVersion, $again->sourceVersion );
		self::assertSame( $document->contentHash, $again->contentHash );
	}

	/**
	 * A file source must already exist in persistence before normalization.
	 */
	public function test_documents_rejects_unpersisted_source(): void {
		$this->expectException( KnowledgeSourceException::class );
		$this->expectExceptionMessage( 'File source must be persisted before normalization.' );

		iterator_to_array( $this->source()->documents( $this->record( null ) ), false );
	}

	/**
	 * Source type must match the file source implementation.
	 */
	public function test_documents_rejects_wrong_source_type(): void {
		$this->expectException( KnowledgeSourceException::class );
		$this->expectExceptionMessage( 'File source type does not match.' );

		iterator_to_array( $this->source()->documents( $this->record( 42, 'manual_text' ) ), false );
	}

	/**
	 * Extractor-domain failures remain fail-closed and visible to callers.
	 */
	public function test_documents_propagates_extractor_failure(): void {
		$registry = new DocumentExtractorRegistry();
		$registry->register(
			new class() implements DocumentExtractor {
				/**
				 * {@inheritDoc}
				 */
				public function supportedMimeTypes(): array {
					return array( 'text/plain' );
				}

				/**
				 * {@inheritDoc}
				 */
				public function extract( ValidatedFile $file ): ExtractedDocument {
					unset( $file );
					throw new ExtractionException( 'Parser failed safely.' );
				}
			}
		);

		$this->expectException( ExtractionException::class );
		$this->expectExceptionMessage( 'Parser failed safely.' );

		iterator_to_array( $this->source( $registry )->documents( $this->record() ), false );
	}

	/**
	 * Create the file source under test.
	 *
	 * @param DocumentExtractorRegistry|null $registry Optional extractor registry.
	 */
	private function source( ?DocumentExtractorRegistry $registry = null ): FileDocumentSource {
		$detector = new class() implements MimeTypeDetector {
			/**
			 * {@inheritDoc}
			 */
			public function detect( string $path ): string {
				unset( $path );
				return 'text/plain';
			}
		};

		if ( null === $registry ) {
			$registry = new DocumentExtractorRegistry();
			$registry->register(
				new class() implements DocumentExtractor {
					/**
					 * {@inheritDoc}
					 */
					public function supportedMimeTypes(): array {
						return array( 'text/plain' );
					}

					/**
					 * {@inheritDoc}
					 */
					public function extract( ValidatedFile $file ): ExtractedDocument {
						unset( $file );
						return new ExtractedDocument( 'Hello file', array( 'parser' => 'test' ) );
					}
				}
			);
		}

		return new FileDocumentSource( new FileValidationPolicy( $detector ), $registry );
	}

	/**
	 * Create a persisted source fixture.
	 *
	 * @param int|null $id Persisted source ID.
	 * @param string   $type Source type.
	 */
	private function record( ?int $id = 42, string $type = 'file' ): KnowledgeSourceRecord {
		$now = new DateTimeImmutable( '2026-09-03T00:00:00+00:00' );

		return new KnowledgeSourceRecord(
			$id,
			'file-source-1',
			$type,
			'attachment:99',
			'Fallback title',
			null,
			'active',
			array(
				'path'         => $this->path,
				'allowed_root' => $this->directory,
				'title'        => 'Guide title',
				'language'     => 'en',
				'visibility'   => 'private',
			),
			null,
			null,
			$now,
			$now
		);
	}
}
