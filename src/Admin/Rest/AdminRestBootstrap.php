<?php
/**
 * WordPress admin REST bootstrap.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Admin\AdminCapability;

/**
 * Registers the plugin administration REST resources.
 */
final class AdminRestBootstrap {
	/**
	 * Versioned REST namespace for administration resources.
	 */
	public const REST_NAMESPACE = 'wp-rag-ai-chatbot/v1';

	/**
	 * Register M12 administration REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_bootstrap' ),
				'permission_callback' => array( AdminCapability::class, 'can_manage' ),
			)
		);
	}

	/**
	 * Return non-secret identifiers needed to bootstrap the admin application.
	 *
	 * @return array{plugin:string,api_version:string}
	 */
	public static function get_bootstrap(): array {
		return array(
			'plugin'      => 'wp-rag-ai-chatbot',
			'api_version' => 'v1',
		);
	}
}
