<?php
/**
 * M13 Playground WordPress callback binding tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;

/**
 * Specifies the thin WordPress request adapter into the production Playground runtime.
 */
final class PlaygroundCallbackBindingTest extends TestCase {
	/** The callback must parse the bounded DTO and delegate through the shared production handler. */
	public function test_callback_binds_validated_payload_to_shared_runtime_handler(): void {
		$method   = new ReflectionMethod( AdminRestBootstrap::class, 'run_playground' );
		$filename = $method->getFileName();
		self::assertIsString( $filename );

		$lines = file( $filename );
		self::assertIsArray( $lines );
		$body = implode(
			'',
			array_slice( $lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 )
		);

		self::assertStringContainsString( 'PlaygroundRequest::from_array( $request->get_json_params() )', $body );
		self::assertStringContainsString( 'null === $playground_request', $body );
		self::assertStringContainsString( 'PlaygroundRuntimeBootstrap::handler(', $body );
		self::assertStringContainsString( '->handle( $playground_request )', $body );
		self::assertStringNotContainsString( 'unset( $request )', $body );
	}
}
