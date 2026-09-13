<?php
/**
 * Public chat WordPress REST bootstrap.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use InvalidArgumentException;
use WP_REST_Request;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbBotRepository;
use WpRagAiChatbot\Database\Repository\WpdbBotRetrievalBindingRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;

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
	 * Build the canonical production public-chat executor resolver.
	 *
	 * @param Connection $connection Existing WordPress database connection.
	 * @param TableNames $tables Existing per-site table-name authority.
	 */
	public static function executor_resolver( Connection $connection, TableNames $tables ): PublicChatProductionExecutorResolver {
		return PublicChatRuntimeBootstrap::executor_resolver( $connection, $tables );
	}

	/**
	 * Validate public input and apply the persisted abuse/runtime boundary.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function run_chat( WP_REST_Request $request ): array {
		try {
			$public_request = PublicChatWordPressRequestAdapter::request( $request->get_json_params() );
			$client_scope   = PublicChatWordPressRequestAdapter::client_scope( $_SERVER, wp_salt( 'auth' ) );
		} catch ( InvalidArgumentException ) {
			return self::error( 'invalid_request' );
		}

		global $wpdb;
		$connection        = new WpdbConnection( $wpdb );
		$tables            = new TableNames( $connection->prefix() );
		$executor_resolver = self::executor_resolver( $connection, $tables );
		$resource          = new PublicChatRestResource(
			new PublicChatAbuseGuard( new WpdbPublicChatRateLimitStore( $connection ) ),
			new PublicChatRuntimeResolver(
				new WpdbBotRepository( $connection, $tables ),
				new WpdbBotRetrievalBindingRepository( $connection, $tables )
			),
			static fn ( PublicChatRuntime $runtime ): ProductionPublicChatExecutor => $executor_resolver->resolve( $runtime )
		);

		return $resource->run( $public_request, $client_scope );
	}

	/**
	 * Create one stable non-sensitive public error payload.
	 *
	 * @param string $code Repository-owned public error code.
	 * @return array<string,array<string,string>>
	 */
	private static function error( string $code ): array {
		return array(
			'error' => array(
				'code' => $code,
			),
		);
	}
}
