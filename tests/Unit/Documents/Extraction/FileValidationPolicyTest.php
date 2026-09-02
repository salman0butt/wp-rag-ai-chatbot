<?php
/**
 * File validation policy tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Documents\Extraction;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\Extraction\ExtractionException;
use WpRagAiChatbot\Documents\Extraction\FileValidationPolicy;
use WpRagAiChatbot\Documents\Extraction\MimeTypeDetector;
use WpRagAiChatbot\Documents\Extraction\NativeMimeTypeDetector;

/**
 * Defines the M05 file trust boundary before parser dispatch.
 */
final class FileValidationPolicyTest extends TestCase {
	/**
	 * Temporary paths created by a test.
	 *
	 * @var list<string>
	 */
	private array $temporaryPaths = array();

	/**
	 * Remove temporary files and directories after each test.
	 */
	protected function tearDown(): void {
		foreach ( array_reverse( $this->temporaryPaths ) as $path ) {
			if ( is_link( $path ) || is_file( $path ) ) {
				unlink( $path );
				continue;
			}

			if ( is_dir( $path ) ) {
				rmdir( $path );
			}
		}

		$this->temporaryPaths = array();
	}

	/**
	 * A supported readable regular file becomes trusted canonical metadata.
	 */
	public function test_validate_returns_canonical_metadata_for_supported_regular_file(): void {
		$this->requireTask2Contracts();
		$path = $this->createFile( 'knowledge.txt', "Hello knowledge.\n" );

		$policy = new FileValidationPolicy( $this->detectorReturning( 'text/plain' ) );
		$file   = $policy->validate( $path );

		self::assertSame( realpath( $path ), $file->path );
		self::assertSame( 'knowledge.txt', $file->basename );
		self::assertSame( 'txt', $file->extension );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ValidatedFile public API follows the approved domain contract.
		self::assertSame( 'text/plain', $file->mimeType );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		self::assertSame( filesize( $path ), $file->size );
		self::assertSame( hash_file( 'sha256', $path ), $file->sha256 );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $file->sha256 );
	}

	/**
	 * Empty files fail closed before extraction.
	 */
	public function test_validate_rejects_empty_file(): void {
		$this->requireTask2Contracts();
		$path = $this->createFile( 'empty.txt', '' );

		$this->expectException( ExtractionException::class );
		( new FileValidationPolicy( $this->detectorReturning( 'text/plain' ) ) )->validate( $path );
	}

	/**
	 * The default policy rejects files larger than ten MiB.
	 */
	public function test_validate_rejects_file_over_default_ten_mib_limit(): void {
		$this->requireTask2Contracts();
		$path = $this->createFile( 'large.txt', str_repeat( 'a', ( 10 * 1024 * 1024 ) + 1 ) );

		$this->expectException( ExtractionException::class );
		( new FileValidationPolicy( $this->detectorReturning( 'text/plain' ) ) )->validate( $path );
	}

	/**
	 * Extensions outside the explicit M05 allow-list fail closed.
	 */
	public function test_validate_rejects_unsupported_extension(): void {
		$this->requireTask2Contracts();
		$path = $this->createFile( 'payload.exe', 'not executable bytes' );

		$this->expectException( ExtractionException::class );
		( new FileValidationPolicy( $this->detectorReturning( 'text/plain' ) ) )->validate( $path );
	}

	/**
	 * Server MIME and extension must agree through the explicit allow-list.
	 */
	public function test_validate_rejects_mime_spoof(): void {
		$this->requireTask2Contracts();
		$path = $this->createFile( 'spoof.txt', '%PDF-1.7' );

		$this->expectException( ExtractionException::class );
		( new FileValidationPolicy( $this->detectorReturning( 'application/pdf' ) ) )->validate( $path );
	}

	/**
	 * A candidate outside an allowed root cannot cross the trust boundary.
	 */
	public function test_validate_rejects_allowed_root_escape(): void {
		$this->requireTask2Contracts();
		$root    = $this->createDirectory( 'allowed' );
		$outside = $this->createFile( 'outside.txt', 'outside' );

		$this->expectException( ExtractionException::class );
		( new FileValidationPolicy( $this->detectorReturning( 'text/plain' ) ) )->validate( $outside, $root );
	}

	/**
	 * A symlink inside the allowed root cannot resolve to a file outside it.
	 */
	public function test_validate_rejects_symlink_escape(): void {
		$this->requireTask2Contracts();
		$root    = $this->createDirectory( 'allowed' );
		$outside = $this->createFile( 'outside.txt', 'outside' );
		$link    = $root . '/linked.txt';

		if ( ! function_exists( 'symlink' ) || ! symlink( $outside, $link ) ) {
			self::markTestSkipped( 'Symlink creation is unavailable in this environment.' );
		}
		$this->temporaryPaths[] = $link;

		$this->expectException( ExtractionException::class );
		( new FileValidationPolicy( $this->detectorReturning( 'text/plain' ) ) )->validate( $link, $root );
	}

	/**
	 * Native MIME detection returns a non-empty server-observed type.
	 */
	public function test_native_mime_detector_detects_local_text_file(): void {
		$this->requireTask2Contracts();
		$path = $this->createFile( 'native.txt', "Native detector text.\n" );

		$mime = ( new NativeMimeTypeDetector() )->detect( $path );

		self::assertNotSame( '', trim( $mime ) );
	}

	/**
	 * Build a deterministic MIME detector double.
	 *
	 * @param string $mimeType MIME type to return.
	 */
	private function detectorReturning( string $mimeType ): MimeTypeDetector {
		return new class( $mimeType ) implements MimeTypeDetector {
			/**
			 * Create detector.
			 *
			 * @param string $mimeType MIME type to return.
			 */
			public function __construct( private readonly string $mimeType ) {
			}

			/**
			 * Detect the MIME type.
			 *
			 * @param string $path Local file path.
			 */
			public function detect( string $path ): string {
				unset( $path );
				return $this->mimeType;
			}
		};
	}

	/**
	 * Create an isolated temporary directory.
	 *
	 * @param string $suffix Directory suffix.
	 */
	private function createDirectory( string $suffix ): string {
		$path = sys_get_temp_dir() . '/wp-rag-m05-' . bin2hex( random_bytes( 8 ) ) . '-' . $suffix;
		self::assertTrue( mkdir( $path, 0700 ) );
		$this->temporaryPaths[] = $path;

		return $path;
	}

	/**
	 * Create an isolated temporary file.
	 *
	 * @param string $basename File basename.
	 * @param string $contents File contents.
	 */
	private function createFile( string $basename, string $contents ): string {
		$directory = $this->createDirectory( 'fixture' );
		$path      = $directory . '/' . $basename;
		self::assertSame( strlen( $contents ), file_put_contents( $path, $contents ) );
		$this->temporaryPaths[] = $path;

		return $path;
	}

	/**
	 * Keep the RED phase behavioral instead of producing autoload fatals.
	 */
	private function requireTask2Contracts(): void {
		self::assertTrue(
			class_exists( FileValidationPolicy::class )
			&& interface_exists( MimeTypeDetector::class )
			&& class_exists( NativeMimeTypeDetector::class ),
			'M05 file validation policy contracts must exist.'
		);
	}
}
