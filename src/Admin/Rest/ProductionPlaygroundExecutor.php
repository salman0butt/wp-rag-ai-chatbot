<?php
/**
 * Request-local production Playground execution.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use LogicException;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ChatOrchestrator;
use WpRagAiChatbot\Chat\ChatRequest;
use WpRagAiChatbot\Debug\DebugTraceProjector;
use WpRagAiChatbot\RAG\GroundingMode;

/**
 * Executes one administrator question through the existing M11 pipeline and projects its exact M10 retrieval result.
 */
final class ProductionPlaygroundExecutor implements PlaygroundExecutor {
	/**
	 * Create one request-local production executor.
	 *
	 * @param ChatOrchestrator           $orchestrator Existing M11 production pipeline.
	 * @param ChatAccessContext          $access Trusted server-side retrieval scope.
	 * @param PlaygroundRetrievalCapture $capture Request-local exact retrieval observer.
	 * @param DebugTraceProjector        $projector Task 5 bounded/redacted retrieval projector.
	 * @param string                     $model_id Selected generation model identifier.
	 * @param GroundingMode              $grounding_mode Selected M11 grounding policy.
	 */
	public function __construct(
		private readonly ChatOrchestrator $orchestrator,
		private readonly ChatAccessContext $access,
		private readonly PlaygroundRetrievalCapture $capture,
		private readonly DebugTraceProjector $projector, // @phpstan-ignore property.onlyWritten -- The read is after an observer callback PHPStan cannot correlate across the injected orchestrator.
		private readonly string $model_id,
		private readonly GroundingMode $grounding_mode
	) {
	}

	/**
	 * Execute one bounded question through M11 exactly once.
	 *
	 * The request-local orchestrator is composed with this same capture instance. PHPStan cannot
	 * correlate the observer callback performed inside the injected final orchestrator with the
	 * subsequent capture read here, so the two flow-analysis suppressions below are intentionally
	 * limited to that cross-object callback boundary.
	 *
	 * @param string $question Validated administrator question.
	 * @throws LogicException When this request-local executor is reused or retrieval was not observed.
	 */
	public function execute( string $question ): PlaygroundExecutionResult {
		if ( null !== $this->capture->result() ) {
			throw new LogicException( 'Playground executor is request-local and cannot be reused.' );
		}

		$started_at = hrtime( true );
		$chat       = $this->orchestrator->respond(
			new ChatRequest( $question, $this->model_id, $this->grounding_mode ),
			$this->access
		);
		$retrieval  = $this->capture->result();

		if ( null === $retrieval ) { // @phpstan-ignore identical.alwaysTrue -- The injected orchestrator observes into this request-local capture.
			throw new LogicException( 'Playground retrieval observation was not produced.' );
		}

		// @phpstan-ignore-next-line -- Reachable when the injected orchestrator has invoked the request-local observer.
		$latency_ms = (int) floor( ( hrtime( true ) - $started_at ) / 1_000_000 );

		return new PlaygroundExecutionResult(
			$chat,
			$this->projector->projectRetrieval( $retrieval ),
			$this->model_id,
			max( 0, $latency_ms )
		);
	}
}
