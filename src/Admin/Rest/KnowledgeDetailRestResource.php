<?php
/**
 * Administrator source/document/chunk detail projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Documents\DocumentRepository;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Retrieval\Lexical\ChunkInspectionStore;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;

// phpcs:disable WordPress.NamingConventions -- DTO keys and repository APIs follow the approved domain contract.
/**
 * Projects persisted knowledge detail into bounded allow-listed admin DTOs.
 */
final class KnowledgeDetailRestResource {
	private const MAX_PAGE_SIZE           = 100;
	private const MAX_CHUNK_CONTENT_BYTES = 2000;

	/**
	 * Create the resource.
	 *
	 * @param KnowledgeSourceRepository $sources Source repository.
	 * @param DocumentRepository        $documents Document repository.
	 * @param ChunkInspectionStore      $chunks Chunk inspection store.
	 */
	public function __construct(
		private readonly KnowledgeSourceRepository $sources,
		private readonly DocumentRepository $documents,
		private readonly ChunkInspectionStore $chunks
	) {
	}

	/**
	 * Return one source detail projection.
	 *
	 * @param int $source_id Persisted source identifier.
	 * @return array<string,mixed>
	 */
	public function source( int $source_id ): array {
		if ( $source_id < 1 ) {
			return self::invalid_request();
		}

		$source = $this->sources->findById( $source_id );
		if ( null === $source ) {
			return self::not_found();
		}

		return self::project_source( $source );
	}

	/**
	 * Return one bounded document page for a source.
	 *
	 * @param int $source_id Persisted source identifier.
	 * @param int $page One-based page.
	 * @param int $per_page Requested page size.
	 * @return array<string,mixed>
	 */
	public function documents( int $source_id, int $page, int $per_page ): array {
		if ( ! self::valid_page( $source_id, $page, $per_page ) ) {
			return self::invalid_request();
		}

		if ( null === $this->sources->findById( $source_id ) ) {
			return self::not_found();
		}

		$result = $this->documents->paginateBySource( $source_id, $page, $per_page );

		return array(
			'items'    => array_map( self::project_document( ... ), $result->items ),
			'total'    => $result->total,
			'page'     => $result->page,
			'per_page' => $result->perPage,
		);
	}

	/**
	 * Return one bounded chunk page for a source document.
	 *
	 * @param int    $source_id Persisted source identifier.
	 * @param string $document_key Stable document key.
	 * @param int    $page One-based page.
	 * @param int    $per_page Requested page size.
	 * @return array<string,mixed>
	 */
	public function chunks( int $source_id, string $document_key, int $page, int $per_page ): array {
		if ( ! self::valid_page( $source_id, $page, $per_page ) || '' === trim( $document_key ) ) {
			return self::invalid_request();
		}

		if ( null === $this->sources->findById( $source_id ) ) {
			return self::not_found();
		}

		$document = $this->documents->findByKey( $document_key );
		if ( null === $document || $document->sourceId !== $source_id ) {
			return self::not_found();
		}

		$result = $this->chunks->paginate_document_chunks( $document_key, $page, $per_page );

		return array(
			'items'    => array_map( self::project_chunk( ... ), $result->items ),
			'total'    => $result->total,
			'page'     => $result->page,
			'per_page' => $result->perPage,
		);
	}

	/**
	 * Project one safe source detail.
	 *
	 * @param KnowledgeSourceRecord $record Persisted source record.
	 * @return array<string,mixed>
	 */
	private static function project_source( KnowledgeSourceRecord $record ): array {
		return array(
			'id'             => $record->id,
			'source_key'     => $record->sourceKey,
			'source_type'    => $record->sourceType,
			'external_id'    => $record->externalId,
			'title'          => $record->title,
			'canonical_url'  => $record->canonicalUrl,
			'status'         => $record->status,
			'last_synced_at' => $record->lastSyncedAt?->format( DATE_ATOM ),
			'created_at'     => $record->createdAt->format( DATE_ATOM ),
			'updated_at'     => $record->updatedAt->format( DATE_ATOM ),
		);
	}

	/**
	 * Project one safe document summary without raw content, metadata or hashes.
	 *
	 * @param DocumentRecord $record Persisted document record.
	 * @return array<string,mixed>
	 */
	private static function project_document( DocumentRecord $record ): array {
		return array(
			'id'             => $record->id,
			'document_key'   => $record->documentKey,
			'source_id'      => $record->sourceId,
			'external_id'    => $record->externalId,
			'document_type'  => $record->documentType,
			'title'          => $record->title,
			'canonical_url'  => $record->canonicalUrl,
			'source_version' => $record->sourceVersion,
			'language'       => $record->language,
			'visibility'     => $record->visibility,
			'created_at'     => $record->createdAt->format( DATE_ATOM ),
			'updated_at'     => $record->updatedAt->format( DATE_ATOM ),
		);
	}

	/**
	 * Project one safe bounded chunk detail.
	 *
	 * @param ChunkSearchRecord $record Persisted chunk-search record.
	 * @return array<string,mixed>
	 */
	private static function project_chunk( ChunkSearchRecord $record ): array {
		$truncated = strlen( $record->content ) > self::MAX_CHUNK_CONTENT_BYTES;

		return array(
			'chunk_key'         => $record->chunk_key,
			'document_key'      => $record->document_key,
			'source_id'         => $record->source_id,
			'document_type'     => $record->document_type,
			'title'             => $record->title,
			'canonical_url'     => $record->canonical_url,
			'content'           => substr( $record->content, 0, self::MAX_CHUNK_CONTENT_BYTES ),
			'content_truncated' => $truncated,
			'language'          => $record->language,
			'visibility'        => $record->visibility,
			'sequence'          => $record->sequence,
		);
	}

	/**
	 * Validate a bounded child-page request.
	 *
	 * @param int $source_id Persisted source identifier.
	 * @param int $page One-based page.
	 * @param int $per_page Requested page size.
	 */
	private static function valid_page( int $source_id, int $page, int $per_page ): bool {
		return $source_id > 0 && $page > 0 && $per_page > 0 && $per_page <= self::MAX_PAGE_SIZE;
	}

	/**
	 * Return the stable malformed-request response.
	 *
	 * @return array{error:array{code:string,message:string}}
	 */
	private static function invalid_request(): array {
		return array(
			'error' => array(
				'code'    => 'invalid_request',
				'message' => 'Request parameters are invalid.',
			),
		);
	}

	/**
	 * Return the stable missing-resource response.
	 *
	 * @return array{error:array{code:string,message:string}}
	 */
	private static function not_found(): array {
		return array(
			'error' => array(
				'code'    => 'not_found',
				'message' => 'Knowledge resource was not found.',
			),
		);
	}
}
// phpcs:enable WordPress.NamingConventions
