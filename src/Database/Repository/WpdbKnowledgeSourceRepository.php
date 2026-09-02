<?php
/**
 * WordPress knowledge source repository.
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
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

// phpcs:disable WordPress.NamingConventions -- Repository API and DTO properties follow the approved domain contract.
/**
 * Persists knowledge sources through the narrow database connection contract.
 */
final class WpdbKnowledgeSourceRepository implements KnowledgeSourceRepository {
	/**
	 * Create the repository.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly TableNames $tables
	) {
	}

	/**
	 * Insert or update a source record.
	 *
	 * @throws DatabaseException When JSON encoding or the database write fails.
	 */
	public function save( KnowledgeSourceRecord $record ): KnowledgeSourceRecord {
		$config_json = wp_json_encode( $record->config );
		if ( false === $config_json ) {
			throw new DatabaseException( 'Could not encode knowledge source configuration.' );
		}

		$data = array(
			'source_key'     => $record->sourceKey,
			'source_type'    => $record->sourceType,
			'external_id'    => $record->externalId,
			'title'          => $record->title,
			'canonical_url'  => $record->canonicalUrl,
			'status'         => $record->status,
			'config_json'    => $config_json,
			'source_hash'    => $record->sourceHash,
			'last_synced_at' => $this->formatNullableDateTime( $record->lastSyncedAt ),
			'created_at'     => $this->formatDateTime( $record->createdAt ),
			'updated_at'     => $this->formatDateTime( $record->updatedAt ),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( null === $record->id ) {
			$result = $this->connection->insert( $this->tables->sources(), $data, $formats );
			if ( false === $result ) {
				throw new DatabaseException( 'Could not insert knowledge source.' );
			}
			$id = $this->connection->insert_id();
			if ( $id < 1 ) {
				throw new DatabaseException( 'Knowledge source insert did not return an identifier.' );
			}
			return $record->withId( $id );
		}

		$result = $this->connection->update(
			$this->tables->sources(),
			$data,
			array( 'id' => $record->id ),
			$formats,
			array( '%d' )
		);
		if ( false === $result ) {
			throw new DatabaseException( 'Could not update knowledge source.' );
		}

		return $record;
	}

	/**
	 * Find a source by persisted identifier.
	 */
	public function findById( int $id ): ?KnowledgeSourceRecord {
		$sql = $this->connection->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', $this->tables->sources(), $id );
		$row = $this->connection->get_row( $sql );
		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Find a source by stable source key.
	 */
	public function findByKey( string $source_key ): ?KnowledgeSourceRecord {
		$sql = $this->connection->prepare( 'SELECT * FROM %i WHERE source_key = %s LIMIT 1', $this->tables->sources(), $source_key );
		$row = $this->connection->get_row( $sql );
		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Return a bounded page of sources.
	 */
	public function paginate( int $page = 1, int $per_page = 20 ): PagedResult {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$count    = (int) $this->connection->get_var(
			$this->connection->prepare( 'SELECT COUNT(*) FROM %i', $this->tables->sources() )
		);
		$sql      = $this->connection->prepare(
			'SELECT * FROM %i ORDER BY id ASC LIMIT %d OFFSET %d',
			$this->tables->sources(),
			$per_page,
			$offset
		);
		$items    = array_map( fn ( array $row ): KnowledgeSourceRecord => $this->hydrate( $row ), $this->connection->get_results( $sql ) );

		return new PagedResult( $items, $count, $page, $per_page );
	}

	/**
	 * Delete a source by identifier.
	 *
	 * @throws DatabaseException When the database delete fails.
	 */
	public function delete( int $id ): void {
		$result = $this->connection->delete( $this->tables->sources(), array( 'id' => $id ), array( '%d' ) );
		if ( false === $result ) {
			throw new DatabaseException( 'Could not delete knowledge source.' );
		}
	}

	/**
	 * Hydrate a source record from a database row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @throws DatabaseException When stored JSON cannot be decoded to an array.
	 */
	private function hydrate( array $row ): KnowledgeSourceRecord {
		return new KnowledgeSourceRecord(
			(int) $row['id'],
			(string) $row['source_key'],
			(string) $row['source_type'],
			$this->nullableString( $row['external_id'] ?? null ),
			(string) $row['title'],
			$this->nullableString( $row['canonical_url'] ?? null ),
			(string) $row['status'],
			$this->decodeArray( $row['config_json'] ?? null ),
			$this->nullableString( $row['source_hash'] ?? null ),
			$this->nullableDateTime( $row['last_synced_at'] ?? null ),
			$this->dateTime( (string) $row['created_at'] ),
			$this->dateTime( (string) $row['updated_at'] )
		);
	}

	/**
	 * Decode a stored JSON object/array.
	 *
	 * @return array<string, mixed>
	 * @throws DatabaseException When stored JSON cannot be decoded to an array.
	 */
	private function decodeArray( mixed $value ): array {
		if ( null === $value || '' === $value ) {
			return array();
		}

		try {
			$decoded = json_decode( (string) $value, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw new DatabaseException( 'Stored knowledge source configuration is invalid JSON.', 0, $exception );
		}

		if ( ! is_array( $decoded ) ) {
			throw new DatabaseException( 'Stored knowledge source configuration must decode to an array.' );
		}

		return $decoded;
	}

	/**
	 * Normalize a nullable database string.
	 */
	private function nullableString( mixed $value ): ?string {
		return null === $value ? null : (string) $value;
	}

	/**
	 * Parse one UTC database datetime.
	 */
	private function dateTime( string $value ): DateTimeImmutable {
		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Parse a nullable UTC database datetime.
	 */
	private function nullableDateTime( mixed $value ): ?DateTimeImmutable {
		return null === $value ? null : $this->dateTime( (string) $value );
	}

	/**
	 * Format one datetime as UTC database time.
	 */
	private function formatDateTime( DateTimeImmutable $value ): string {
		return $value->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Format a nullable datetime as UTC database time.
	 */
	private function formatNullableDateTime( ?DateTimeImmutable $value ): ?string {
		return null === $value ? null : $this->formatDateTime( $value );
	}
}
// phpcs:enable WordPress.NamingConventions
