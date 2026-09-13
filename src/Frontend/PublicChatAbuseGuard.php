<?php
/**
 * Public chat abuse-control guard.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Bots\BotId;

/**
 * Applies a bounded per-bot/client request budget before expensive chat work.
 */
final readonly class PublicChatAbuseGuard {
	private const REQUEST_LIMIT  = 20;
	private const WINDOW_SECONDS = 60;
	private const BUCKET_PREFIX  = 'wp_rag_public_chat_';

	/**
	 * Create one abuse-control guard.
	 *
	 * @param PublicChatRateLimitStore $store Rate-limit persistence authority.
	 */
	public function __construct( private PublicChatRateLimitStore $store ) {
	}

	/**
	 * Determine whether one public request may proceed.
	 *
	 * The client scope must already be a server-derived lowercase SHA-256 value;
	 * raw IP addresses, user agents, prompt text, and credentials never enter the store.
	 *
	 * @param string $bot_id Persisted bot identifier.
	 * @param string $client_scope Server-derived opaque client scope.
	 */
	public function allow( string $bot_id, string $client_scope ): bool {
		if ( 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $client_scope ) ) {
			return false;
		}

		$canonical_bot_id = ( new BotId( $bot_id ) )->value;
		$bucket           = self::BUCKET_PREFIX . hash( 'sha256', $canonical_bot_id . "\0" . $client_scope );

		return $this->store->consume( $bucket, self::REQUEST_LIMIT, self::WINDOW_SECONDS );
	}
}
