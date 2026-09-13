<?php
/**
 * Administrator display-rules REST resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use InvalidArgumentException;
use RuntimeException;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Frontend\BotDisplayRulesRepository;
use WpRagAiChatbot\Frontend\DisplayRulesConfig;

/** Repository-backed administrator display-rules boundary. */
final class DisplayRulesRestResource {
	/**
	 * Create the display-rules resource.
	 *
	 * @param BotDisplayRulesRepository $repository Bot-scoped display-rules persistence.
	 */
	public function __construct( private readonly BotDisplayRulesRepository $repository ) {
	}

	/**
	 * Read one normalized bot display-rules configuration.
	 *
	 * @param string $id Bot identifier.
	 * @return array{display_rules:array<string,mixed>}|array{error:array{code:string,message:string}}
	 */
	public function read( string $id ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}

		try {
			$display_rules = $this->repository->find( $bot_id );
		} catch ( RuntimeException ) {
			return self::error( 'display_rules_read_failed', 'Bot display rules could not be read.' );
		}

		return array( 'display_rules' => $display_rules->to_array() );
	}

	/**
	 * Normalize and persist one bot display-rules configuration.
	 *
	 * @param string              $id Bot identifier.
	 * @param array<string,mixed> $payload Display-rules payload.
	 * @return array{display_rules:array<string,mixed>}|array{error:array{code:string,message:string}}
	 */
	public function write( string $id, array $payload ): array {
		$bot_id = self::parse_id( $id );
		if ( null === $bot_id ) {
			return self::error( 'invalid_bot_id', 'Bot identifier is invalid.' );
		}

		try {
			$display_rules = DisplayRulesConfig::from_array( $payload );
		} catch ( InvalidArgumentException ) {
			return self::error( 'invalid_display_rules', 'Display-rule settings are invalid.' );
		}

		try {
			$this->repository->save( $bot_id, $display_rules );
		} catch ( RuntimeException ) {
			return self::error( 'display_rules_save_failed', 'Bot display rules could not be saved.' );
		}

		return array( 'display_rules' => $display_rules->to_array() );
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
