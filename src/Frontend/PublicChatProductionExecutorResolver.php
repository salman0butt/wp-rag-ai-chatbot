<?php
/**
 * Public production-executor resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use Closure;
use WpRagAiChatbot\Chat\ChatResponder;

/**
 * Bridges trusted persisted public runtime authority into the existing M11 responder graph.
 */
final readonly class PublicChatProductionExecutorResolver {
	/**
	 * Create one resolver.
	 *
	 * @param Closure(PublicChatRuntime):ChatResponder $responder_factory Existing production M11 responder composer.
	 * @param PublicChatAccessContextResolver          $access_context Trusted bot-scoped retrieval/access resolver.
	 */
	public function __construct(
		private Closure $responder_factory,
		private PublicChatAccessContextResolver $access_context
	) {
	}

	/**
	 * Resolve one thin public executor from server-owned persisted runtime configuration.
	 *
	 * @param PublicChatRuntime $runtime Trusted persisted public runtime authority.
	 */
	public function resolve( PublicChatRuntime $runtime ): ProductionPublicChatExecutor {
		$responder = ( $this->responder_factory )( $runtime );

		return new ProductionPublicChatExecutor(
			$responder,
			$this->access_context->resolve( $runtime ),
			$runtime->bot->model_id
		);
	}
}
