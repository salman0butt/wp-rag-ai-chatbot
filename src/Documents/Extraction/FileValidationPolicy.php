<?php
/**
 * File validation policy.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

/**
 * Establishes trusted local-file metadata before parser dispatch.
 */
final class FileValidationPolicy {
	private const DEFAULT_MAX_FILE_SIZE = 10 * 1024 * 1024;

	/**
	 * Explicit extension-to-MIME allow-list.
	 *
	 * @var array<string,list<string>>
	 */
	private const ALLOWED_MIME_TYPES = array(
		'txt'      => array( 'text/plain' ),
		'md'       => array( 'text/markdown', 'text/plain' ),
		'markdown' => array( 'text/markdown', 'text/plain' ),
		'html'     => array( 'text/html' ),
		'htm'      => array( 'text/html' ),
		'csv'      => array( 'text/csv', 'text/plain' ),
		'json'     => array( 'application/json', 'text/plain' ),
		'xml'      => array( 'application/xml', 'text/xml' ),
		'pdf'      => array( 'application/pdf' ),
		'docx'     => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
	);

	/**
	 * Create the validation policy.
	 *
	 * @param MimeTypeDetector $mime_type_detector Server-side MIME detector.
	 * @param int              $max_file_size Maximum allowed file size in bytes.
	 * @throws ExtractionException When the configured maximum is invalid.
	 */
	public function __construct(
		private readonly MimeTypeDetector $mime_type_detector,
		private readonly int $max_file_size = self::DEFAULT_MAX_FILE_SIZE
	) {
		if ( $this->max_file_size < 1 ) {
			throw new ExtractionException( 'Maximum file size must be positive.' );
		}
	}

	/**
	 * Validate one local file and return immutable trusted metadata.
	 *
	 * @param string      $path Candidate local file path.
	 * @param string|null $allowed_root Optional canonical containment root.
	 * @throws ExtractionException When the candidate fails validation.
	 */
	public function validate( string $path, ?string $allowed_root = null ): ValidatedFile {
		$canonical_path = realpath( $path );
		if ( false === $canonical_path || ! is_file( $canonical_path ) || ! is_readable( $canonical_path ) ) {
			throw new ExtractionException( 'File must be a readable regular local file.' );
		}

		if ( null !== $allowed_root ) {
			$this->assert_within_allowed_root( $canonical_path, $allowed_root );
		}

		$extension = strtolower( pathinfo( $canonical_path, PATHINFO_EXTENSION ) );
		if ( '' === $extension || ! array_key_exists( $extension, self::ALLOWED_MIME_TYPES ) ) {
			throw new ExtractionException( 'File extension is not supported.' );
		}

		$size = filesize( $canonical_path );
		if ( false === $size || $size < 1 ) {
			throw new ExtractionException( 'File must not be empty.' );
		}

		if ( $size > $this->max_file_size ) {
			throw new ExtractionException( 'File exceeds the configured size limit.' );
		}

		$mime_type = strtolower( trim( $this->mime_type_detector->detect( $canonical_path ) ) );
		if ( '' === $mime_type || ! in_array( $mime_type, self::ALLOWED_MIME_TYPES[ $extension ], true ) ) {
			throw new ExtractionException( 'Detected MIME type does not match the file extension.' );
		}

		$sha256 = hash_file( 'sha256', $canonical_path );
		if ( false === $sha256 ) {
			throw new ExtractionException( 'Unable to hash validated file.' );
		}

		return new ValidatedFile(
			$canonical_path,
			basename( $canonical_path ),
			$extension,
			$mime_type,
			$size,
			strtolower( $sha256 )
		);
	}

	/**
	 * Require a canonical file path to remain within an allowed root.
	 *
	 * @param string $canonical_path Canonical candidate path.
	 * @param string $allowed_root Configured allowed root.
	 * @throws ExtractionException When the root is invalid or containment fails.
	 */
	private function assert_within_allowed_root( string $canonical_path, string $allowed_root ): void {
		$canonical_root = realpath( $allowed_root );
		if ( false === $canonical_root || ! is_dir( $canonical_root ) ) {
			throw new ExtractionException( 'Allowed file root is invalid.' );
		}

		$root_prefix = rtrim( $canonical_root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		if ( ! str_starts_with( $canonical_path, $root_prefix ) ) {
			throw new ExtractionException( 'File resolves outside the allowed root.' );
		}
	}
}
