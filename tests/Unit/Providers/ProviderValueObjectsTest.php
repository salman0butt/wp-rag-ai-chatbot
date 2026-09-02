<?php
/**
 * Provider value object tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\ModelInfo;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\Providers\ProviderHealth;
use WpRagAiChatbot\Providers\ProviderHealthStatus;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Usage;

/**
 * Verifies normalized provider value-object behavior.
 */
final class ProviderValueObjectsTest extends TestCase {
	/**
	 * Provider IDs remain stable configuration keys.
	 */
	public function test_provider_ids_are_stable(): void {
		$this->require_class( ProviderIds::class );

		self::assertSame( 'openai_direct', ProviderIds::OPENAI_DIRECT );
		self::assertSame( 'openrouter_direct', ProviderIds::OPENROUTER_DIRECT );
		self::assertSame( 'wordpress_ai_client', ProviderIds::WORDPRESS_AI_CLIENT );
	}

	/**
	 * Generation requests normalize only the model identifier.
	 */
	public function test_generation_request_normalizes_model_id_and_preserves_input(): void {
		$this->require_class( GenerationRequest::class );

		$request = new GenerationRequest( '  gpt-test  ', '  user input  ', ' system instruction ', 512 );

		self::assertSame( 'gpt-test', $request->model_id );
		self::assertSame( '  user input  ', $request->input );
		self::assertSame( ' system instruction ', $request->instructions );
		self::assertSame( 512, $request->max_output_tokens );
	}

	/**
	 * Generation requests reject invalid required values and token bounds.
	 */
	public function test_generation_request_rejects_blank_model_input_and_invalid_token_bounds(): void {
		$this->require_class( GenerationRequest::class );

		foreach ( array(
			array( '   ', 'hello', null ),
			array( 'model', '   ', null ),
			array( 'model', 'hello', 0 ),
			array( 'model', 'hello', 32769 ),
		) as $case ) {
			try {
				new GenerationRequest( $case[0], $case[1], null, $case[2] );
				self::fail( 'Invalid generation request must throw.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/**
	 * Usage preserves unknown values and rejects invalid negative counts.
	 */
	public function test_usage_preserves_unknown_values_and_rejects_negative_tokens(): void {
		$this->require_class( Usage::class );

		$usage = new Usage( null, 4, null, array( 'provider_cost' => '0.01' ) );
		self::assertNull( $usage->input_tokens );
		self::assertSame( 4, $usage->output_tokens );
		self::assertNull( $usage->total_tokens );
		self::assertSame( array( 'provider_cost' => '0.01' ), $usage->safe_metadata );

		$this->expectException( InvalidArgumentException::class );
		new Usage( -1, null, null );
	}

	/**
	 * Completed generations require output while incomplete results may be empty.
	 */
	public function test_completed_generation_requires_output_but_incomplete_may_be_empty(): void {
		$this->require_class( GenerationResult::class );
		$this->require_class( GenerationStatus::class );
		$this->require_class( Usage::class );

		$usage  = new Usage();
		$result = new GenerationResult(
			'openai_direct',
			'gpt-test',
			'answer',
			GenerationStatus::COMPLETED,
			$usage,
			'req_123'
		);

		self::assertSame( 'answer', $result->output_text );
		self::assertSame( GenerationStatus::COMPLETED, $result->status );
		self::assertSame( 'req_123', $result->request_id );

		$incomplete = new GenerationResult(
			'openai_direct',
			'gpt-test',
			'',
			GenerationStatus::INCOMPLETE,
			$usage
		);
		self::assertSame( '', $incomplete->output_text );

		$this->expectException( InvalidArgumentException::class );
		new GenerationResult(
			'openai_direct',
			'gpt-test',
			'',
			GenerationStatus::COMPLETED,
			$usage
		);
	}

	/**
	 * Model context windows must be positive when known.
	 */
	public function test_model_info_requires_positive_context_window(): void {
		$this->require_class( ModelInfo::class );

		$model = new ModelInfo(
			'openrouter_direct',
			'vendor/model',
			'Vendor Model',
			array( 'text' ),
			array( 'text' ),
			array( 'temperature' ),
			128000,
			array( 'owned_by' => 'vendor' )
		);
		self::assertSame( 128000, $model->context_window );

		$this->expectException( InvalidArgumentException::class );
		new ModelInfo( 'openrouter_direct', 'vendor/model', 'Vendor Model', array(), array(), array(), 0 );
	}

	/**
	 * Provider exceptions and health expose normalized safe fields only.
	 */
	public function test_provider_exception_and_health_expose_only_normalized_safe_fields(): void {
		$this->require_class( ProviderException::class );
		$this->require_class( ProviderErrorCode::class );
		$this->require_class( ProviderHealth::class );
		$this->require_class( ProviderHealthStatus::class );

		$exception = new ProviderException(
			ProviderErrorCode::RATE_LIMIT,
			'openrouter_direct',
			'Rate limited by provider.',
			'req_safe'
		);
		self::assertSame( ProviderErrorCode::RATE_LIMIT, $exception->error_code );
		self::assertSame( 'openrouter_direct', $exception->provider_id );
		self::assertSame( 'req_safe', $exception->request_id );
		self::assertSame( 'Rate limited by provider.', $exception->getMessage() );

		$health = new ProviderHealth(
			'openrouter_direct',
			ProviderHealthStatus::CONFIGURED,
			'Configured without a paid health ping.'
		);
		self::assertSame( ProviderHealthStatus::CONFIGURED, $health->status );
		self::assertSame( 'Configured without a paid health ping.', $health->message );
	}

	/**
	 * Assert the intended RED is a missing provider class or enum.
	 *
	 * @param string $class_name Class or enum name.
	 */
	private function require_class( string $class_name ): void {
		self::assertTrue(
			class_exists( $class_name ) || enum_exists( $class_name ),
			sprintf( '%s must exist before provider value-object behavior can pass.', $class_name )
		);
	}
}
