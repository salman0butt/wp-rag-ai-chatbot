<?php
/**
 * Persisted Playground retrieval configuration resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use InvalidArgumentException;
use RuntimeException;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

/**
 * Resolves explicit source and collection identifiers against existing persistence without fallbacks.
 */
final class PlaygroundRetrievalConfigurationResolver {
	/**
	 * Create the resolver.
	 *
	 * @param KnowledgeSourceRepository $sources Existing knowledge-source persistence boundary.
	 * @param Connection                $connection Existing database connection boundary.
	 * @param TableNames                $tables Existing per-site table-name resolver.
	 */
	public function __construct(
		private readonly KnowledgeSourceRepository $sources,
		private readonly Connection $connection,
		private readonly TableNames $tables
	) {
	}

	/**
	 * Resolve one explicit persisted retrieval selection.
	 *
	 * @param int    $source_id Persisted knowledge-source identifier.
	 * @param string $collection_id Persisted vector collection key.
	 * @throws InvalidArgumentException When identifiers are malformed.
	 * @throws RuntimeException When either persisted identifier does not exist.
	 */
	public function resolve( int $source_id, string $collection_id ): PlaygroundRetrievalConfiguration {
		if ( $source_id < 1 ) {
			throw new InvalidArgumentException( 'Playground retrieval source ID is invalid.' );
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $collection_id ) ) {
			throw new InvalidArgumentException( 'Playground vector collection ID is invalid.' );
		}

		$source = $this->sources->findById( $source_id );
		if ( null === $source ) {
			throw new RuntimeException( 'Playground retrieval source was not found.' );
		}

		$sql = $this->connection->prepare(
			'SELECT collection_key FROM %i WHERE collection_key = %s LIMIT 1',
			$this->tables->vector_collections(),
			$collection_id
		);
		$persisted_collection = $this->connection->get_var( $sql );
		if ( ! is_string( $persisted_collection ) || $persisted_collection !== $collection_id ) {
			throw new RuntimeException( 'Playground vector collection was not found.' );
		}

		return new PlaygroundRetrievalConfiguration( $source, $persisted_collection );
	}
}
