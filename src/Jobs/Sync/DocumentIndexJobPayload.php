<?php
/**
 * Immutable identifier-only payload for queued document indexing.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use WpRagAiChatbot\Jobs\JobQueueException;

/**
 * Carries only stable identifiers needed to reconstruct document indexing server-side.
 */
final readonly class DocumentIndexJobPayload {
	/** Stable identifier grammar shared by the queued synchronization contract. */
	private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/';

	/**
	 * Create one safe document-index payload.
	 *
	 * @param string $document_key Stable document identity.
	 * @param int    $source_id Owning source identity.
	 * @param string $collection_id Stable collection identity.
	 * @param string $configuration_id Stable server-side indexing configuration identity.
	 * @param string $generation Stable synchronization generation/version.
	 * @throws JobQueueException When an identifier is outside the approved contract.
	 */
	public function __construct(
		public string $document_key,
		public int $source_id,
		public string $collection_id,
		public string $configuration_id,
		public string $generation
	) {
		if ( $source_id < 1 ) {
			throw new JobQueueException( 'Document index source identity must be positive.' );
		}

		foreach ( array( $document_key, $collection_id, $configuration_id, $generation ) as $identifier ) {
			if ( 1 !== preg_match( self::IDENTIFIER_PATTERN, $identifier ) ) {
				throw new JobQueueException( 'Document index payload identifiers must use the stable queue grammar.' );
			}
		}
	}

	/**
	 * Hydrate a strict persisted payload.
	 *
	 * @param array<string, mixed> $payload Decoded queue payload.
	 * @throws JobQueueException When the persisted shape is not exact and safe.
	 */
	public static function from_array( array $payload ): self {
		$expected = array( 'document_key', 'source_id', 'collection_id', 'configuration_id', 'generation' );
		$actual   = array_keys( $payload );
		sort( $expected );
		sort( $actual );

		if ( $expected !== $actual
			|| ! is_string( $payload['document_key'] )
			|| ! is_int( $payload['source_id'] )
			|| ! is_string( $payload['collection_id'] )
			|| ! is_string( $payload['configuration_id'] )
			|| ! is_string( $payload['generation'] ) ) {
			throw new JobQueueException( 'Document index job payload has an invalid persisted shape.' );
		}

		return new self(
			$payload['document_key'],
			$payload['source_id'],
			$payload['collection_id'],
			$payload['configuration_id'],
			$payload['generation']
		);
	}

	/**
	 * Return the exact identifier-only persisted shape.
	 *
	 * @return array{document_key:string,source_id:int,collection_id:string,configuration_id:string,generation:string}
	 */
	public function to_array(): array {
		return array(
			'document_key'     => $this->document_key,
			'source_id'        => $this->source_id,
			'collection_id'    => $this->collection_id,
			'configuration_id' => $this->configuration_id,
			'generation'       => $this->generation,
		);
	}
}
