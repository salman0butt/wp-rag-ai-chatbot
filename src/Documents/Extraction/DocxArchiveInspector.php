<?php
/**
 * DOCX archive resource inspection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use InvalidArgumentException;
use ZipArchive;

/**
 * Enforces bounded ZIP structure before a DOCX parser sees archive contents.
 */
final readonly class DocxArchiveInspector {
	/**
	 * Maximum archive-entry count.
	 *
	 * @var int
	 */
	private int $max_entries;

	/**
	 * Maximum aggregate uncompressed bytes.
	 *
	 * @var int
	 */
	private int $max_uncompressed_bytes;

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Public argument names are fixed by the approved M05 domain contract and PHP named-argument compatibility.
	/**
	 * Create the archive inspector.
	 *
	 * @param int $maxEntries Maximum archive-entry count.
	 * @param int $maxUncompressedBytes Maximum aggregate uncompressed bytes.
	 * @throws InvalidArgumentException When limits are not positive.
	 */
	public function __construct( int $maxEntries = 1000, int $maxUncompressedBytes = 20971520 ) {
		if ( $maxEntries < 1 || $maxUncompressedBytes < 1 ) {
			throw new InvalidArgumentException( 'DOCX archive limits must be positive.' );
		}

		$this->max_entries            = $maxEntries;
		$this->max_uncompressed_bytes = $maxUncompressedBytes;
	}
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

	/**
	 * Verify that a DOCX archive is readable and within resource limits.
	 *
	 * @param string $path Canonical validated DOCX path.
	 * @throws ExtractionException When the archive cannot be safely inspected.
	 */
	public function inspect( string $path ): void {
		$archive = new ZipArchive();
		if ( true !== $archive->open( $path, ZipArchive::RDONLY ) ) {
			throw new ExtractionException( 'DOCX extraction failed.' );
		}

		try {
			$entry_count = count( $archive );
			if ( $entry_count > $this->max_entries ) {
				throw new ExtractionException( 'DOCX extraction failed.' );
			}

			$total_bytes = 0;
			for ( $index = 0; $index < $entry_count; ++$index ) {
				$entry = $archive->statIndex( $index );
				if ( false === $entry || ! isset( $entry['size'] ) || ! is_int( $entry['size'] ) || $entry['size'] < 0 ) {
					throw new ExtractionException( 'DOCX extraction failed.' );
				}

				$total_bytes += $entry['size'];
				if ( $total_bytes > $this->max_uncompressed_bytes ) {
					throw new ExtractionException( 'DOCX extraction failed.' );
				}
			}
		} finally {
			$archive->close();
		}
	}
}
