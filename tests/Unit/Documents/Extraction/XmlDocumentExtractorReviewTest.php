<?php
/**
 * XML extractor review regression tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Documents\Extraction;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\Extraction\ValidatedFile;
use WpRagAiChatbot\Documents\Extraction\XmlDocumentExtractor;

/**
 * Guards visible mixed-content extraction discovered during Task 3 review.
 */
final class XmlDocumentExtractorReviewTest extends TestCase {
	/**
	 * Temporary fixture path.
	 */
	private ?string $temporary_path = null;

	/**
	 * Remove the temporary XML fixture.
	 */
	protected function tearDown(): void {
		if ( null !== $this->temporary_path && is_file( $this->temporary_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit fixture cleanup outside WordPress bootstrap.
			unlink( $this->temporary_path );
		}
		$this->temporary_path = null;
	}

	/**
	 * Mixed XML content must retain parent text around inline child elements.
	 */
	public function test_xml_extractor_preserves_mixed_content_text(): void {
		$contents             = '<?xml version="1.0"?><root><paragraph>Hello <em>world</em>!</paragraph></root>';
		$this->temporary_path = sys_get_temp_dir() . '/wp-rag-m05-xml-review-' . bin2hex( random_bytes( 8 ) ) . '.xml';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Unit fixture setup requires exact local bytes.
		self::assertSame( strlen( $contents ), file_put_contents( $this->temporary_path, $contents ) );

		$file = new ValidatedFile(
			$this->temporary_path,
			'mixed.xml',
			'xml',
			'application/xml',
			strlen( $contents ),
			hash( 'sha256', $contents )
		);

		$extracted = ( new XmlDocumentExtractor() )->extract( $file );

		self::assertSame( 'Hello world!', $extracted->text );
	}
}
