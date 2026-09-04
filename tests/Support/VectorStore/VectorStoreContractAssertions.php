<?php
/**
 * Reusable vector-store contract assertions.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\VectorStore;

use PHPUnit\Framework\Assert;
use WpRagAiChatbot\VectorStore\VectorDeleteStore;
use WpRagAiChatbot\VectorStore\VectorRecord;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorSearchStore;
use WpRagAiChatbot\VectorStore\VectorUpsertStore;

/**
 * Shared assertions that future adapters can reuse for stable-ID semantics.
 */
final class VectorStoreContractAssertions {
	/**
	 * Assert that upserting the same stable ID replaces the prior value.
	 *
	 * @param VectorUpsertStore $store Store under test.
	 * @param VectorSearchStore $search Search capability.
	 * @param VectorRecord      $first Initial record.
	 * @param VectorRecord      $replacement Replacement record.
	 * @param VectorSearchRequest $request Query returning that record.
	 */
	public static function assert_replacement(
		VectorUpsertStore $store,
		VectorSearchStore $search,
		VectorRecord $first,
		VectorRecord $replacement,
		VectorSearchRequest $request
	): void {
		$store->upsert( $first );
		$store->upsert( $replacement );
		$result = $search->search( $request );

		Assert::assertCount( 1, $result->matches );
		Assert::assertSame( $replacement->id, $result->matches[0]->id );
	}

	/**
	 * Assert that repeated delete remains safe.
	 *
	 * @param VectorDeleteStore $store Store under test.
	 * @param VectorRecord      $record Record to delete.
	 */
	public static function assert_idempotent_delete( VectorDeleteStore $store, VectorRecord $record ): void {
		$first  = $store->delete( $record->collection, $record->id );
		$second = $store->delete( $record->collection, $record->id );

		Assert::assertTrue( $first->changed );
		Assert::assertFalse( $second->changed );
	}
}
