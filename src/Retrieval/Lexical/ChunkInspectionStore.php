<?php
/**
 * Bounded persisted chunk inspection contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

use WpRagAiChatbot\Core\PagedResult;

/**
 * Reads deterministic bounded chunk pages without invoking retrieval ranking.
 */
interface ChunkInspectionStore {
	/**
	 * Return a bounded deterministic page for one persisted document.
	 *
	 * @param string $document_key Stable owning document key.
	 * @param int    $page One-based page.
	 * @param int    $per_page Requested page size.
	 */
	public function paginateByDocument( string $document_key, int $page = 1, int $per_page = 20 ): PagedResult;
}
