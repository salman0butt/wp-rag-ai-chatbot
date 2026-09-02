<?php
/**
 * WordPress 7 AI Client provider adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\WordPressAi;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Providers\WordPressAi\WordPressAiClientProvider;
use WpRagAiChatbot\Tests\Support\Providers\WordPressAi\FakeWordPressAiBuilder;
use WpRagAiChatbot\Tests\Support\Providers\WordPressAi\FakeWordPressAiError;
use WpRagAiChatbot\Tests\Support\Providers\WordPressAi\FakeWordPressAiResult;
use WpRagAiChatbot\Tests\Support\Providers\WordPressAi\RuntimeShim;

/**
 * Verifies optional WordPress 7 AI Client compatibility through public APIs only.
 */
final class WordPressAiClientProviderTest extends TestCase {
	/**
	 * WordPress 6.9-style runtime without the AI Client function degrades safely.
	 */
	public function test_missing_ai_client_is_unavailable_and_generation_fails_safely(): void {
		$this->require_adapter();
		$provider = new WordPressAiClientProvider( new SecretRedactor() );

		self::assertFalse( $provider->available() );
		self::assertSame( ProviderIds::WORDPRESS_AI_CLIENT, $provider->provider_id() );
		self::assertSame( ProviderHealthStatus::UNAVAILABLE, $provider->health()->status );

		try {
			$provider->generate( new GenerationRequest( 'core-model', 'Hello' ) );
			self::fail( 'Expected unsupported-capability failure without WordPress AI Client.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::UNSUPPORTED_CAPABILITY, $exception->error_code );
			self::assertSame( ProviderIds::WORDPRESS_AI_CLIENT, $exception->provider_id );
		}
	}

	/**
	 * Explicit wp_supports_ai() false keeps the adapter unavailable without building a prompt.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_support_false_is_unavailable_without_prompt_creation(): void {
		$this->require_adapter();
		$this->load_runtime_shim();
		RuntimeShim::$supports_ai = false;
		RuntimeShim::$inputs      = array();
		RuntimeShim::$builder     = new FakeWordPressAiBuilder( new FakeWordPressAiResult( 'unused', '', array() ) );

		$provider = new WordPressAiClientProvider( new SecretRedactor() );
		self::assertFalse( $provider->available() );
		self::assertSame( ProviderHealthStatus::UNAVAILABLE, $provider->health()->status );

		try {
			$provider->generate( new GenerationRequest( 'core-model', 'Hello' ) );
			self::fail( 'Expected unsupported-capability failure when Core reports no AI support.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::UNSUPPORTED_CAPABILITY, $exception->error_code );
			self::assertSame( array(), RuntimeShim::$inputs );
		}
	}

	/**
	 * Supported runtime calls only documented builder/result APIs and normalizes explicit metadata.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_supported_runtime_normalizes_public_result_data(): void {
		$this->require_adapter();
		$this->load_runtime_shim();
		RuntimeShim::$supports_ai = true;
		RuntimeShim::$inputs      = array();
		$result                   = new FakeWordPressAiResult(
			'Hello from Core',
			'wp-result-1',
			array(
				'modelMetadata' => array( 'id' => 'core/actual-model' ),
				'tokenUsage'    => array(
					'promptTokens'     => 5,
					'completionTokens' => 3,
					'totalTokens'      => 8,
				),
				'candidates'    => array(
					array( 'finishReason' => 'stop' ),
				),
			)
		);
		$builder                  = new FakeWordPressAiBuilder( $result );
		RuntimeShim::$builder     = $builder;

		$provider   = new WordPressAiClientProvider( new SecretRedactor() );
		$generation = $provider->generate( new GenerationRequest( 'core/requested-model', 'Say hello', 'Be concise', 64 ) );

		self::assertTrue( $provider->available() );
		self::assertSame( ProviderHealthStatus::CONFIGURED, $provider->health()->status );
		self::assertSame( array( 'Say hello' ), RuntimeShim::$inputs );
		self::assertSame(
			array(
				array( 'system', 'Be concise' ),
				array( 'max_tokens', 64 ),
				array( 'model', 'core/requested-model' ),
				array( 'generate' ),
			),
			$builder->calls
		);
		self::assertSame( ProviderIds::WORDPRESS_AI_CLIENT, $generation->provider_id );
		self::assertSame( 'core/actual-model', $generation->model_id );
		self::assertSame( 'Hello from Core', $generation->output_text );
		self::assertSame( GenerationStatus::COMPLETED, $generation->status );
		self::assertSame( 5, $generation->usage->input_tokens );
		self::assertSame( 3, $generation->usage->output_tokens );
		self::assertSame( 8, $generation->usage->total_tokens );
		self::assertSame( 'wp-result-1', $generation->request_id );
	}

	/**
	 * Missing optional metadata stays unknown and optional builder calls are omitted.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_metadata_uses_request_fallbacks_without_fabrication(): void {
		$this->require_adapter();
		$this->load_runtime_shim();
		RuntimeShim::$supports_ai = true;
		RuntimeShim::$inputs      = array();
		$result                   = new FakeWordPressAiResult(
			'Fallback text',
			'',
			array(
				'tokenUsage' => array( 'promptTokens' => '5' ),
				'candidates' => array( array( 'finishReason' => 'tool_calls' ) ),
			)
		);
		$builder                  = new FakeWordPressAiBuilder( $result );
		RuntimeShim::$builder     = $builder;

		$generation = ( new WordPressAiClientProvider( new SecretRedactor() ) )->generate(
			new GenerationRequest( 'core/requested-model', 'Hello', '   ', null )
		);

		self::assertSame( array( 'Hello' ), RuntimeShim::$inputs );
		self::assertSame(
			array(
				array( 'model', 'core/requested-model' ),
				array( 'generate' ),
			),
			$builder->calls
		);
		self::assertSame( 'core/requested-model', $generation->model_id );
		self::assertSame( GenerationStatus::UNKNOWN, $generation->status );
		self::assertNull( $generation->usage->input_tokens );
		self::assertNull( $generation->usage->output_tokens );
		self::assertNull( $generation->usage->total_tokens );
		self::assertNull( $generation->request_id );
	}

	/**
	 * Length-style public finish reasons normalize to incomplete.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_length_finish_reasons_are_incomplete(): void {
		$this->require_adapter();
		$this->load_runtime_shim();
		RuntimeShim::$supports_ai = true;

		foreach ( array( 'length', 'max_tokens' ) as $finish_reason ) {
			$result               = new FakeWordPressAiResult(
				'Partial',
				'partial-id',
				array( 'candidates' => array( array( 'finishReason' => $finish_reason ) ) )
			);
			RuntimeShim::$builder = new FakeWordPressAiBuilder( $result );
			$generation           = ( new WordPressAiClientProvider( new SecretRedactor() ) )->generate(
				new GenerationRequest( 'core-model', 'Hello' )
			);
			self::assertSame( GenerationStatus::INCOMPLETE, $generation->status );
		}
	}

	/**
	 * WP_Error diagnostics are sanitized and expose only safe public error data.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_wp_error_is_sanitized_without_inspecting_core_credentials(): void {
		$this->require_adapter();
		$this->load_runtime_shim();
		RuntimeShim::$supports_ai = true;
		$error                    = new FakeWordPressAiError(
			"Authorization: Bearer core-secret\nProvider unavailable",
			array( 'request_id' => 'wp-error-1' )
		);
		RuntimeShim::$error       = $error;
		RuntimeShim::$builder     = new FakeWordPressAiBuilder( $error );

		try {
			( new WordPressAiClientProvider( new SecretRedactor() ) )->generate(
				new GenerationRequest( 'core-model', 'Hello' )
			);
			self::fail( 'Expected normalized WordPress AI provider failure.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderIds::WORDPRESS_AI_CLIENT, $exception->provider_id );
			self::assertStringNotContainsString( 'core-secret', $exception->getMessage() );
			self::assertStringContainsString( '[REDACTED]', $exception->getMessage() );
			self::assertSame( 'wp-error-1', $exception->request_id );
		}
	}

	/**
	 * Unexpected Core throwables fail closed instead of republishing opaque messages.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_unexpected_core_throwable_does_not_republish_opaque_message(): void {
		$this->require_adapter();
		$this->load_runtime_shim();
		RuntimeShim::$supports_ai = true;
		$builder                  = new FakeWordPressAiBuilder( new FakeWordPressAiResult( 'unused', '', array() ) );
		$builder->exception       = new \RuntimeException( 'opaque-core-credential-should-never-escape' );
		RuntimeShim::$builder     = $builder;

		try {
			( new WordPressAiClientProvider( new SecretRedactor() ) )->generate(
				new GenerationRequest( 'core-model', 'Hello' )
			);
			self::fail( 'Expected normalized WordPress AI throwable failure.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::UNKNOWN, $exception->error_code );
			self::assertSame( ProviderIds::WORDPRESS_AI_CLIENT, $exception->provider_id );
			self::assertSame( 'WordPress AI Client request failed.', $exception->getMessage() );
			self::assertStringNotContainsString( 'opaque-core-credential', $exception->getMessage() );
		}
	}

	/**
	 * A documented result that cannot produce valid text becomes malformed response.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_result_text_failure_is_malformed_response(): void {
		$this->require_adapter();
		$this->load_runtime_shim();
		RuntimeShim::$supports_ai = true;
		$result                   = new FakeWordPressAiResult( '', 'bad-result', array(), true );
		RuntimeShim::$builder     = new FakeWordPressAiBuilder( $result );

		try {
			( new WordPressAiClientProvider( new SecretRedactor() ) )->generate(
				new GenerationRequest( 'core-model', 'Hello' )
			);
			self::fail( 'Expected malformed WordPress AI result failure.' );
		} catch ( ProviderException $exception ) {
			self::assertSame( ProviderErrorCode::MALFORMED_RESPONSE, $exception->error_code );
		}
	}

	/**
	 * Load exact global WordPress AI API shims only inside isolated child processes.
	 */
	private function load_runtime_shim(): void {
		require_once dirname( __DIR__, 3 ) . '/Support/Providers/WordPressAi/runtime-functions.php';
	}

	/**
	 * Require the intended missing-adapter RED before implementation.
	 */
	private function require_adapter(): void {
		self::assertTrue(
			class_exists( WordPressAiClientProvider::class ),
			'WordPressAiClientProvider must exist before WordPress AI adapter behavior can pass.'
		);
	}
}
