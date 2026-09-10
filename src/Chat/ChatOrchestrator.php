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
use WpRagAiChatbot\Conversations\ConversationMessage;
use WpRagAiChatbot\Conversations\MessageRepository;
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
	 * @param ChatRequestPolicy          $request_policy Deterministic pre-generation request policy.
	 * @param MemoryAssembler            $memory_assembler Owner-scoped bounded memory assembler.
	 * @param HybridRetriever            $retriever Existing M10 retrieval boundary.
	 * @param GroundingPolicy            $grounding_policy Deterministic grounding decision boundary.
	 * @param PromptBuilder              $prompt_builder Bounded prompt/context builder.
	 * @param GenerationProvider         $provider Existing M03 provider-neutral generation boundary.
	 * @param CitationValidator          $citation_validator Request-local citation validator.
	 * @param MessageRepository|null     $message_repository Optional owner-scoped assistant-message persistence boundary.
	 * @param ChatAnalyticsHook|null     $analytics_hook Optional non-critical text-free analytics boundary.
	 * @param ChatRetrievalObserver|null $retrieval_observer Optional request-local retrieval observation boundary.
	 */
	public function __construct(
		private readonly ChatRequestPolicy $request_policy,
		private readonly MemoryAssembler $memory_assembler,
		private readonly HybridRetriever $retriever,
		private readonly GroundingPolicy $grounding_policy,
		private readonly PromptBuilder $prompt_builder,
		private readonly GenerationProvider $provider,
		private readonly CitationValidator $citation_validator,
		private readonly ?MessageRepository $message_repository = null,
		private readonly ?ChatAnalyticsHook $analytics_hook = null,
		private readonly ?ChatRetrievalObserver $retrieval_observer = null
	) {
	}

	/**
	 * Run one bounded non-streaming RAG chat request.
	 *
	 * Trusted owner/retrieval scope is consumed only by memory, retrieval, and scoped persistence and is never passed to PromptBuilder.
	 * Validated assistant output is persisted only when a conversation and persistence boundary are present.
	 *
	 * @param ChatRequest       $request Normalized application request.
	 * @param ChatAccessContext $access Trusted server-side owner and retrieval scope.
	 * @throws ChatException When retrieval, generation, prompt construction, citation validation, or persistence fails.
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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Application exception reason enum is not rendered output.
			throw new ChatException( ChatFailureReason::RETRIEVAL_UNAVAILABLE, 'Retrieval is unavailable.' );
		}

		$grounding = $this->grounding_policy->decide( $request->grounding_mode, $retrieval->candidates );
		if ( ! $grounding->may_generate ) {
			$no_answer = $grounding->no_answer;
			if ( null === $no_answer ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Application exception reason enum is not rendered output.
				throw new ChatException( ChatFailureReason::INSUFFICIENT_EVIDENCE, 'Selected evidence is insufficient.' );
			}

			$this->record_analytics(
				true,
				$request->model_id,
				null,
				count( $retrieval->candidates ),
				0
			);

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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Application exception reason enum is not rendered output.
			throw new ChatException( ChatFailureReason::INVALID_REQUEST, 'Chat context is invalid or exceeds its bounds.' );
		}

		try {
			$generation = $this->provider->generate( $generation_request );
		} catch ( Throwable ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Application exception reason enum is not rendered output.
			throw new ChatException( ChatFailureReason::GENERATION_FAILED, 'Generation failed.' );
		}

		if ( GenerationStatus::COMPLETED !== $generation->status ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Application exception reason enum is not rendered output.
			throw new ChatException( ChatFailureReason::GENERATION_FAILED, 'Generation failed.' );
		}

		$validation = $this->citation_validator->validate( $generation->output_text, $registry );
		if (
			! $validation->valid ||
			( GroundingMode::STRICT === $request->grounding_mode && array() !== $registry->all() && array() === $validation->citations )
		) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Application exception reason enum is not rendered output.
			throw new ChatException( ChatFailureReason::INVALID_CITATIONS, 'Generated citations are invalid.' );
		}

		if ( null !== $request->conversation_id && null !== $this->message_repository ) {
			try {
				$this->message_repository->append_for_owner(
					$request->conversation_id,
					$access->owner_scope,
					new ConversationMessage( 'assistant', $generation->output_text )
				);
			} catch ( Throwable ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Application exception reason enum is not rendered output.
				throw new ChatException( ChatFailureReason::PERSISTENCE_FAILED, 'Conversation persistence failed.' );
			}
		}

		$this->record_analytics(
			false,
			$request->model_id,
			$generation->provider_id,
			count( $retrieval->candidates ),
			count( $validation->citations ),
			$generation->usage->input_tokens,
			$generation->usage->output_tokens,
			$generation->usage->total_tokens
		);

		return new ChatResult(
			$generation->output_text,
			false,
			$generation->usage,
			$validation->citations,
			$request->conversation_id
		);
	}

	/**
	 * Read the optional retrieval observer dependency without executing it.
	 */
	private function retrieval_observer(): ?ChatRetrievalObserver {
		return $this->retrieval_observer;
	}

	/**
	 * Construct and emit one non-critical sanitized analytics event when configured.
	 *
	 * @param bool        $no_answer Whether deterministic no-answer was returned.
	 * @param string      $model_id Requested model identifier.
	 * @param string|null $provider_id Provider identifier when generation occurred.
	 * @param int         $retrieval_candidate_count Selected retrieval candidate count.
	 * @param int         $citation_count Validated citation count.
	 * @param int|null    $input_tokens Input tokens when known.
	 * @param int|null    $output_tokens Output tokens when known.
	 * @param int|null    $total_tokens Total tokens when known.
	 */
	private function record_analytics(
		bool $no_answer,
		string $model_id,
		?string $provider_id,
		int $retrieval_candidate_count,
		int $citation_count,
		?int $input_tokens = null,
		?int $output_tokens = null,
		?int $total_tokens = null
	): void {
		if ( null === $this->analytics_hook ) {
			return;
		}

		try {
			$this->analytics_hook->record(
				new ChatAnalyticsEvent(
					$no_answer,
					$model_id,
					$provider_id,
					$retrieval_candidate_count,
					$citation_count,
					$input_tokens,
					$output_tokens,
					$total_tokens
				)
			);
		} catch ( Throwable ) {
			// Analytics is explicitly non-critical and must never affect a valid chat result.
			return;
		}
	}
}
