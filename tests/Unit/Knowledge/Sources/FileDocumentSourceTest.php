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
	/**
	 * Temporary paths created by a test.
	 *
	 * @var list<string>
	 */
	private array $temporary_paths = array();

	/**
	 * Remove temporary files and directories after each test.
	 */
	protected function tearDown(): void {
		// phpcs:disable WordPress.WP.AlternativeFunctions -- Unit fixtures must exercise native local-file behavior outside WordPress bootstrap.
		foreach ( array_reverse( $this->temporary_paths ) as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
				continue;
			}
			if ( is_dir( $path ) ) {
				rmdir( $path );
			}
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions

		$this->temporary_paths = array();
		parent::tearDown();
	}

	/**
	 * File sources validate, dispatch, extract, and normalize deterministically.
	 */
	public function test_documents_normalizes_validated_file_with_traceable_metadata(): void {
		$this->requireTask5Contract();
		$path      = $this->createFile( 'guide.txt', "Hello file\n" );
		$directory = dirname( $path );
		$source    = $this->source();
		$documents = iterator_to_array( $source->documents( $this->record( $path, $directory ) ), false );

		self::assertCount( 1, $documents );
		$document = $documents[0];

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DocumentRecord public API follows the approved domain contract.
		self::assertSame( 'file:file-source-1', $document->documentKey );
		self::assertSame( 42, $document->sourceId );
		self::assertSame( 'attachment:99', $document->externalId );
		self::assertSame( 'file', $document->documentType );
		self::assertSame( 'Guide title', $document->title );
		self::assertNull( $document->canonicalUrl );
		self::assertSame( 'Hello file', $document->content );
		self::assertSame( 'en', $document->language );
		self::assertSame( 'private', $document->visibility );
		self::assertSame( hash_file( 'sha256', $path ) . ':' . filesize( $path ), $document->sourceVersion );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $document->contentHash );
		self::assertSame( 'file', $document->metadata['source_type'] );
		self::assertSame( 'guide.txt', $document->metadata['filename'] );
		self::assertSame( 'txt', $document->metadata['extension'] );
		self::assertSame( 'text/plain', $document->metadata['mime_type'] );
		self::assertSame( filesize( $path ), $document->metadata['size'] );
		self::assertSame( hash_file( 'sha256', $path ), $document->metadata['file_sha256'] );
		self::assertSame( 'test', $document->metadata['parser'] );

		$again = iterator_to_array( $source->documents( $this->record( $path, $directory ) ), false )[0];
		self::assertSame( $document->sourceVersion, $again->sourceVersion );
		self::assertSame( $document->contentHash, $again->contentHash );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * A file source must already exist in persistence before normalization.
	 */
	public function test_documents_rejects_unpersisted_source(): void {
		$this->requireTask5Contract();
		$path = $this->createFile( 'guide.txt', 'Hello file' );

		$this->expectException( KnowledgeSourceException::class );
		$this->expectExceptionMessage( 'File source must be persisted before normalization.' );

		iterator_to_array( $this->source()->documents( $this->record( $path, dirname( $path ), null ) ), false );
	}

	/**
	 * Source type must match the file source implementation.
	 */
	public function test_documents_rejects_wrong_source_type(): void {
		$this->requireTask5Contract();
		$path = $this->createFile( 'guide.txt', 'Hello file' );

		$this->expectException( KnowledgeSourceException::class );
		$this->expectExceptionMessage( 'File source type does not match.' );

		iterator_to_array( $this->source()->documents( $this->record( $path, dirname( $path ), 42, 'manual_text' ) ), false );
	}

	/**
	 * Extractor-domain failures remain fail-closed and visible to callers.
	 */
	public function test_documents_propagates_extractor_failure(): void {
		$this->requireTask5Contract();
		$path     = $this->createFile( 'guide.txt', 'Hello file' );
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
				 * Fail extraction deliberately.
				 *
				 * @param ValidatedFile $file Validated file.
				 * @throws ExtractionException Always for this test double.
				 */
				public function extract( ValidatedFile $file ): ExtractedDocument {
					unset( $file );
					throw new ExtractionException( 'Parser failed safely.' );
				}
			}
		);

		$this->expectException( ExtractionException::class );
		$this->expectExceptionMessage( 'Parser failed safely.' );

		iterator_to_array( $this->source( $registry )->documents( $this->record( $path, dirname( $path ) ) ), false );
	}

	/**
	 * Create the file source under test.
	 *
	 * @param DocumentExtractorRegistry|null $registry Optional extractor registry.
	 */
	private function source( ?DocumentExtractorRegistry $registry = null ): FileDocumentSource {
		$detector = new class() implements MimeTypeDetector {
			/**
			 * Detect the fixture MIME type.
			 *
			 * @param string $path Local file path.
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
					 * Extract deterministic fixture content.
					 *
					 * @param ValidatedFile $file Validated file.
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
	 * @param string   $path File path.
	 * @param string   $allowed_root Allowed root.
	 * @param int|null $id Persisted source ID.
	 * @param string   $type Source type.
	 */
	private function record( string $path, string $allowed_root, ?int $id = 42, string $type = 'file' ): KnowledgeSourceRecord {
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
				'path'         => $path,
				'allowed_root' => $allowed_root,
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

	/**
	 * Create an isolated temporary file.
	 *
	 * @param string $basename File basename.
	 * @param string $contents File contents.
	 */
	private function createFile( string $basename, string $contents ): string {
		$directory = sys_get_temp_dir() . '/wp-rag-m05-' . bin2hex( random_bytes( 8 ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Unit fixture setup needs an isolated local directory.
		self::assertTrue( mkdir( $directory, 0700 ) );
		$this->temporary_paths[] = $directory;
		$path                    = $directory . '/' . $basename;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Unit fixture setup needs exact local bytes.
		self::assertSame( strlen( $contents ), file_put_contents( $path, $contents ) );
		$this->temporary_paths[] = $path;

		return $path;
	}

	/**
	 * Keep the RED phase behavioral instead of producing autoload fatals.
	 */
	private function requireTask5Contract(): void {
		self::assertTrue( class_exists( FileDocumentSource::class ), 'M05 FileDocumentSource must exist.' );
	}
}
