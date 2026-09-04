<?php
/**
 * Vector delete capability contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

/**
 * Optional collection-scoped vector delete operation.
 */
interface VectorDeleteStore extends VectorStore {
	/**
	 * Delete one stable ID from a collection idempotently.
	 *
	 * @param VectorCollection $collection Collection boundary.
	 * @param string           $id Stable record ID.
	 */
	public function delete( VectorCollection $collection, string $id ): VectorWriteResult;
}
