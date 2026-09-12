<?php
/**
 * Playground production chat-graph composition boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ChatOrchestrator;
use WpRagAiChatbot\Chat\ChatRequestPolicy;
use WpRagAiChatbot\Citations\CitationValidator;
use WpRagAiChatbot\Debug\DebugTraceProjector;
use WpRagAiChatbot\Memory\MemoryAssembler;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\RAG\GroundingMode;
use WpRagAiChatbot\RAG\GroundingPolicy;
use WpRagAiChatbot\RAG\PromptBuilder;
use WpRagAiChatbot\Retrieval\HybridRetriever;

/**
 * Composes the existing production M10/M11 graph for one Playground request.
 */
final class PlaygroundChatGraphResolver {
	/**
	 * Create the composition boundary from existing production collaborators.
	 *
	 * @param ChatRequestPolicy   $request_policy Existing M11 request policy.
	 * @param MemoryAssembler     $memory Existing M11 memory assembler.
	 * @param GroundingPolicy     $grounding Existing M11 grounding policy.
	 * @param PromptBuilder       $prompt Existing M11 prompt builder.
	 * @param CitationValidator   $citations Existing M11 citation validator.
	 * @param DebugTraceProjector $projector Existing M13 safe debug projector.
	 */
	public function __construct(
		private readonly ChatRequestPolicy $request_policy,
		private readonly MemoryAssembler $memory,
		private readonly GroundingPolicy $grounding,
		private readonly PromptBuilder $prompt,
		private readonly CitationValidator $citations,
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
		$orchestrator = new ChatOrchestrator(
			$this->request_policy,
			$this->memory,
			$retriever,
			$this->grounding,
			$this->prompt,
			$provider,
			$this->citations,
			null,
			null,
			$capture
		);

		return new ProductionPlaygroundExecutor(
			$orchestrator,
			$access,
			$capture,
			$this->projector,
			$model_id,
			GroundingMode::STRICT
		);
	}
}
