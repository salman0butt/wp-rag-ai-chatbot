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
use WpRagAiChatbot\Database\Repository\WpdbDocumentRepository;
use WpRagAiChatbot\Database\Repository\WpdbJobReadRepository;
use WpRagAiChatbot\Database\Repository\WpdbJobRepository;
use WpRagAiChatbot\Database\Repository\WpdbKnowledgeSourceRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;
use WpRagAiChatbot\Jobs\SystemClock;
use WpRagAiChatbot\Providers\Credentials\AuthenticatedCredentialCipher;
use WpRagAiChatbot\Providers\Credentials\RuntimeCredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\RuntimeCryptoCapabilities;
use WpRagAiChatbot\Providers\Credentials\WordPressCredentialStore;
use WpRagAiChatbot\Providers\ProviderBootstrap;
use WpRagAiChatbot\Retrieval\Lexical\WpdbChunkSearchStore;

/**
 * Registers the plugin administration REST resources.
 */
final class AdminRestBootstrap {
	/**
	 * Versioned REST namespace for administration resources.
	 */
	public const REST_NAMESPACE = 'wp-rag-ai-chatbot/v1';

	/**
	 * Register administration REST routes.
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

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/models',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'list_models' ),
				'permission_callback' => array( AdminCapability::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/onboarding/readiness',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_onboarding_readiness' ),
				'permission_callback' => array( AdminCapability::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/knowledge/sources',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'list_knowledge_sources' ),
				'permission_callback' => array( AdminCapability::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/knowledge/sources/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_knowledge_source' ),
				'permission_callback' => array( AdminCapability::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/knowledge/sources/(?P<id>\\d+)/documents',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'list_knowledge_documents' ),
				'permission_callback' => array( AdminCapability::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/knowledge/sources/(?P<id>\\d+)/documents/(?P<document_key>[^/]+)/chunks',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'list_knowledge_chunks' ),
				'permission_callback' => array( AdminCapability::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/knowledge/jobs',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'list_knowledge_jobs' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'enqueue_knowledge_job' ),
					'permission_callback' => array( AdminCapability::class, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/knowledge/jobs/(?P<job_key>[^/]+)/(?P<action>cancel|retry)',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'mutate_knowledge_job' ),
				'permission_callback' => array( AdminCapability::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/admin/debug/playground',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'run_playground' ),
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

	/**
	 * Fail closed until the production Playground resource is bound to request-local services.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function run_playground( WP_REST_Request $request ): array {
		unset( $request );

		return self::invalid_request();
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
		return self::provider_credentials()->write(
			(string) $request->get_param( 'provider_id' ),
			$request->get_json_params()
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
	 * Return normalized models for one configured provider.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function list_models( WP_REST_Request $request ): array {
		$provider_id = $request->get_param( 'provider_id' );
		$purpose     = $request->get_param( 'purpose' );
		$capability  = $request->get_param( 'capability' );

		if (
			! is_string( $provider_id )
			|| '' === trim( $provider_id )
			|| ! is_string( $purpose )
			|| '' === trim( $purpose )
			|| ( null !== $capability && '' !== $capability && ! is_string( $capability ) )
		) {
			return self::invalid_request();
		}

		$normalized_capability = null;
		if ( is_string( $capability ) && '' !== trim( $capability ) ) {
			$normalized_capability = trim( $capability );
		}

		return self::model_readiness()->models(
			trim( $provider_id ),
			trim( $purpose ),
			$normalized_capability
		);
	}

	/**
	 * Return onboarding progress derived from persisted provider/model/bot truth.
	 *
	 * @return array{ready:bool,next_step:string}
	 */
	public static function get_onboarding_readiness(): array {
		return self::model_readiness()->readiness();
	}

	/**
	 * Return one bounded page of safe persisted knowledge-source fields.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function list_knowledge_sources( WP_REST_Request $request ): array {
		$page     = self::request_positive_int( $request->get_param( 'page' ), 1 );
		$per_page = self::request_positive_int( $request->get_param( 'per_page' ), 20 );

		if ( null === $page || null === $per_page || $per_page > 100 ) {
			return self::invalid_request();
		}

		return self::knowledge_sources()->list( $page, $per_page );
	}

	/**
	 * Return one safe knowledge source detail.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function get_knowledge_source( WP_REST_Request $request ): array {
		$source_id = self::request_positive_int( $request->get_param( 'id' ), 0 );
		if ( null === $source_id ) {
			return self::invalid_request();
		}

		return self::knowledge_detail()->source( $source_id );
	}

	/**
	 * Return one bounded document page for a knowledge source.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function list_knowledge_documents( WP_REST_Request $request ): array {
		$source_id = self::request_positive_int( $request->get_param( 'id' ), 0 );
		$page      = self::request_positive_int( $request->get_param( 'page' ), 1 );
		$per_page  = self::request_positive_int( $request->get_param( 'per_page' ), 20 );

		if ( null === $source_id || null === $page || null === $per_page || $per_page > 100 ) {
			return self::invalid_request();
		}

		return self::knowledge_detail()->documents( $source_id, $page, $per_page );
	}

	/**
	 * Return one bounded chunk page for a knowledge document.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function list_knowledge_chunks( WP_REST_Request $request ): array {
		$source_id    = self::request_positive_int( $request->get_param( 'id' ), 0 );
		$document_key = $request->get_param( 'document_key' );
		$page         = self::request_positive_int( $request->get_param( 'page' ), 1 );
		$per_page     = self::request_positive_int( $request->get_param( 'per_page' ), 20 );

		if (
			null === $source_id
			|| ! is_string( $document_key )
			|| '' === trim( $document_key )
			|| null === $page
			|| null === $per_page
			|| $per_page > 100
		) {
			return self::invalid_request();
		}

		return self::knowledge_detail()->chunks( $source_id, $document_key, $page, $per_page );
	}

	/**
	 * Return one bounded page of safe persisted job status fields.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function list_knowledge_jobs( WP_REST_Request $request ): array {
		$page     = self::request_positive_int( $request->get_param( 'page' ), 1 );
		$per_page = self::request_positive_int( $request->get_param( 'per_page' ), 20 );

		if ( null === $page || null === $per_page || $per_page > 100 ) {
			return self::invalid_request();
		}

		return self::knowledge_jobs()->list( $page, $per_page );
	}

	/**
	 * Enqueue one document indexing job through the established M09 queue seam.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function enqueue_knowledge_job( WP_REST_Request $request ): array {
		return self::knowledge_jobs()->enqueue( $request->get_json_params() );
	}

	/**
	 * Apply one supported job lifecycle mutation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>
	 */
	public static function mutate_knowledge_job( WP_REST_Request $request ): array {
		$job_key = $request->get_param( 'job_key' );
		$action  = $request->get_param( 'action' );

		if ( ! is_string( $job_key ) || '' === trim( $job_key ) || ! is_string( $action ) ) {
			return self::invalid_request();
		}

		return match ( $action ) {
			'cancel' => self::knowledge_jobs()->cancel( trim( $job_key ) ),
			'retry'  => self::knowledge_jobs()->retry( trim( $job_key ) ),
			default  => self::invalid_request(),
		};
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
	 * Build Task 5 from the existing provider registry/configuration and bot repository seams.
	 */
	private static function model_readiness(): ModelReadinessRestResource {
		global $wpdb;

		$connection = new WpdbConnection( $wpdb );

		return new ModelReadinessRestResource(
			ProviderBootstrap::registry(),
			ProviderBootstrap::configuration(),
			new WpdbBotRepository(
				$connection,
				new TableNames( $connection->prefix() )
			)
		);
	}

	/**
	 * Build the M13 knowledge inventory from the established source repository.
	 */
	private static function knowledge_sources(): KnowledgeSourceRestResource {
		global $wpdb;

		$connection = new WpdbConnection( $wpdb );

		return new KnowledgeSourceRestResource(
			new WpdbKnowledgeSourceRepository(
				$connection,
				new TableNames( $connection->prefix() )
			)
		);
	}

	/**
	 * Build the M13 bounded source/document/chunk inspection resource.
	 */
	private static function knowledge_detail(): KnowledgeDetailRestResource {
		global $wpdb;

		$connection = new WpdbConnection( $wpdb );
		$tables     = new TableNames( $connection->prefix() );

		return new KnowledgeDetailRestResource(
			new WpdbKnowledgeSourceRepository( $connection, $tables ),
			new WpdbDocumentRepository( $connection, $tables ),
			new WpdbChunkSearchStore( $connection, $tables )
		);
	}

	/**
	 * Build the M13 job inspection/lifecycle resource over the existing M09 queue seams.
	 */
	private static function knowledge_jobs(): KnowledgeJobRestResource {
		global $wpdb;

		$connection = new WpdbConnection( $wpdb );
		$tables     = new TableNames( $connection->prefix() );

		return new KnowledgeJobRestResource(
			new WpdbJobReadRepository( $connection, $tables ),
			new WpdbJobRepository( $connection, $tables ),
			new SystemClock()
		);
	}

	/**
	 * Normalize a positive integer REST parameter.
	 *
	 * @param mixed $value Raw request parameter.
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
