<?php
/**
 * WordPress admin REST bootstrap.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WP_REST_Request;
use WpRagAiChatbot\Admin\AdminCapability;
use WpRagAiChatbot\Database\Repository\WpdbBotRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;
use WpRagAiChatbot\Providers\Credentials\AuthenticatedCredentialCipher;
use WpRagAiChatbot\Providers\Credentials\RuntimeCredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\RuntimeCryptoCapabilities;
use WpRagAiChatbot\Providers\Credentials\WordPressCredentialStore;

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

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/bots',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'list_bots' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create_bot' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/bots/(?P<id>[^/]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'get_bot' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( self::class, 'update_bot' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'delete_bot' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/providers/(?P<provider_id>[^/]+)/credential',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'get_provider_credential' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( self::class, 'put_provider_credential' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'delete_provider_credential' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
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

	/**
	 * Return one bounded deterministic bot page.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function list_bots( WP_REST_Request $request ): array {
		$page     = self::request_positive_int( $request->get_param( 'page' ), 1 );
		$per_page = self::request_positive_int( $request->get_param( 'per_page' ), 20 );

		if ( null === $page || null === $per_page || $per_page > 100 ) {
			return self::invalid_request();
		}

		return self::bots()->list( $page, $per_page );
	}

	/**
	 * Create one bot from a JSON settings payload.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function create_bot( WP_REST_Request $request ): array {
		$payload = self::settings_payload( $request->get_json_params(), false );
		if ( null === $payload ) {
			return self::invalid_request();
		}

		return self::bots()->create( $payload );
	}

	/**
	 * Read exactly one bot.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function get_bot( WP_REST_Request $request ): array {
		return self::bots()->read( (string) $request->get_param( 'id' ) );
	}

	/**
	 * Update exactly one bot using optimistic versioning.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function update_bot( WP_REST_Request $request ): array {
		$payload = self::settings_payload( $request->get_json_params(), true );
		if ( null === $payload || ! isset( $payload['version'] ) ) {
			return self::invalid_request();
		}

		return self::bots()->update( (string) $request->get_param( 'id' ), $payload );
	}

	/**
	 * Delete exactly one bot.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function delete_bot( WP_REST_Request $request ): array {
		return self::bots()->delete( (string) $request->get_param( 'id' ) );
	}

	/**
	 * Read safe credential configuration state for one direct provider.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function get_provider_credential( WP_REST_Request $request ): array {
		return self::provider_credentials()->read( (string) $request->get_param( 'provider_id' ) );
	}

	/**
	 * Store or replace one direct-provider credential without echoing it.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function put_provider_credential( WP_REST_Request $request ): array {
		$payload = $request->get_json_params();
		if ( null === $payload ) {
			return self::invalid_request();
		}

		return self::provider_credentials()->write(
			(string) $request->get_param( 'provider_id' ),
			$payload
		);
	}

	/**
	 * Reset only the plugin-managed credential for one direct provider.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function delete_provider_credential( WP_REST_Request $request ): array {
		return self::provider_credentials()->delete( (string) $request->get_param( 'provider_id' ) );
	}

	/**
	 * Build the repository-backed bot resource from WordPress services.
	 */
	private static function bots(): BotRestResource {
		global $wpdb;

		$connection = new WpdbConnection( $wpdb );

		return new BotRestResource(
			new WpdbBotRepository(
				$connection,
				new TableNames( $connection->prefix() )
			)
		);
	}

	/**
	 * Build the provider credential resource from the established M03 secure storage seams.
	 */
	private static function provider_credentials(): ProviderCredentialRestResource {
		$store = new WordPressCredentialStore(
			new AuthenticatedCredentialCipher( new RuntimeCryptoCapabilities() )
		);

		return new ProviderCredentialRestResource( new RuntimeCredentialSourceReader(), $store );
	}

	/**
	 * Normalize a positive integer REST parameter.
	 *
	 * @param mixed $value         Raw request parameter.
	 * @param int   $default_value Value used when the parameter is omitted.
	 */
	private static function request_positive_int( mixed $value, int $default_value ): ?int {
		if ( null === $value || '' === $value ) {
			return $default_value;
		}

		$validated = filter_var( $value, FILTER_VALIDATE_INT );
		if ( false === $validated || $validated < 1 ) {
			return null;
		}

		return $validated;
	}

	/**
	 * Normalize the bounded bot settings payload before entering the typed resource.
	 *
	 * @param array<string,mixed>|null $input Raw JSON payload.
	 * @param bool                     $require_version Whether optimistic version is required.
	 * @return array{name:string,enabled:bool,provider_id:string,model_id:string}|array{version:int,name:string,enabled:bool,provider_id:string,model_id:string}|null
	 */
	private static function settings_payload( ?array $input, bool $require_version ): ?array {
		if (
		null === $input
		|| ! isset( $input['name'], $input['provider_id'], $input['model_id'] )
		|| ! is_string( $input['name'] )
		|| ! is_bool( $input['enabled'] ?? null )
		|| ! is_string( $input['provider_id'] )
		|| ! is_string( $input['model_id'] )
		) {
			return null;
		}

		$payload = array(
			'name'        => $input['name'],
			'enabled'     => $input['enabled'],
			'provider_id' => $input['provider_id'],
			'model_id'    => $input['model_id'],
		);

		if ( ! $require_version ) {
			return $payload;
		}

		if ( ! isset( $input['version'] ) || ! is_int( $input['version'] ) || $input['version'] < 1 ) {
			return null;
		}

		return array( 'version' => $input['version'] ) + $payload;
	}

	/**
	 * Return one stable malformed-request response.
	 *
	 * @return array{error:array{code:string,message:string}}
	 */
	private static function invalid_request(): array {
		return array(
			'error' => array(
				'code'    => 'invalid_request',
				'message' => 'Request parameters are invalid.',
			),
		);
	}
}
