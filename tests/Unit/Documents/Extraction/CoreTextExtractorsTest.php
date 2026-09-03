<?php
/**
 * Core text extractor tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Documents\Extraction;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\Extraction\CsvDocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\DocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\DocumentExtractorRegistry;
use WpRagAiChatbot\Documents\Extraction\ExtractionException;
use WpRagAiChatbot\Documents\Extraction\HtmlDocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\JsonDocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\MarkdownDocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\TextDocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\ValidatedFile;
use WpRagAiChatbot\Documents\Extraction\XmlDocumentExtractor;

/**
 * Defines deterministic and bounded behavior for M05 core extractors.
 */
final class CoreTextExtractorsTest extends TestCase {
	/**
	 * Temporary fixture paths.
	 *
	 * @var list<string>
	 */
	private array $temporary_paths = array();

	/**
	 * Remove temporary fixture files.
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
	 * All core extractors advertise deterministic MIME ownership.
	 */
	public function test_core_extractors_advertise_expected_mime_types(): void {
		$this->requireTask3Contracts();

		self::assertSame( array( 'text/plain' ), ( new TextDocumentExtractor() )->supportedMimeTypes() );
		self::assertSame( array( 'text/markdown' ), ( new MarkdownDocumentExtractor() )->supportedMimeTypes() );
		self::assertSame( array( 'text/html' ), ( new HtmlDocumentExtractor() )->supportedMimeTypes() );
		self::assertSame( array( 'text/csv' ), ( new CsvDocumentExtractor() )->supportedMimeTypes() );
		self::assertSame( array( 'application/json' ), ( new JsonDocumentExtractor() )->supportedMimeTypes() );
		self::assertSame( array( 'application/xml', 'text/xml' ), ( new XmlDocumentExtractor() )->supportedMimeTypes() );
	}

	/**
	 * Registry dispatch resolves every core extractor by its owned MIME.
	 */
	public function test_registry_resolves_core_extractor_ownership(): void {
		$this->requireTask3Contracts();
		$registry = new DocumentExtractorRegistry();

		foreach ( $this->coreExtractors() as $extractor ) {
			$registry->register( $extractor );
		}

		self::assertInstanceOf( TextDocumentExtractor::class, $registry->get( 'text/plain' ) );
		self::assertInstanceOf( MarkdownDocumentExtractor::class, $registry->get( 'text/markdown' ) );
		self::assertInstanceOf( HtmlDocumentExtractor::class, $registry->get( 'text/html' ) );
		self::assertInstanceOf( CsvDocumentExtractor::class, $registry->get( 'text/csv' ) );
		self::assertInstanceOf( JsonDocumentExtractor::class, $registry->get( 'application/json' ) );
		self::assertInstanceOf( XmlDocumentExtractor::class, $registry->get( 'application/xml' ) );
		self::assertInstanceOf( XmlDocumentExtractor::class, $registry->get( 'text/xml' ) );
	}

	/**
	 * Plain text extraction normalizes line endings and surrounding whitespace.
	 */
	public function test_text_extractor_normalizes_utf8_text(): void {
		$this->requireTask3Contracts();
		$file      = $this->validatedFile( 'notes.txt', "  Alpha\r\nBeta\r\n  ", 'text/plain' );
		$extracted = ( new TextDocumentExtractor() )->extract( $file );

		self::assertSame( "Alpha\nBeta", $extracted->text );
		self::assertSame( array( 'format' => 'txt' ), $extracted->metadata );
	}

	/**
	 * Null bytes are treated as binary input and fail closed.
	 */
	public function test_text_extractor_rejects_null_byte_content(): void {
		$this->requireTask3Contracts();
		$file = $this->validatedFile( 'binary.txt', "Alpha\0Beta", 'text/plain' );

		$this->expectException( ExtractionException::class );
		( new TextDocumentExtractor() )->extract( $file );
	}

	/**
	 * Markdown keeps readable structural markers while normalizing line endings.
	 */
	public function test_markdown_extractor_preserves_readable_structure(): void {
		$this->requireTask3Contracts();
		$file      = $this->validatedFile( 'guide.md', "# Heading\r\n\r\n- Item\r\n\r\n```php\r\necho 'ok';\r\n```\r\n", 'text/markdown' );
		$extracted = ( new MarkdownDocumentExtractor() )->extract( $file );

		self::assertSame( "# Heading\n\n- Item\n\n```php\necho 'ok';\n```", $extracted->text );
		self::assertSame( array( 'format' => 'markdown' ), $extracted->metadata );
	}

	/**
	 * Markdown binary input fails closed just like plain text.
	 */
	public function test_markdown_extractor_rejects_null_byte_content(): void {
		$this->requireTask3Contracts();
		$file = $this->validatedFile( 'binary.md', "# Alpha\0Beta", 'text/markdown' );

		$this->expectException( ExtractionException::class );
		( new MarkdownDocumentExtractor() )->extract( $file );
	}

	/**
	 * HTML extraction retains visible structure but strips executable/non-visible content.
	 */
	public function test_html_extractor_strips_script_style_and_comments(): void {
		$this->requireTask3Contracts();
		$html      = '<!doctype html><html><head><style>.x{display:none}</style><script>alert(1)</script></head>'
			. '<body><!-- secret --><h1>Title</h1><p>Hello <strong>world</strong>.</p><ul><li>One</li><li>Two</li></ul></body></html>';
		$file      = $this->validatedFile( 'page.html', $html, 'text/html' );
		$extracted = ( new HtmlDocumentExtractor() )->extract( $file );

		self::assertSame( "Title\nHello world.\nOne\nTwo", $extracted->text );
		self::assertSame( 'html', $extracted->metadata['format'] );
		self::assertStringNotContainsString( 'alert', $extracted->text );
		self::assertStringNotContainsString( 'secret', $extracted->text );
	}

	/**
	 * Visible HTML is not lost merely because it is wrapped in generic containers.
	 */
	public function test_html_extractor_keeps_visible_text_from_generic_containers(): void {
		$this->requireTask3Contracts();
		$file      = $this->validatedFile( 'generic.html', '<html><body><div>Standalone <span>visible</span> text</div></body></html>', 'text/html' );
		$extracted = ( new HtmlDocumentExtractor() )->extract( $file );

		self::assertSame( 'Standalone visible text', $extracted->text );
	}

	/**
	 * HTML parsing is bounded by a deterministic DOM node ceiling.
	 */
	public function test_html_extractor_rejects_excessive_node_count(): void {
		$this->requireTask3Contracts();
		$html = '<html><body>' . str_repeat( '<span>x</span>', 5001 ) . '</body></html>';
		$file = $this->validatedFile( 'large.html', $html, 'text/html' );

		$this->expectException( ExtractionException::class );
		( new HtmlDocumentExtractor() )->extract( $file );
	}

	/**
	 * CSV extraction emits deterministic tabular text and shape metadata.
	 */
	public function test_csv_extractor_emits_tabular_text_and_metadata(): void {
		$this->requireTask3Contracts();
		$file      = $this->validatedFile( 'people.csv', "name,city\r\nAda,Oslo\r\nLinus,Helsinki\r\n", 'text/csv' );
		$extracted = ( new CsvDocumentExtractor() )->extract( $file );

		self::assertSame( "name\tcity\nAda\tOslo\nLinus\tHelsinki", $extracted->text );
		self::assertSame(
			array(
				'format'  => 'csv',
				'rows'    => 3,
				'columns' => 2,
			),
			$extracted->metadata
		);
	}

	/**
	 * CSV rows are bounded to avoid unbounded synchronous parsing.
	 */
	public function test_csv_extractor_rejects_excessive_rows(): void {
		$this->requireTask3Contracts();
		$file = $this->validatedFile( 'rows.csv', str_repeat( "a,b\n", 1001 ), 'text/csv' );

		$this->expectException( ExtractionException::class );
		( new CsvDocumentExtractor() )->extract( $file );
	}

	/**
	 * CSV columns are bounded to avoid pathological wide records.
	 */
	public function test_csv_extractor_rejects_excessive_columns(): void {
		$this->requireTask3Contracts();
		$file = $this->validatedFile( 'columns.csv', implode( ',', array_fill( 0, 101, 'x' ) ), 'text/csv' );

		$this->expectException( ExtractionException::class );
		( new CsvDocumentExtractor() )->extract( $file );
	}

	/**
	 * JSON extraction produces stable pretty-printed UTF-8 text.
	 */
	public function test_json_extractor_pretty_prints_valid_json(): void {
		$this->requireTask3Contracts();
		$file      = $this->validatedFile( 'data.json', '{"name":"Ada","active":true,"tags":["math","code"]}', 'application/json' );
		$extracted = ( new JsonDocumentExtractor() )->extract( $file );

		self::assertSame(
			"{\n    \"name\": \"Ada\",\n    \"active\": true,\n    \"tags\": [\n        \"math\",\n        \"code\"\n    ]\n}",
			$extracted->text
		);
		self::assertSame( array( 'format' => 'json' ), $extracted->metadata );
	}

	/**
	 * Malformed JSON normalizes parser errors to the extraction boundary.
	 */
	public function test_json_extractor_rejects_malformed_json(): void {
		$this->requireTask3Contracts();
		$file = $this->validatedFile( 'broken.json', '{"name":', 'application/json' );

		$this->expectException( ExtractionException::class );
		( new JsonDocumentExtractor() )->extract( $file );
	}

	/**
	 * Deep JSON payloads fail at the explicit parser depth limit.
	 */
	public function test_json_extractor_rejects_excessive_depth(): void {
		$this->requireTask3Contracts();
		$file = $this->validatedFile( 'deep.json', str_repeat( '[', 65 ) . '0' . str_repeat( ']', 65 ), 'application/json' );

		$this->expectException( ExtractionException::class );
		( new JsonDocumentExtractor() )->extract( $file );
	}

	/**
	 * XML extraction preserves visible text deterministically.
	 */
	public function test_xml_extractor_extracts_visible_text_and_root_metadata(): void {
		$this->requireTask3Contracts();
		$file      = $this->validatedFile( 'feed.xml', '<?xml version="1.0"?><catalog><title>Hello</title><item>One</item><item>Two</item></catalog>', 'application/xml' );
		$extracted = ( new XmlDocumentExtractor() )->extract( $file );

		self::assertSame( "Hello\nOne\nTwo", $extracted->text );
		self::assertSame(
			array(
				'format' => 'xml',
				'root'   => 'catalog',
			),
			$extracted->metadata
		);
	}

	/**
	 * Malformed XML fails closed with a normalized extraction error.
	 */
	public function test_xml_extractor_rejects_malformed_xml(): void {
		$this->requireTask3Contracts();
		$file = $this->validatedFile( 'broken.xml', '<root><item></root>', 'application/xml' );

		$this->expectException( ExtractionException::class );
		( new XmlDocumentExtractor() )->extract( $file );
	}

	/**
	 * XML document types/entities are rejected before entity expansion or network access.
	 */
	public function test_xml_extractor_rejects_document_type_and_entities(): void {
		$this->requireTask3Contracts();
		$xml  = '<?xml version="1.0"?><!DOCTYPE root [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><root>&xxe;</root>';
		$file = $this->validatedFile( 'hostile.xml', $xml, 'application/xml' );

		$this->expectException( ExtractionException::class );
		( new XmlDocumentExtractor() )->extract( $file );
	}

	/**
	 * XML element depth is bounded for synchronous extraction.
	 */
	public function test_xml_extractor_rejects_excessive_depth(): void {
		$this->requireTask3Contracts();
		$xml  = str_repeat( '<n>', 65 ) . 'value' . str_repeat( '</n>', 65 );
		$file = $this->validatedFile( 'deep.xml', $xml, 'application/xml' );

		$this->expectException( ExtractionException::class );
		( new XmlDocumentExtractor() )->extract( $file );
	}

	/**
	 * Return all Task 3 extractors.
	 *
	 * @return list<DocumentExtractor>
	 */
	private function coreExtractors(): array {
		return array(
			new TextDocumentExtractor(),
			new MarkdownDocumentExtractor(),
			new HtmlDocumentExtractor(),
			new CsvDocumentExtractor(),
			new JsonDocumentExtractor(),
			new XmlDocumentExtractor(),
		);
	}

	/**
	 * Create a validated temporary fixture with exact bytes.
	 *
	 * @param string $basename Basename.
	 * @param string $contents Fixture contents.
	 * @param string $mime_type Trusted MIME type.
	 */
	private function validatedFile( string $basename, string $contents, string $mime_type ): ValidatedFile {
		$path = sys_get_temp_dir() . '/wp-rag-m05-' . bin2hex( random_bytes( 8 ) ) . '-' . $basename;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Unit fixture setup needs exact local bytes.
		self::assertSame( strlen( $contents ), file_put_contents( $path, $contents ) );
		$this->temporary_paths[] = $path;

		$extension = strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) );

		return new ValidatedFile(
			$path,
			$basename,
			$extension,
			$mime_type,
			strlen( $contents ),
			hash( 'sha256', $contents )
		);
	}

	/**
	 * Keep the RED phase behavioral rather than fataling on missing classes.
	 */
	private function requireTask3Contracts(): void {
		self::assertTrue(
			class_exists( TextDocumentExtractor::class )
			&& class_exists( MarkdownDocumentExtractor::class )
			&& class_exists( HtmlDocumentExtractor::class )
			&& class_exists( CsvDocumentExtractor::class )
			&& class_exists( JsonDocumentExtractor::class )
			&& class_exists( XmlDocumentExtractor::class ),
			'M05 core text extractor contracts must exist.'
		);
	}
}
