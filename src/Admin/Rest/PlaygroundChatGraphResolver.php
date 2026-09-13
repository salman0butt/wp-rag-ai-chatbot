<?php
/**
 * Playground production chat-graph composition boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ProductionChatResponderFactory;
use WpRagAiChatbot\Debug\DebugTraceProjector;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\RAG\GroundingMode;
use WpRagAiChatbot\Retrieval\HybridRetriever;

/**
 * Composes the existing production M10/M11 graph for one Playground request.
 */
final class PlaygroundChatGraphResolver {
	/**
	 * Create the composition boundary from the shared production graph factory.
	 *
	 * @param ProductionChatResponderFactory $responders Shared M11 responder composition authority.
	 * @param DebugTraceProjector            $projector Existing M13 safe debug projector.
	 */
	public function __construct(
		private readonly ProductionChatResponderFactory $responders,
		private readonly DebugTraceProjector $projector
	) {
	}

	/**
	 * Compose one request-local production executor without running retrieval or generation.
	 *
	 * @param HybridRetriever            $retriever Existing M10 hybrid retriever.
	 * @param GenerationProvider         $provider Persisted generation provider.
	 * @param ChatAccessContext          $access Trusted persisted retrieval scope.
	 * @param PlaygroundRetrievalCapture $capture Request-local exact retrieval observer.
	 * @param string                     $model_id Persisted generation model identifier.
	 */
	public function resolve(
		HybridRetriever $retriever,
		GenerationProvider $provider,
		ChatAccessContext $access,
		PlaygroundRetrievalCapture $capture,
		string $model_id
	): ProductionPlaygroundExecutor {
		$responder = $this->responders->create(
			$retriever,
			$provider,
			null,
			null,
			$capture
		);

		return new ProductionPlaygroundExecutor(
			$responder,
			$access,
			$capture,
			$this->projector,
			$model_id,
			GroundingMode::STRICT
		);
	}
}
