<?php
/**
 * PDF extractor resource-limit tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Documents\Extraction;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use WpRagAiChatbot\Documents\Extraction\ExtractionException;
use WpRagAiChatbot\Documents\Extraction\PdfDocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\ValidatedFile;

/**
 * Verifies explicit PDF page and extracted-text limits.
 */
final class PdfDocumentExtractorResourceLimitsTest extends TestCase {
	/**
	 * Temporary fixture path.
	 *
	 * @var string|null
	 */
	private ?string $temporary_path = null;

	/** Remove the temporary fixture. */
	protected function tearDown(): void {
		if ( null !== $this->temporary_path && is_file( $this->temporary_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit fixture cleanup outside WordPress bootstrap.
			unlink( $this->temporary_path );
		}
	}

	/** Page count exceeding the configured bound fails closed. */
	public function test_pdf_extractor_rejects_page_count_above_limit(): void {
		$file = $this->validatedPdf( $this->twoPagePdf() );

		$this->expectException( ExtractionException::class );
		$this->extractorWithLimits( 1, 1024 )->extract( $file );
	}

	/** Extracted text exceeding the configured byte bound fails closed. */
	public function test_pdf_extractor_rejects_text_above_limit(): void {
		$file = $this->validatedPdf( $this->onePagePdf( 'Hello PDF' ) );

		$this->expectException( ExtractionException::class );
		$this->extractorWithLimits( 10, 4 )->extract( $file );
	}

	/** Compressed PDF streams are bounded during decode, before text is trusted. */
	public function test_pdf_extractor_rejects_stream_above_decode_memory_limit(): void {
		$file = $this->validatedPdf( $this->compressedPdfWithPadding( 'Visible text' ) );

		$this->expectException( ExtractionException::class );
		$this->extractorWithLimits( 10, 1024, 64 )->extract( $file );
	}

	/** Non-positive resource limits are invalid configuration. */
	public function test_pdf_extractor_rejects_non_positive_limits(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->extractorWithLimits( 0, 1024 );
	}

	/**
	 * Construct the extractor through its public named-argument contract.
	 *
	 * Reflection keeps the intentionally missing constructor a runtime RED rather than a static-analysis failure.
	 *
	 * @param int $max_pages Maximum PDF pages.
	 * @param int $max_text_bytes Maximum extracted-text bytes.
	 * @param int $max_decode_bytes Maximum bytes used while decoding compressed PDF streams.
	 */
	private function extractorWithLimits( int $max_pages, int $max_text_bytes, int $max_decode_bytes = 8388608 ): PdfDocumentExtractor {
		$reflection = new ReflectionClass( PdfDocumentExtractor::class );
		$extractor  = $reflection->newInstanceArgs(
			array(
				'maxPages'       => $max_pages,
				'maxTextBytes'   => $max_text_bytes,
				'maxDecodeBytes' => $max_decode_bytes,
			)
		);

		self::assertInstanceOf( PdfDocumentExtractor::class, $extractor );
		return $extractor;
	}

	/**
	 * Write a trusted temporary PDF fixture.
	 *
	 * @param string $content PDF bytes.
	 * @throws RuntimeException When fixture metadata cannot be created.
	 */
	private function validatedPdf( string $content ): ValidatedFile {
		$path = tempnam( sys_get_temp_dir(), 'wp-rag-pdf-limit-' );
		if ( false === $path ) {
			throw new RuntimeException( 'Unable to create PDF fixture.' );
		}

		$this->temporary_path = $path;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Unit fixture setup outside WordPress bootstrap.
		if ( false === file_put_contents( $path, $content ) ) {
			throw new RuntimeException( 'Unable to write PDF fixture.' );
		}

		$size = filesize( $path );
		$hash = hash_file( 'sha256', $path );
		if ( false === $size || false === $hash ) {
			throw new RuntimeException( 'Unable to read PDF fixture metadata.' );
		}

		return new ValidatedFile(
			path: $path,
			basename: 'limit.pdf',
			extension: 'pdf',
			mimeType: 'application/pdf',
			size: $size,
			sha256: $hash
		);
	}

	/**
	 * Build a valid one-page PDF.
	 *
	 * @param string $text Visible text.
	 */
	private function onePagePdf( string $text ): string {
		return $this->buildPdf( array( $text ) );
	}

	/** Build a valid two-page PDF. */
	private function twoPagePdf(): string {
		return $this->buildPdf( array( 'Page one', 'Page two' ) );
	}

	/**
	 * Build a one-page PDF with a highly compressible content stream whose visible text is small.
	 *
	 * @param string $text Visible text.
	 * @throws RuntimeException When zlib compression fails.
	 */
	private function compressedPdfWithPadding( string $text ): string {
		$decoded    = str_repeat( "% harmless padding to exercise decode limits\n", 32 )
			. 'BT /F1 12 Tf 72 720 Td (' . $text . ') Tj ET';
		$compressed = gzcompress( $decoded );
		if ( false === $compressed ) {
			throw new RuntimeException( 'Unable to compress PDF fixture stream.' );
		}

		$objects = array(
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
			2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
			4 => '<< /Length ' . strlen( $compressed ) . " /Filter /FlateDecode >>\nstream\n" . $compressed . "\nendstream",
			5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
		);

		return $this->assemblePdf( $objects, 5 );
	}

	/**
	 * Build a tiny valid PDF for each supplied page text.
	 *
	 * @param array<string> $pages Visible page strings.
	 */
	private function buildPdf( array $pages ): string {
		$page_ids    = array();
		$content_ids = array();
		$objects     = array();
		$next_id     = 3;

		foreach ( $pages as $text ) {
			$page_ids[]    = $next_id++;
			$content_ids[] = $next_id++;
		}
		$font_id = $next_id;

		$kids       = implode( ' ', array_map( static fn ( int $id ): string => $id . ' 0 R', $page_ids ) );
		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count( $pages ) . ' >>';

		foreach ( $pages as $index => $text ) {
			$stream                            = 'BT /F1 12 Tf 72 720 Td (' . $text . ') Tj ET';
			$objects[ $page_ids[ $index ] ]    = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
				. '/Resources << /Font << /F1 ' . $font_id . ' 0 R >> >> /Contents ' . $content_ids[ $index ] . ' 0 R >>';
			$objects[ $content_ids[ $index ] ] = '<< /Length ' . strlen( $stream ) . ">>\nstream\n" . $stream . "\nendstream";
		}
		$objects[ $font_id ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

		return $this->assemblePdf( $objects, $font_id );
	}

	/**
	 * Assemble numbered PDF objects with a valid xref table.
	 *
	 * @param array<int,string> $objects Numbered PDF object bodies.
	 * @param int               $last_id Highest object id.
	 */
	private function assemblePdf( array $objects, int $last_id ): string {
		ksort( $objects );

		$pdf     = "%PDF-1.4\n";
		$offsets = array( 0 );
		foreach ( $objects as $id => $body ) {
			$offsets[ $id ] = strlen( $pdf );
			$pdf           .= $id . " 0 obj\n" . $body . "\nendobj\n";
		}

		$xref_offset = strlen( $pdf );
		$pdf        .= "xref\n0 " . ( $last_id + 1 ) . "\n0000000000 65535 f \n";
		for ( $id = 1; $id <= $last_id; ++$id ) {
			$pdf .= sprintf( '%010d 00000 n ', $offsets[ $id ] ) . "\n";
		}

		return $pdf . "trailer\n<< /Size " . ( $last_id + 1 ) . " /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF";
	}
}
