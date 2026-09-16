<?php
/**
 * Shared fixed-profile retrieval readiness checks.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Jobs\Sync\WordPressDocumentIndexDependencies;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

/** Keeps admin and public retrieval readiness on one server-owned profile. */
final class GuidedRetrievalReadiness {
	/** Return the fixed collection ID. */
	public static function collection_id(): string {
		return WordPressDocumentIndexDependencies::COLLECTION_ID;
	}

	/** Return the fixed collection profile fingerprint. */
	public static function profile_fingerprint(): string {
		return WordPressDocumentIndexDependencies::profile()->fingerprint();
	}

	/**
	 * Resolve a source to the fixed collection only when its persisted semantic profile matches exactly.
	 *
	 * @param KnowledgeSourceRecord $source Persisted source.
	 */
	public static function source_collection_id( KnowledgeSourceRecord $source ): ?string {
		$semantic = $source->config['semantic_retrieval'] ?? null;
		$expected = WordPressDocumentIndexDependencies::semantic_configuration();
		if ( ! is_array( $semantic ) || count( $semantic ) !== count( $expected ) ) {
			return null;
		}

		foreach ( $expected as $key => $value ) {
			if ( ! array_key_exists( $key, $semantic ) || $semantic[ $key ] !== $value ) {
				return null;
			}
		}

		return self::collection_id();
	}

	/**
	 * Check the persisted vector collection profile used by the guided flow.
	 *
	 * @param Connection $connection Local index connection.
	 */
	public static function collection_ready( Connection $connection ): bool {
		$tables = new TableNames( $connection->prefix() );
		if ( ! $connection->table_exists( $tables->vector_collections() ) || ! $connection->table_exists( $tables->vectors() ) ) {
			return false;
		}

		$row = $connection->get_row(
			$connection->prepare(
				'SELECT fingerprint, dimensions FROM %i WHERE collection_key = %s LIMIT 1',
				$tables->vector_collections(),
				self::collection_id()
			)
		);
		if ( null === $row || ! is_string( $row['fingerprint'] ?? null ) || ! is_numeric( $row['dimensions'] ?? null ) ) {
			return false;
		}

		return hash_equals( self::profile_fingerprint(), $row['fingerprint'] )
			&& WordPressDocumentIndexDependencies::EMBEDDING_DIMENSIONS === (int) $row['dimensions'];
	}
}
