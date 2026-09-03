<?php
/**
 * PDF and DOCX extractor tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Documents\Extraction;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Documents\Extraction\DocxArchiveInspector;
use WpRagAiChatbot\Documents\Extraction\DocxDocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\DocumentExtractorRegistry;
use WpRagAiChatbot\Documents\Extraction\ExtractionException;
use WpRagAiChatbot\Documents\Extraction\PdfDocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\ValidatedFile;
use ZipArchive;

/**
 * Defines bounded parser-adapter behavior for M05 Task 4.
 */
final class PdfDocxExtractorsTest extends TestCase {
	/**
	 * Temporary fixture paths.
	 *
	 * @var list<string>
	 */
	private array $temporary_paths = array();

	/**
	 * Remove temporary fixtures.
	 */
	protected function tearDown(): void {
		// phpcs:disable WordPress.WP.AlternativeFunctions -- Unit fixtures exercise local parser behavior outside WordPress bootstrap.
		foreach ( array_reverse( $this->temporary_paths ) as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions
		$this->temporary_paths = array();
	}

	/**
	 * PDF and DOCX adapters advertise exact MIME ownership and register normally.
	 */
	public function test_adapters_advertise_and_register_expected_mime_types(): void {
		$this->requireTask4Contracts();

		$pdf  = new PdfDocumentExtractor();
		$docx = new DocxDocumentExtractor( new DocxArchiveInspector() );

		self::assertSame( array( 'application/pdf' ), $pdf->supportedMimeTypes() );
		self::assertSame(
			array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
			$docx->supportedMimeTypes()
		);

		$registry = new DocumentExtractorRegistry();
		$registry->register( $pdf );
		$registry->register( $docx );

		self::assertInstanceOf( PdfDocumentExtractor::class, $registry->get( 'application/pdf' ) );
		self::assertInstanceOf(
			DocxDocumentExtractor::class,
			$registry->get( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' )
		);
	}

	/**
	 * A valid PDF produces deterministic visible text without leaking parser details.
	 */
	public function test_pdf_extractor_extracts_visible_text(): void {
		$this->requireTask4Contracts();
		$path      = $this->writeFixture( 'document.pdf', $this->minimalPdf( 'Hello PDF' ) );
		$file      = $this->validatedFile( $path, 'application/pdf' );
		$extracted = ( new PdfDocumentExtractor() )->extract( $file );

		self::assertStringContainsString( 'Hello PDF', $extracted->text );
		self::assertSame( array( 'format' => 'pdf' ), $extracted->metadata );
	}

	/**
	 * Malformed PDF parser errors are normalized to the extraction boundary.
	 */
	public function test_pdf_extractor_normalizes_malformed_pdf_failure(): void {
		$this->requireTask4Contracts();
		$path = $this->writeFixture( 'broken.pdf', "%PDF-1.4\nthis is not a valid PDF" );
		$file = $this->validatedFile( $path, 'application/pdf' );

		try {
			( new PdfDocumentExtractor() )->extract( $file );
			self::fail( 'Malformed PDF should fail extraction.' );
		} catch ( ExtractionException $exception ) {
			self::assertStringNotContainsString( $path, $exception->getMessage() );
		}
	}

	/**
	 * Encrypted/password-protected PDFs fail closed before parser output is trusted.
	 */
	public function test_pdf_extractor_rejects_encrypted_pdf(): void {
		$this->requireTask4Contracts();
		$content = "%PDF-1.4\n1 0 obj\n<< /Encrypt 2 0 R >>\nendobj\n%%EOF";
		$path    = $this->writeFixture( 'protected.pdf', $content );
		$file    = $this->validatedFile( $path, 'application/pdf' );

		$this->expectException( ExtractionException::class );
		( new PdfDocumentExtractor() )->extract( $file );
	}

	/**
	 * A minimal DOCX produces deterministic visible paragraph text.
	 */
	public function test_docx_extractor_extracts_visible_text(): void {
		$this->requireTask4Contracts();
		$path      = $this->createDocx( 'document.docx', array( 'Hello DOCX', 'Second paragraph' ) );
		$file      = $this->validatedFile( $path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
		$extracted = ( new DocxDocumentExtractor( new DocxArchiveInspector() ) )->extract( $file );

		self::assertSame( "Hello DOCX\nSecond paragraph", $extracted->text );
		self::assertSame( array( 'format' => 'docx' ), $extracted->metadata );
	}

	/**
	 * Malformed DOCX archives fail through the stable extraction exception.
	 */
	public function test_docx_extractor_normalizes_malformed_archive_failure(): void {
		$this->requireTask4Contracts();
		$path = $this->writeFixture( 'broken.docx', 'not-a-zip-archive' );
		$file = $this->validatedFile( $path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' );

		try {
			( new DocxDocumentExtractor( new DocxArchiveInspector() ) )->extract( $file );
			self::fail( 'Malformed DOCX should fail extraction.' );
		} catch ( ExtractionException $exception ) {
			self::assertStringNotContainsString( $path, $exception->getMessage() );
		}
	}

	/**
	 * DOCX archive entry count is bounded before PHPWord receives the archive.
	 */
	public function test_docx_archive_inspector_rejects_excessive_entry_count(): void {
		$this->requireTask4Contracts();
		$path = $this->createZip(
			'entries.docx',
			array(
				'a.txt' => 'a',
				'b.txt' => 'b',
				'c.txt' => 'c',
			)
		);

		$this->expectException( ExtractionException::class );
		( new DocxArchiveInspector( maxEntries: 2, maxUncompressedBytes: 1024 ) )->inspect( $path );
	}

	/**
	 * DOCX total uncompressed archive size is bounded before PHPWord parsing.
	 */
	public function test_docx_archive_inspector_rejects_excessive_uncompressed_size(): void {
		$this->requireTask4Contracts();
		$path = $this->createZip( 'expanded.docx', array( 'large.txt' => str_repeat( 'x', 64 ) ) );

		$this->expectException( ExtractionException::class );
		( new DocxArchiveInspector( maxEntries: 10, maxUncompressedBytes: 32 ) )->inspect( $path );
	}

	/**
	 * Require intentionally absent Task 4 contracts so the first CI run is behavioral RED.
	 */
	private function requireTask4Contracts(): void {
		self::assertTrue( class_exists( PdfDocumentExtractor::class ), 'Task 4 PdfDocumentExtractor contract is missing.' );
		self::assertTrue( class_exists( DocxDocumentExtractor::class ), 'Task 4 DocxDocumentExtractor contract is missing.' );
		self::assertTrue( class_exists( DocxArchiveInspector::class ), 'Task 4 DocxArchiveInspector contract is missing.' );
	}

	/**
	 * Create trusted metadata for a temporary fixture.
	 *
	 * @param string $path Fixture path.
	 * @param string $mime_type Trusted fixture MIME type.
	 * @throws RuntimeException When fixture metadata cannot be read.
	 */
	private function validatedFile( string $path, string $mime_type ): ValidatedFile {
		$size = filesize( $path );
		$hash = hash_file( 'sha256', $path );
		if ( false === $size || false === $hash ) {
			throw new RuntimeException( 'Unable to read temporary fixture metadata.' );
		}

		return new ValidatedFile(
			path: $path,
			basename: basename( $path ),
			extension: strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ),
			mimeType: $mime_type,
			size: $size,
			sha256: $hash
		);
	}

	/**
	 * Write a text fixture to a temporary file.
	 *
	 * @param string $name Fixture basename.
	 * @param string $content Fixture contents.
	 * @throws RuntimeException When fixture creation fails.
	 */
	private function writeFixture( string $name, string $content ): string {
		$directory = sys_get_temp_dir() . '/wp-rag-task4-' . bin2hex( random_bytes( 6 ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Unit fixture setup outside WordPress bootstrap.
		if ( ! mkdir( $directory ) && ! is_dir( $directory ) ) {
			throw new RuntimeException( 'Unable to create temporary fixture directory.' );
		}

		$path = $directory . '/' . $name;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Unit fixture setup outside WordPress bootstrap.
		if ( false === file_put_contents( $path, $content ) ) {
			throw new RuntimeException( 'Unable to write temporary fixture.' );
		}

		$this->temporary_paths[] = $path;
		return $path;
	}

	/**
	 * Create a deterministic ZIP fixture.
	 *
	 * @param string               $name Fixture basename.
	 * @param array<string,string> $entries Archive entries keyed by path.
	 * @throws RuntimeException When ZIP fixture creation fails.
	 */
	private function createZip( string $name, array $entries ): string {
		$path = $this->writeFixture( $name, 'placeholder' );
		$zip  = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( 'Unable to create ZIP fixture.' );
		}

		foreach ( $entries as $entry_name => $contents ) {
			if ( ! $zip->addFromString( $entry_name, $contents ) ) {
				$zip->close();
				throw new RuntimeException( 'Unable to add ZIP fixture entry.' );
			}
		}
		$zip->close();
		return $path;
	}

	/**
	 * Create a minimal DOCX fixture from paragraph strings.
	 *
	 * @param string       $name Fixture basename.
	 * @param list<string> $paragraphs Paragraph text.
	 * @throws RuntimeException When fixture creation fails.
	 */
	private function createDocx( string $name, array $paragraphs ): string {
		$body = '';
		foreach ( $paragraphs as $paragraph ) {
			$body .= '<w:p><w:r><w:t>' . htmlspecialchars( $paragraph, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</w:t></w:r></w:p>';
		}

		$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
			. $body
			. '<w:sectPr/></w:body></w:document>';

		return $this->createZip(
			$name,
			array(
				'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>'
					. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
					. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
					. '<Default Extension="xml" ContentType="application/xml"/>'
					. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
					. '</Types>',
				'_rels/.rels'         => '<?xml version="1.0" encoding="UTF-8"?>'
					. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
					. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
					. '</Relationships>',
				'word/document.xml'   => $document,
			)
		);
	}

	/**
	 * Build a tiny PDF with a single visible text operation and valid xref offsets.
	 *
	 * @param string $text Visible PDF text.
	 */
	private function minimalPdf( string $text ): string {
		$objects = array(
			'1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n',
			'2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n',
			'3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n',
			'4 0 obj\n<< /Length ' . strlen( 'BT /F1 12 Tf 72 720 Td (' . $text . ') Tj ET' ) . ' >>\nstream\nBT /F1 12 Tf 72 720 Td (' . $text . ') Tj ET\nendstream\nendobj\n',
			'5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n',
		);
		$pdf     = "%PDF-1.4\n";
		$offsets = array( 0 );
		foreach ( $objects as $object ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= str_replace( '\\n', "\n", $object );
		}

		$xref_offset = strlen( $pdf );
		$pdf        .= "xref\n0 6\n0000000000 65535 f \n";
		for ( $index = 1; $index <= 5; ++$index ) {
			$pdf .= sprintf( '%010d 00000 n ', $offsets[ $index ] ) . "\n";
		}
		$pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF";
		return $pdf;
	}
}
