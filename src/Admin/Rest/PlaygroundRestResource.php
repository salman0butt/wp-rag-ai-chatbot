<?php
/**
 * Protected administrator Playground execution resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use Closure;

/**
 * Validates bounded Playground input before delegating to production execution.
 */
final class PlaygroundRestResource {
	/** Maximum accepted question size, matching the production ChatRequest boundary. */
	private const MAX_QUESTION_BYTES = 16384;

	/**
	 * Request-local production executor.
	 *
	 * @var Closure
	 */
	private Closure $executor;

	/**
	 * Create the Playground resource.
	 *
	 * @param Closure $executor Request-local production executor.
	 */
	public function __construct( Closure $executor ) {
		$this->executor = $executor;
	}

	/**
	 * Execute one validated Playground question.
	 *
	 * @param string $question Administrator question.
	 * @return array<string,mixed>
	 */
	public function run( string $question ): array {
		$question = trim( $question );

		if ( '' === $question || strlen( $question ) > self::MAX_QUESTION_BYTES ) {
			return array(
				'error' => array(
					'code' => 'invalid_request',
				),
			);
		}

		$response = ( $this->executor )( $question );

		return is_array( $response ) ? $response : array(
			'error' => array(
				'code' => 'playground_failed',
			),
		);
	}
}
