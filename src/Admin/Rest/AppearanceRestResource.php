<?php
/**
 * Administrator appearance REST resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use InvalidArgumentException;
use RuntimeException;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Frontend\AppearanceConfig;
use WpRagAiChatbot\Frontend\BotAppearanceRepository;

/** Repository-backed administrator appearance boundary. */
final class AppearanceRestResource {
	/**
	 * Create the appearance resource.
	 *
	 * @param BotAppearanceRepository $repository Bot-scoped appearance persistence.
	 */
	public function __construct( private readonly BotAppearanceRepository $repository ) {
	}

	/**
	 * Read one normalized bot appearance.
	 *
	 * @param string $id Bot identifier.
	 * @return array{appearance:array<string,mixed>}|array{error:array{code:string,message:string}}
	 */
	public function read( string $id ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}

		try {
			$appearance = $this->repository->find( $bot_id );
		} catch ( RuntimeException ) {
			return self::error( 'appearance_read_failed', 'Bot appearance could not be read.' );
		}

		return array( 'appearance' => $appearance->to_array() );
	}

	/**
	 * Normalize and persist one bot appearance.
	 *
	 * @param string              $id Bot identifier.
	 * @param array<string,mixed> $payload Appearance payload.
	 * @return array{appearance:array<string,mixed>}|array{error:array{code:string,message:string}}
	 */
	public function write( string $id, array $payload ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}

		try {
			$appearance = AppearanceConfig::from_array( $payload );
		} catch ( InvalidArgumentException ) {
			return self::error( 'invalid_appearance', 'Appearance settings are invalid.' );
		}

		try {
			$this->repository->save( $bot_id, $appearance );
		} catch ( RuntimeException ) {
			return self::error( 'appearance_save_failed', 'Bot appearance could not be saved.' );
		}

		return array( 'appearance' => $appearance->to_array() );
	}

	/**
	 * Parse one bot identifier without leaking validation details.
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
	 * Build one stable non-sensitive error payload.
	 *
	 * @param string $code Stable machine-readable code.
	 * @param string $message Stable human-readable message.
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
