<?php
/**
 * Provider-neutral non-streaming RAG chat orchestration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

use InvalidArgumentException;
use Throwable;
use WpRagAiChatbot\Citations\CitationRegistry;
use WpRagAiChatbot\Citations\CitationValidator;
use WpRagAiChatbot\Memory\ConversationMemory;
use WpRagAiChatbot\Memory\MemoryAssembler;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\RAG\GroundingMode;
use WpRagAiChatbot\RAG\GroundingPolicy;
use WpRagAiChatbot\RAG\PromptBuilder;
use WpRagAiChatbot\Retrieval\HybridRetriever;
use WpRagAiChatbot\Retrieval\RetrievalException;

/**
 * Coordinates the bounded non-streaming M11 request pipeline around existing M03/M10 contracts.
 */
final class ChatOrchestrator {
	/**
	 * Create one non-streaming orchestrator.
	 *
	 * @param ChatRequestPolicy  $request_policy Deterministic pre-generation request policy.
	 * @param MemoryAssembler    $memory_assembler Owner-scoped bounded memory assembler.
	 * @param HybridRetriever    $retriever Existing M10 retrieval boundary.
	 * @param GroundingPolicy    $grounding_policy Deterministic grounding decision boundary.
	 * @param PromptBuilder      $prompt_builder Bounded prompt/context builder.
	 * @param GenerationProvider $provider Existing M03 provider-neutral generation boundary.
	 * @param CitationValidator  $citation_validator Request-local citation validator.
	 */
	public function __construct(
		private readonly ChatRequestPolicy $request_policy,
		private readonly MemoryAssembler $memory_assembler,
		private readonly HybridRetriever $retriever,
		private readonly GroundingPolicy $grounding_policy,
		private readonly PromptBuilder $prompt_builder,
		private readonly GenerationProvider $provider,
		private readonly CitationValidator $citation_validator
	) {
	}

	/**
	 * Run one bounded non-streaming RAG chat request.
	 *
	 * Trusted owner/retrieval scope is consumed only by memory and retrieval and never passed to PromptBuilder.
	 * Persistence/analytics hooks remain the explicitly deferred Task 9 boundary.
	 *
	 * @param ChatRequest       $request Normalized application request.
	 * @param ChatAccessContext $access Trusted server-side owner and retrieval scope.
	 * @throws ChatException When retrieval, generation, prompt construction, or citation validation fails.
	 */
	public function respond( ChatRequest $request, ChatAccessContext $access ): ChatResult {
		$query  = $this->request_policy->prepare( $request, $this->provider );
		$memory = null === $request->conversation_id
			? new ConversationMemory( array() )
			: $this->memory_assembler->assemble( $request->conversation_id, $access->owner_scope );

		try {
			$retrieval = $this->retriever->retrieve(
				$query,
				$access->semantic_context,
				$access->lexical_filter,
				$access->allow_single_channel_degradation
			);
		} catch ( RetrievalException ) {
			throw new ChatException( ChatFailureReason::RETRIEVAL_UNAVAILABLE, 'Retrieval is unavailable.' );
		}

		$grounding = $this->grounding_policy->decide( $request->grounding_mode, $retrieval->candidates );
		if ( ! $grounding->may_generate ) {
			$no_answer = $grounding->no_answer;
			if ( null === $no_answer ) {
				throw new ChatException( ChatFailureReason::INSUFFICIENT_EVIDENCE, 'Selected evidence is insufficient.' );
			}

			return new ChatResult(
				$no_answer,
				true,
				null,
				array(),
				$request->conversation_id
			);
		}

		try {
			$registry           = CitationRegistry::from_candidates( $retrieval->candidates );
			$generation_request = $this->prompt_builder->build( $request, $memory, $registry );
		} catch ( InvalidArgumentException ) {
			throw new ChatException( ChatFailureReason::INVALID_REQUEST, 'Chat context is invalid or exceeds its bounds.' );
		}

		try {
			$generation = $this->provider->generate( $generation_request );
		} catch ( Throwable ) {
			throw new ChatException( ChatFailureReason::GENERATION_FAILED, 'Generation failed.' );
		}

		if ( GenerationStatus::COMPLETED !== $generation->status ) {
			throw new ChatException( ChatFailureReason::GENERATION_FAILED, 'Generation failed.' );
		}

		$validation = $this->citation_validator->validate( $generation->output_text, $registry );
		if (
			! $validation->valid ||
			( GroundingMode::STRICT === $request->grounding_mode && array() !== $registry->all() && array() === $validation->citations )
		) {
			throw new ChatException( ChatFailureReason::INVALID_CITATIONS, 'Generated citations are invalid.' );
		}

		return new ChatResult(
			$generation->output_text,
			false,
			$generation->usage,
			$validation->citations,
			$request->conversation_id
		);
	}
}
