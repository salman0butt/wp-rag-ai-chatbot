<?php
/**
 * Playground request-to-production-executor binding seam.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use Throwable;

/**
 * Resolves persisted Playground configuration and delegates one request to the production executor.
 */
final readonly class PlaygroundRequestHandler {
	/**
	 * Create the request handler from established production authorities.
	 *
	 * @param PlaygroundConfigurationResolver $configuration Persisted selector resolver.
	 * @param PlaygroundExecutorResolver      $executor Production executor composer.
	 */
	public function __construct(
		private PlaygroundConfigurationResolver $configuration,
		private PlaygroundExecutorResolver $executor
	) {
	}

	/**
	 * Handle one validated Playground request without duplicating retrieval or generation logic.
	 *
	 * @param PlaygroundRequest $request Validated persisted selectors plus question.
	 * @return array<string,mixed>
	 */
	public function handle( PlaygroundRequest $request ): array {
		try {
			$configuration = $this->configuration->resolve(
				$request->bot_id,
				$request->source_id,
				$request->collection_id
			);
			$executor      = $this->executor->resolve( $configuration );

			return ( new PlaygroundRestResource( $executor ) )->run( $request->question );
		} catch ( Throwable $throwable ) {
			unset( $throwable );

			return array(
				'error' => array(
					'code' => 'playground_failed',
				),
			);
		}
	}
}
