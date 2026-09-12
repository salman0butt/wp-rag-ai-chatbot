<?php
/**
 * Public chat WordPress REST bootstrap.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WP_REST_Request;

/**
 * Registers the narrow anonymous public chat transport.
 */
final class PublicChatRestBootstrap {
	/** Versioned public REST namespace. */
	public const REST_NAMESPACE = 'wp-rag-ai-chatbot/v1';

	/** Public chat route. */
	public const REST_ROUTE = '/chat';

	/** Register the public chat route. */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			self::route_definition()
		);
	}

	/**
	 * Return the route definition without exposing request-level runtime controls.
	 *
	 * @return array{methods:string,callback:array{class-string<self>,string},permission_callback:array{class-string<self>,string}}
	 */
	public static function route_definition(): array {
		return array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'run_chat' ),
			'permission_callback' => array( self::class, 'allow_public' ),
		);
	}

	/**
	 * Public chat intentionally permits anonymous callers; abuse controls run inside the callback.
	 */
	public static function allow_public(): bool {
		return true;
	}

	/**
	 * Fail closed until the trusted WordPress runtime composition is bound.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,array<string,string>>
	 */
	public static function run_chat( WP_REST_Request $request ): array {
		unset( $request );

		return array(
			'error' => array(
				'code' => 'chat_unavailable',
			),
		);
	}
}
