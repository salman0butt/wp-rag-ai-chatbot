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
use RuntimeException;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Providers\WordPressAi\WordPressAiClientProvider;
use WpRagAiChatbot\Tests\Support\Providers\WordPressAi\RuntimeShim;

/**
 * Deterministic fake for the documented WordPress AI prompt builder methods.
 */
final class FakeWordPressAiBuilder {
	/**
	 * Recorded public builder calls in order.
	 *
	 * @var array<int, array<int, int|string>>
	 */
	public array $calls = array();

	/**
	 * Create a fake builder around one deterministic result.
	 *
	 * @param object $result Result returned by generate_text_result().
	 */
	public function __construct( private object $result ) {
	}

	/**
	 * Record the documented system-instruction call.
	 *
	 * @param string $instruction System instruction.
	 */
	public function using_system_instruction( string $instruction ): self {
		$this->calls[] = array( 'system', $instruction );
		return $this;
	}

	/**
	 * Record the documented maximum-token call.
	 *
	 * @param int $tokens Maximum output tokens.
	 */
	public function using_max_tokens( int $tokens ): self {
		$this->calls[] = array( 'max_tokens', $tokens );
		return $this;
	}

	/**
	 * Record the documented model-preference call.
	 *
	 * @param string $model_id Requested model identifier.
	 */
	public function using_model_preference( string $model_id ): self {
		$this->calls[] = array( 'model', $model_id );
		return $this;
	}

	/**
	 * Return the deterministic result.
	 */
	public function generate_text_result(): object {
		$this->calls[] = array( 'generate' );
		return $this->result;
	}
}

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Test double mirrors documented WordPress AI result method names exactly.
/**
 * Deterministic fake for the documented WordPress AI result methods.
 */
final class FakeWordPressAiResult {
	/**
	 * Create a fake result.
	 *
	 * @param string               $text Text returned by toText().
	 * @param string               $id Safe result identifier.
	 * @param array<string, mixed> $data Public normalized result data.
	 * @param bool                 $throw_on_text Whether toText() should fail.
	 */
	public function __construct(
		private string $text,
		private string $id,
		private array $data,
		private bool $throw_on_text = false
	) {
	}

	/**
	 * Return deterministic generated text.
	 */
	public function toText(): string {
		if ( $this->throw_on_text ) {
			throw new RuntimeException( 'No text result available.' );
		}

		return $this->text;
	}

	/**
	 * Return deterministic safe result ID.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Return deterministic public result metadata.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return $this->data;
	}
}
// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

/**
 * Minimal WP_Error-compatible fake used only through public error methods.
 */
final class FakeWordPressAiError {
	/**
	 * Create a deterministic error.
	 *
	 * @param string $message Safe/error diagnostic input.
	 * @param mixed  $data Public error data.
	 */
	public function __construct(
		private string $message,
		private mixed $data
	) {
	}

	/**
	 * Return the error message.
	 */
	public function get_error_message(): string {
		return $this->message;
	}

	/**
	 * Return public error data.
	 */
	public function get_error_data(): mixed {
		return $this->data;
	}
}

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
	#[PreserveGlobalState(false)]
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
	#[PreserveGlobalState(false)]
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
	#[PreserveGlobalState(false)]
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
	#[PreserveGlobalState(false)]
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
	#[PreserveGlobalState(false)]
	public function test_wp_error_is_sanitized_without_inspecting_core_credentials(): void {
		$this->require_adapter();
		$this->load_runtime_shim();
		RuntimeShim::$supports_ai = true;
		$error                     = new FakeWordPressAiError(
			"Authorization: Bearer core-secret\nProvider unavailable",
			array( 'request_id' => 'wp-error-1' )
		);
		RuntimeShim::$error        = $error;
		RuntimeShim::$builder      = new FakeWordPressAiBuilder( $error );

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
	 * A documented result that cannot produce valid text becomes malformed response.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
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
