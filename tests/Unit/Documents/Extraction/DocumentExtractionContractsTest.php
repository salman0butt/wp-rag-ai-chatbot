<?php
/**
 * Document extraction contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Documents\Extraction;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\Extraction\DocumentExtractor;
use WpRagAiChatbot\Documents\Extraction\DocumentExtractorRegistry;
use WpRagAiChatbot\Documents\Extraction\ExtractedDocument;
use WpRagAiChatbot\Documents\Extraction\ExtractionException;
use WpRagAiChatbot\Documents\Extraction\ValidatedFile;

/**
 * Defines the first M05 extraction boundary.
 */
final class DocumentExtractionContractsTest extends TestCase {
	/**
	 * Validated files retain canonical trusted metadata only.
	 */
	public function test_validated_file_exposes_validated_metadata(): void {
		$this->requireM05Contracts();

		$file = new ValidatedFile(
			'/tmp/knowledge.txt',
			'knowledge.txt',
			'txt',
			'text/plain',
			128,
			str_repeat( 'a', 64 )
		);

		self::assertSame( '/tmp/knowledge.txt', $file->path );
		self::assertSame( 'knowledge.txt', $file->basename );
		self::assertSame( 'txt', $file->extension );
		self::assertSame( 'text/plain', $file->mimeType );
		self::assertSame( 128, $file->size );
		self::assertSame( str_repeat( 'a', 64 ), $file->sha256 );
	}

	/**
	 * Validated files reject invalid trusted state.
	 */
	public function test_validated_file_rejects_invalid_state(): void {
		$this->requireM05Contracts();
		$this->expectException( InvalidArgumentException::class );

		new ValidatedFile( '/tmp/a.txt', 'a.txt', 'txt', 'text/plain', 0, str_repeat( 'a', 64 ) );
	}

	/**
	 * Extracted documents require non-blank normalized text.
	 */
	public function test_extracted_document_rejects_blank_text(): void {
		$this->requireM05Contracts();
		$this->expectException( InvalidArgumentException::class );

		new ExtractedDocument( '   ', array() );
	}

	/**
	 * Registry resolves exact MIME ownership and rejects duplicate MIME types.
	 */
	public function test_registry_resolves_exact_mime_and_rejects_duplicates(): void {
		$this->requireM05Contracts();

		$first = new class implements DocumentExtractor {
			public function supportedMimeTypes(): array {
				return array( 'text/plain' );
			}

			public function extract( ValidatedFile $file ): ExtractedDocument {
				return new ExtractedDocument( 'text', array( 'name' => $file->basename ) );
			}
		};
		$duplicate = new class implements DocumentExtractor {
			public function supportedMimeTypes(): array {
				return array( 'text/plain' );
			}

			public function extract( ValidatedFile $file ): ExtractedDocument {
				return new ExtractedDocument( 'duplicate', array() );
			}
		};

		$registry = new DocumentExtractorRegistry();
		$registry->register( $first );
		self::assertSame( $first, $registry->get( 'text/plain' ) );

		$this->expectException( InvalidArgumentException::class );
		$registry->register( $duplicate );
	}

	/**
	 * Unsupported MIME resolution fails explicitly.
	 */
	public function test_registry_rejects_unsupported_mime(): void {
		$this->requireM05Contracts();
		$this->expectException( ExtractionException::class );

		( new DocumentExtractorRegistry() )->get( 'application/pdf' );
	}

	/**
	 * Keep the RED phase behavioral instead of producing autoload fatals.
	 */
	private function requireM05Contracts(): void {
		self::assertTrue(
			class_exists( ValidatedFile::class )
			&& class_exists( ExtractedDocument::class )
			&& interface_exists( DocumentExtractor::class )
			&& class_exists( DocumentExtractorRegistry::class )
			&& class_exists( ExtractionException::class ),
			'M05 extraction contracts must exist.'
		);
	}
}
