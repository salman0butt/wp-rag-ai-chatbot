<?php
/**
 * Administrator bot retrieval binding REST resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use InvalidArgumentException;
use RuntimeException;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\BotRetrievalBindingRepository;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

// phpcs:disable WordPress.NamingConventions -- DTO keys and repository APIs follow the approved domain contract.
/**
 * Validates and projects the server-owned bot retrieval binding.
 */
final class BotRetrievalResource {
	/**
	 * Create the resource.
	 *
	 * @param BotRetrievalBindingRepository $bindings Bot retrieval persistence.
	 * @param KnowledgeSourceRepository     $sources Knowledge source persistence.
	 * @param Connection                    $connection Local vector persistence connection.
	 */
	public function __construct(
		private readonly BotRetrievalBindingRepository $bindings,
		private readonly KnowledgeSourceRepository $sources,
		private readonly Connection $connection
	) {
	}

	/**
	 * Read one bot retrieval binding.
	 *
	 * @param string $id Bot identifier.
	 * @return array<string,mixed>
	 */
	public function read( string $id ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}

		try {
			$binding = $this->bindings->find( $bot_id );
		} catch ( RuntimeException ) {
			return self::error( 'retrieval_read_failed', 'Bot knowledge binding could not be read.' );
		}

		if ( null === $binding ) {
			return array( 'retrieval' => self::empty_projection() );
		}

		try {
			$source = $this->sources->findById( $binding->source_id );
		} catch ( RuntimeException ) {
			return self::error( 'retrieval_read_failed', 'Bot knowledge binding could not be read.' );
		}
		if ( null === $source ) {
			return self::error( 'source_not_found', 'The selected knowledge source was not found.' );
		}

		$collection_id = GuidedRetrievalReadiness::source_collection_id( $source );
		if ( null === $collection_id ) {
			return self::error( 'source_configuration_invalid', 'The selected knowledge source is not ready for retrieval.' );
		}
		if ( $binding->collection_id !== $collection_id ) {
			return self::error( 'stale_binding', 'The bot knowledge binding is stale.' );
		}

		try {
			$ready = GuidedRetrievalReadiness::collection_ready( $this->connection );
		} catch ( RuntimeException ) {
			return self::error( 'retrieval_read_failed', 'Bot knowledge binding could not be read.' );
		}

		return array(
			'retrieval' => array(
				'configured'       => true,
				'source_id'        => $source->id,
				'source_title'     => $source->title,
				'collection_id'    => $collection_id,
				'collection_ready' => $ready,
			),
		);
	}

	/**
	 * Persist one bot retrieval binding from the source ID only.
	 *
	 * @param string $id Bot identifier.
	 * @param mixed  $payload Request payload.
	 * @return array<string,mixed>
	 */
	public function write( string $id, mixed $payload ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}
		if ( ! is_array( $payload ) ) {
			return self::error( 'invalid_request', 'Request parameters are invalid.' );
		}
		if ( array( 'source_id' ) !== array_keys( $payload ) || ! is_int( $payload['source_id'] ?? null ) || $payload['source_id'] < 1 ) {
			return self::error( 'invalid_request', 'Request parameters are invalid.' );
		}

		$source_id = $payload['source_id'];
		try {
			$source = $this->sources->findById( $source_id );
		} catch ( RuntimeException ) {
			return self::error( 'retrieval_save_failed', 'Bot knowledge binding could not be saved.' );
		}
		if ( null === $source ) {
			return self::error( 'source_not_found', 'The selected knowledge source was not found.' );
		}

		$collection_id = GuidedRetrievalReadiness::source_collection_id( $source );
		if ( null === $collection_id ) {
			return self::error( 'source_configuration_invalid', 'The selected knowledge source is not ready for retrieval.' );
		}

		try {
			if ( ! GuidedRetrievalReadiness::collection_ready( $this->connection ) ) {
				return self::error( 'collection_not_ready', 'The selected knowledge source is not indexed yet.' );
			}
			$this->bindings->save( $bot_id, new BotRetrievalBinding( $source_id, $collection_id ) );
		} catch ( RuntimeException | InvalidArgumentException ) {
			return self::error( 'retrieval_save_failed', 'Bot knowledge binding could not be saved.' );
		}

		return array(
			'retrieval' => array(
				'configured'       => true,
				'source_id'        => $source->id,
				'source_title'     => $source->title,
				'collection_id'    => $collection_id,
				'collection_ready' => true,
			),
		);
	}

	/**
	 * Clear one bot retrieval binding without cascading into knowledge data.
	 *
	 * @param string $id Bot identifier.
	 * @return array<string,mixed>
	 */
	public function delete( string $id ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}

		try {
			$this->bindings->clear( $bot_id );
		} catch ( RuntimeException ) {
			return self::error( 'retrieval_delete_failed', 'Bot knowledge binding could not be cleared.' );
		}

		return array( 'deleted' => true );
	}

	/**
	 * Parse one bot identifier without leaking aggregate validation details.
	 *
	 * @param string $id Bot identifier.
	 */
	private static function parse_id( string $id ): ?BotId {
		try {
			return new BotId( $id );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Return the exact safe empty projection.
	 *
	 * @return array{configured:bool,source_id:null,source_title:null,collection_id:null,collection_ready:bool}
	 */
	private static function empty_projection(): array {
		return array(
			'configured'       => false,
			'source_id'        => null,
			'source_title'     => null,
			'collection_id'    => null,
			'collection_ready' => false,
		);
	}

	/**
	 * Build a stable safe admin error payload.
	 *
	 * @param string $code Stable machine-readable code.
	 * @param string $message Stable user-facing message.
	 * @return array{error:array{code:string,message:string}}
	 */
	private static function error( string $code, string $message ): array {
		return array(
			'error' => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
// phpcs:enable WordPress.NamingConventions
