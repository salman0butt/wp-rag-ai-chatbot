<?php
/**
 * Immutable identifier-only payload for queued knowledge-source synchronization.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use WpRagAiChatbot\Jobs\JobQueueException;

/** Carries only stable identifiers needed to reconstruct source synchronization server-side. */
final readonly class KnowledgeSourceSyncJobPayload {
	private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/';

	/**
	 * Create one validated source-sync payload.
	 *
	 * @param int    $source_id Persisted source identity.
	 * @param string $collection_id Server-owned collection identity.
	 * @param string $configuration_id Server-owned configuration identity.
	 * @param string $generation Persisted source generation.
	 * @throws JobQueueException When an identifier is outside the queue contract.
	 */
	public function __construct(
		public int $source_id,
		public string $collection_id,
		public string $configuration_id,
		public string $generation
	) {
		if ( $source_id < 1 ) {
			throw new JobQueueException( 'Source synchronization identity must be positive.' );
		}

		foreach ( array( $collection_id, $configuration_id, $generation ) as $identifier ) {
			if ( 1 !== preg_match( self::IDENTIFIER_PATTERN, $identifier ) ) {
				throw new JobQueueException( 'Source synchronization identifiers must use the stable queue grammar.' );
			}
		}
	}

	/**
	 * Hydrate one exact persisted queue payload.
	 *
	 * @param array<string, mixed> $payload Persisted payload.
	 * @throws JobQueueException When the persisted shape is invalid.
	 */
	public static function from_array( array $payload ): self {
		$expected = array( 'source_id', 'collection_id', 'configuration_id', 'generation' );
		$actual   = array_keys( $payload );
		sort( $expected );
		sort( $actual );

		if ( $expected !== $actual
			|| ! is_int( $payload['source_id'] )
			|| ! is_string( $payload['collection_id'] )
			|| ! is_string( $payload['configuration_id'] )
			|| ! is_string( $payload['generation'] ) ) {
			throw new JobQueueException( 'Source synchronization job payload has an invalid persisted shape.' );
		}

		return new self(
			$payload['source_id'],
			$payload['collection_id'],
			$payload['configuration_id'],
			$payload['generation']
		);
	}

	/**
	 * Return the exact identifier-only persisted shape.
	 *
	 * @return array{source_id:int,collection_id:string,configuration_id:string,generation:string}
	 */
	public function to_array(): array {
		return array(
			'source_id'        => $this->source_id,
			'collection_id'    => $this->collection_id,
			'configuration_id' => $this->configuration_id,
			'generation'       => $this->generation,
		);
	}
}
