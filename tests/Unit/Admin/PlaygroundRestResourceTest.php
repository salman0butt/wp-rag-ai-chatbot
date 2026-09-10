<?php
/**
 * M13 Playground REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Retrieval\RetrievalException;

/**
 * Verifies Playground requests are bounded before production execution begins.
 */
final class PlaygroundRestResourceTest extends TestCase {
	/** Invalid questions must never reach the production chat pipeline. */
	public function test_run_rejects_invalid_question_before_execution(): void {
		$executions = 0;
		$executor   = static function () use ( &$executions ): array {
			++$executions;

			return array();
		};

		self::assertSame( 'invalid_request', $this->call_run( $executor, '' )['error']['code'] );
		self::assertSame(
			'invalid_request',
			$this->call_run( $executor, str_repeat( 'a', 16385 ) )['error']['code']
		);
		self::assertSame( 0, $executions );
	}

	/** Retrieval failures expose only the stable repository-owned error code. */
	public function test_run_maps_retrieval_failure_without_leaking_message(): void {
		$executor = static function (): array {
			throw new RetrievalException( 'PROVIDER-SECRET-SENTINEL' );
		};

		$response = $this->call_run( $executor, 'How does retrieval work?' );

		self::assertSame( 'retrieval_unavailable', $response['error']['code'] );
		self::assertStringNotContainsString( 'PROVIDER-SECRET-SENTINEL', wp_json_encode( $response ) ?: '' );
	}

	/** Other internal failures expose only the generic Playground failure code. */
	public function test_run_maps_internal_failure_without_leaking_message(): void {
		$executor = static function (): array {
			throw new RuntimeException( 'INTERNAL-SECRET-SENTINEL' );
		};

		$response = $this->call_run( $executor, 'Explain the answer.' );

		self::assertSame( 'playground_failed', $response['error']['code'] );
		self::assertStringNotContainsString( 'INTERNAL-SECRET-SENTINEL', wp_json_encode( $response ) ?: '' );
	}

	/**
	 * Invoke the resource dynamically so static analysis can reach PHPUnit behavior checks.
	 *
	 * @param Closure $executor Request-local production executor fixture.
	 * @param string  $question Question input.
	 * @return array<string,mixed>
	 */
	private function call_run( Closure $executor, string $question ): array {
		$class = 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundRestResource';
		self::assertTrue( class_exists( $class ), 'PlaygroundRestResource must exist.' );
		$resource = new $class( $executor );
		$response = call_user_func( array( $resource, 'run' ), $question );
		self::assertIsArray( $response );

		return $response;
	}
}
