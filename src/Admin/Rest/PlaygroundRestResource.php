<?php
/**
 * Protected administrator Playground execution resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use Closure;
use Throwable;
use WpRagAiChatbot\Retrieval\RetrievalException;

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
			return $this->error( 'invalid_request' );
		}

		try {
			$response = ( $this->executor )( $question );
		} catch ( RetrievalException $exception ) {
			unset( $exception );

			return $this->error( 'retrieval_unavailable' );
		} catch ( Throwable $throwable ) {
			unset( $throwable );

			return $this->error( 'playground_failed' );
		}

		return is_array( $response ) ? $response : $this->error( 'playground_failed' );
	}

	/**
	 * Create one stable safe error payload.
	 *
	 * @param string $code Repository-owned error code.
	 * @return array<string,array<string,string>>
	 */
	private function error( string $code ): array {
		return array(
			'error' => array(
				'code' => $code,
			),
		);
	}
}
