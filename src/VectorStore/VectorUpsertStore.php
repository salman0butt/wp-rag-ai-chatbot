<?php
/**
 * Vector upsert capability contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

/**
 * Optional raw-vector upsert operation.
 */
interface VectorUpsertStore extends VectorStore {
	/**
	 * Insert or replace one stable vector record.
	 *
	 * @param VectorRecord $record Record to write.
	 */
	public function upsert( VectorRecord $record ): VectorWriteResult;
}
