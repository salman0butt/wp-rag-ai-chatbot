<?php
/**
 * WordPress document repository.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use WpRagAiChatbot\Core\PagedResult;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Documents\DocumentRepository;

// phpcs:disable WordPress.NamingConventions -- Repository API and DTO properties follow the approved domain contract.
/**
 * Persists normalized documents through the narrow database connection contract.
 */
final class WpdbDocumentRepository implements DocumentRepository {
	/**
	 * Create the repository.
	 *
	 * @param Connection $connection Database connection.
	 * @param TableNames $tables Plugin table names.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly TableNames $tables
	) {
	}

	/**
	 * Insert or update a document record.
	 *
	 * @param DocumentRecord $record Document record.
	 * @throws DatabaseException When JSON encoding or the database write fails.
	 */
	public function save( DocumentRecord $record ): DocumentRecord {
		$metadata_json = wp_json_encode( $record->metadata );
		if ( false === $metadata_json ) {
			throw new DatabaseException( 'Could not encode document metadata.' );
		}

		$data    = array(
			'document_key'   => $record->documentKey,
			'source_id'      => $record->sourceId,
			'external_id'    => $record->externalId,
			'document_type'  => $record->documentType,
			'title'          => $record->title,
			'canonical_url'  => $record->canonicalUrl,
			'content'        => $record->content,
			'metadata_json'  => $metadata_json,
			'source_version' => $record->sourceVersion,
			'content_hash'   => $record->contentHash,
			'language'       => $record->language,
			'visibility'     => $record->visibility,
			'created_at'     => $this->formatDateTime( $record->createdAt ),
			'updated_at'     => $this->formatDateTime( $record->updatedAt ),
		);
		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( null === $record->id ) {
			$result = $this->connection->insert( $this->tables->documents(), $data, $formats );
			if ( false === $result ) {
				throw new DatabaseException( 'Could not insert document.' );
			}
			$id = $this->connection->insert_id();
			if ( $id < 1 ) {
				throw new DatabaseException( 'Document insert did not return an identifier.' );
			}
			return $record->withId( $id );
		}

		$result = $this->connection->update(
			$this->tables->documents(),
			$data,
			array( 'id' => $record->id ),
			$formats,
			array( '%d' )
		);
		if ( false === $result ) {
			throw new DatabaseException( 'Could not update document.' );
		}

		return $record;
	}

	/**
	 * Find a document by stable document key.
	 *
	 * @param string $document_key Stable document key.
	 */
	public function findByKey( string $document_key ): ?DocumentRecord {
		$sql = $this->connection->prepare( 'SELECT * FROM %i WHERE document_key = %s LIMIT 1', $this->tables->documents(), $document_key );
		$row = $this->connection->get_row( $sql );
		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Return a bounded page belonging to one source.
	 *
	 * @param int $source_id Owning source identifier.
	 * @param int $page One-based page.
	 * @param int $per_page Requested page size.
	 */
	public function paginateBySource( int $source_id, int $page = 1, int $per_page = 20 ): PagedResult {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$count    = (int) $this->connection->get_var(
			$this->connection->prepare( 'SELECT COUNT(*) FROM %i WHERE source_id = %d', $this->tables->documents(), $source_id )
		);
		$sql      = $this->connection->prepare(
			'SELECT * FROM %i WHERE source_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
			$this->tables->documents(),
			$source_id,
			$per_page,
			$offset
		);
		$items    = array_map( fn ( array $row ): DocumentRecord => $this->hydrate( $row ), $this->connection->get_results( $sql ) );

		return new PagedResult( $items, $count, $page, $per_page );
	}

	/**
	 * Delete all documents belonging to one source.
	 *
	 * @param int $source_id Owning source identifier.
	 * @throws DatabaseException When the database delete fails.
	 */
	public function deleteBySource( int $source_id ): int {
		$result = $this->connection->delete( $this->tables->documents(), array( 'source_id' => $source_id ), array( '%d' ) );
		if ( false === $result ) {
			throw new DatabaseException( 'Could not delete documents for source.' );
		}
		return (int) $result;
	}

	/**
	 * Hydrate a document record from a database row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @throws DatabaseException When stored metadata cannot be decoded to an array.
	 */
	private function hydrate( array $row ): DocumentRecord {
		return new DocumentRecord(
			(int) $row['id'],
			(string) $row['document_key'],
			(int) $row['source_id'],
			$this->nullableString( $row['external_id'] ?? null ),
			(string) $row['document_type'],
			(string) $row['title'],
			$this->nullableString( $row['canonical_url'] ?? null ),
			(string) $row['content'],
			$this->decodeArray( $row['metadata_json'] ?? null ),
			$this->nullableString( $row['source_version'] ?? null ),
			(string) $row['content_hash'],
			$this->nullableString( $row['language'] ?? null ),
			(string) $row['visibility'],
			$this->dateTime( (string) $row['created_at'] ),
			$this->dateTime( (string) $row['updated_at'] )
		);
	}

	/**
	 * Decode stored document metadata.
	 *
	 * @param mixed $value Stored metadata JSON.
	 * @return array<string, mixed>
	 * @throws DatabaseException When stored metadata cannot be decoded to an array.
	 */
	private function decodeArray( mixed $value ): array {
		if ( null === $value || '' === $value ) {
			return array();
		}

		try {
			$decoded = json_decode( (string) $value, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new DatabaseException( 'Stored document metadata is invalid JSON.' );
		}

		if ( ! is_array( $decoded ) ) {
			throw new DatabaseException( 'Stored document metadata must decode to an array.' );
		}

		return $decoded;
	}

	/**
	 * Normalize a nullable database string.
	 *
	 * @param mixed $value Database value.
	 */
	private function nullableString( mixed $value ): ?string {
		return null === $value ? null : (string) $value;
	}

	/**
	 * Parse one UTC database datetime.
	 *
	 * @param string $value Database datetime value.
	 */
	private function dateTime( string $value ): DateTimeImmutable {
		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Format one datetime as UTC database time.
	 *
	 * @param DateTimeImmutable $value Datetime value.
	 */
	private function formatDateTime( DateTimeImmutable $value ): string {
		return $value->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
// phpcs:enable WordPress.NamingConventions
