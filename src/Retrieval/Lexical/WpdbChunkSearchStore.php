<?php
/**
 * WordPress database chunk-search projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

use InvalidArgumentException;
use JsonException;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\TableNames;

/**
 * Stores bounded lexical rows and applies trusted filters in SQL.
 */
final class WpdbChunkSearchStore implements ChunkSearchStore {
	/**
	 * Create the WordPress database projection adapter.
	 *
	 * @param Connection $connection Database connection.
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly TableNames $tables
	) {
	}

	/**
	 * Replace one document projection idempotently.
	 *
	 * @param string            $collection_id Collection scope.
	 * @param string            $document_key Owning document key.
	 * @param ChunkSearchRecord ...$chunks Replacement chunks.
	 * @throws InvalidArgumentException When a chunk belongs to another document.
	 * @throws DatabaseException When persistence fails.
	 */
	public function replace_document_chunks( string $collection_id, string $document_key, ChunkSearchRecord ...$chunks ): void {
		new LexicalFilter( $collection_id, $document_key );
		foreach ( $chunks as $chunk ) {
			if ( $chunk->document_key !== $document_key ) {
				throw new InvalidArgumentException( 'Projected chunk belongs to another document.' );
			}
		}

		$this->delete_document( $collection_id, $document_key );
		foreach ( $chunks as $chunk ) {
			$metadata_json = wp_json_encode( $chunk->metadata );
			if ( false === $metadata_json ) {
				throw new DatabaseException( 'Could not encode chunk-search metadata.' );
			}

			$inserted = $this->connection->insert(
				$this->tables->chunk_search(),
				array(
					'collection_id' => $collection_id,
					'chunk_key'     => $chunk->chunk_key,
					'document_key'  => $chunk->document_key,
					'source_id'     => $chunk->source_id,
					'document_type' => $chunk->document_type,
					'title'         => $chunk->title,
					'canonical_url' => $chunk->canonical_url,
					'content'       => $chunk->content,
					'content_hash'  => $chunk->content_hash,
					'language'      => $chunk->language,
					'visibility'    => $chunk->visibility,
					'sequence'      => $chunk->sequence,
					'metadata_json' => $metadata_json,
					'updated_at'    => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			if ( false === $inserted ) {
				throw new DatabaseException( 'Could not persist chunk-search projection.' );
			}
		}
	}

	/**
	 * Delete only the requested collection/document projection.
	 *
	 * @param string $collection_id Collection scope.
	 * @param string $document_key Owning document key.
	 * @throws DatabaseException When persistence fails.
	 */
	public function delete_document( string $collection_id, string $document_key ): void {
		$filter  = new LexicalFilter( $collection_id, $document_key );
		$deleted = $this->connection->delete(
			$this->tables->chunk_search(),
			array(
				'collection_id' => $filter->collection_id,
				'document_key'  => $filter->document_key,
			)
		);
		if ( false === $deleted ) {
			throw new DatabaseException( 'Could not delete chunk-search projection.' );
		}
	}

	/**
	 * Return a deterministic bounded SQL candidate set.
	 *
	 * @param LexicalSearchRequest $request Trusted bounded search request.
	 * @return LexicalSearchMatch[]
	 * @throws DatabaseException When stored projection data is invalid.
	 */
	public function search( LexicalSearchRequest $request ): array {
		$clauses = array( 'collection_id = %s' );
		$args    = array( $this->tables->chunk_search(), $request->filter->collection_id );

		if ( null !== $request->filter->document_key ) {
			$clauses[] = 'document_key = %s';
			$args[]    = $request->filter->document_key;
		}
		if ( null !== $request->filter->source_id ) {
			$clauses[] = 'source_id = %d';
			$args[]    = $request->filter->source_id;
		}
		if ( null !== $request->filter->language ) {
			$clauses[] = 'language = %s';
			$args[]    = $request->filter->language;
		}
		if ( null !== $request->filter->visibility ) {
			$clauses[] = 'visibility = %s';
			$args[]    = $request->filter->visibility;
		}

		$term_clauses = array();
		foreach ( $request->terms as $term ) {
			$term_clauses[] = '(LOCATE(%s, LOWER(content)) > 0 OR LOCATE(%s, LOWER(title)) > 0)';
			$normalized     = strtolower( $term );
			$args[]         = $normalized;
			$args[]         = $normalized;
		}

		$sql    = 'SELECT chunk_key, document_key, source_id, document_type, title, canonical_url, content, content_hash, language, visibility, sequence, metadata_json FROM %i WHERE '
			. implode( ' AND ', $clauses )
			. ' AND (' . implode( ' OR ', $term_clauses ) . ') ORDER BY chunk_key ASC LIMIT %d';
		$args[] = $request->limit;

		$prepared = $this->connection->prepare( $sql, ...$args );
		$matches  = array();
		foreach ( $this->connection->get_results( $prepared ) as $row ) {
			$matches[] = new LexicalSearchMatch( $this->record_from_row( $row ) );
		}

		return $matches;
	}

	/**
	 * Rehydrate one stored projection row through the same trust-boundary validation.
	 *
	 * @param array<string, mixed> $row Stored projection row.
	 * @throws DatabaseException When stored projection data is invalid.
	 */
	private function record_from_row( array $row ): ChunkSearchRecord {
		try {
			$metadata = json_decode( (string) ( $row['metadata_json'] ?? '{}' ), true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new DatabaseException( 'Stored chunk-search metadata is invalid.' );
		}
		if ( ! is_array( $metadata ) ) {
			throw new DatabaseException( 'Stored chunk-search metadata is invalid.' );
		}

		return new ChunkSearchRecord(
			(string) ( $row['chunk_key'] ?? '' ),
			(string) ( $row['document_key'] ?? '' ),
			(int) ( $row['source_id'] ?? 0 ),
			(string) ( $row['document_type'] ?? '' ),
			(string) ( $row['title'] ?? '' ),
			isset( $row['canonical_url'] ) ? (string) $row['canonical_url'] : null,
			(string) ( $row['content'] ?? '' ),
			(string) ( $row['content_hash'] ?? '' ),
			isset( $row['language'] ) ? (string) $row['language'] : null,
			(string) ( $row['visibility'] ?? '' ),
			(int) ( $row['sequence'] ?? -1 ),
			$metadata
		);
	}
}
