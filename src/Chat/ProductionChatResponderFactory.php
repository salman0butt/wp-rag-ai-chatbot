<?php
/**
 * Shared production chat-responder composition.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

use WpRagAiChatbot\Citations\CitationValidator;
use WpRagAiChatbot\Conversations\MessageRepository;
use WpRagAiChatbot\Memory\MemoryAssembler;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\RAG\GroundingPolicy;
use WpRagAiChatbot\RAG\PromptBuilder;
use WpRagAiChatbot\Retrieval\HybridRetriever;

/**
 * Composes the existing production M11 graph for any trusted runtime consumer.
 */
final readonly class ProductionChatResponderFactory {
	/**
	 * Create the shared graph composer.
	 *
	 * @param ChatRequestPolicy  $request_policy Existing M11 request policy.
	 * @param MemoryAssembler    $memory Existing M11 memory assembler.
	 * @param GroundingPolicy    $grounding Existing M11 grounding policy.
	 * @param PromptBuilder      $prompt Existing M11 prompt builder.
	 * @param CitationValidator  $citations Existing M11 citation validator.
	 */
	public function __construct(
		private ChatRequestPolicy $request_policy,
		private MemoryAssembler $memory,
		private GroundingPolicy $grounding,
		private PromptBuilder $prompt,
		private CitationValidator $citations
	) {
	}

	/**
	 * Compose one production M11 responder without executing retrieval or generation.
	 *
	 * @param HybridRetriever            $retriever Existing M10 hybrid retriever.
	 * @param GenerationProvider         $provider Persisted generation provider.
	 * @param MessageRepository|null     $messages Optional owner-scoped message persistence.
	 * @param ChatAnalyticsHook|null     $analytics Optional non-critical analytics hook.
	 * @param ChatRetrievalObserver|null $observer Optional request-local retrieval observer.
	 */
	public function create(
		HybridRetriever $retriever,
		GenerationProvider $provider,
		?MessageRepository $messages = null,
		?ChatAnalyticsHook $analytics = null,
		?ChatRetrievalObserver $observer = null
	): ChatOrchestrator {
		return new ChatOrchestrator(
			$this->request_policy,
			$this->memory,
			$retriever,
			$this->grounding,
			$this->prompt,
			$provider,
			$this->citations,
			$messages,
			$analytics,
			$observer
		);
	}
}
