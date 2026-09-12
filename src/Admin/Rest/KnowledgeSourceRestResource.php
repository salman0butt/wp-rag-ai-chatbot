<?php
/**
 * Administrator knowledge source REST projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

// phpcs:disable WordPress.NamingConventions -- DTO keys and repository API follow the approved domain contract.
/**
 * Projects persisted knowledge sources into a bounded non-secret admin DTO.
 */
final class KnowledgeSourceRestResource {
	/**
	 * Create the resource.
	 *
	 * @param KnowledgeSourceRepository $repository Knowledge source repository.
	 */
	public function __construct( private readonly KnowledgeSourceRepository $repository ) {
	}

	/**
	 * Return one bounded page of safe source fields.
	 *
	 * @param int $page One-based page.
	 * @param int $perPage Requested page size.
	 * @return array<string,mixed>
	 */
	public function list( int $page, int $perPage ): array {
		if ( $page < 1 || $perPage < 1 || $perPage > 100 ) {
			return self::invalid_request();
		}

		$result = $this->repository->paginate( $page, $perPage );

		return array(
			'items'    => array_map( self::project( ... ), $result->items ),
			'total'    => $result->total,
			'page'     => $result->page,
			'per_page' => $result->perPage,
		);
	}

	/**
	 * Project exactly the allow-listed source inventory fields.
	 *
	 * @param KnowledgeSourceRecord $record Persisted source.
	 * @return array{id:int|null,source_key:string,source_type:string,external_id:string|null,title:string,canonical_url:string|null,status:string,last_synced_at:string|null,updated_at:string}
	 */
	private static function project( KnowledgeSourceRecord $record ): array {
		return array(
			'id'             => $record->id,
			'source_key'     => $record->sourceKey,
			'source_type'    => $record->sourceType,
			'external_id'    => $record->externalId,
			'title'          => $record->title,
			'canonical_url'  => $record->canonicalUrl,
			'status'         => $record->status,
			'last_synced_at' => $record->lastSyncedAt?->format( DATE_ATOM ),
			'updated_at'     => $record->updatedAt->format( DATE_ATOM ),
		);
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
}
// phpcs:enable WordPress.NamingConventions
