# M13 Task 6 — Playground Hybrid Retriever Composition

Status: COMPLETE prerequisite; Task 6 remains IN PROGRESS.

## Scope

Compose the already-existing production M10 semantic and lexical channels into the existing `HybridRetriever` without executing retrieval during request-local Playground composition and without creating a parallel retrieval stack.

## Design

`PlaygroundHybridRetrieverResolver` accepts the request-local production semantic channel plus the existing production lexical channel, reciprocal-rank fusion, confidence estimator, candidate-access policy, bounded retrieval configuration, and optional reranker. `resolve()` only constructs `HybridRetriever`; it performs no semantic retrieval, lexical retrieval, access evaluation, reranking, embedding, vector search, or generation work.

This keeps M10 as the ranking/retrieval authority and prepares the remaining Task 6 chat-orchestrator composition to execute the path exactly once.

## Strict TDD Evidence

The first three test-only checkpoints were correctly rejected as RED evidence because PHP coding standards stopped before PHPUnit reached the intended behavior failure:

- `d046e972d61f7829ace03627219ca46e4f9bb03a` / CI `34608060431`: test docblock/alignment violations.
- `89b0c905f236e8cc6edea452972445fcf70c71e5` / CI `34608176524`: non-void anonymous fixture methods had no return statement.
- `31c51b53151ce6a4e08950b3d2988d3a64cc0966` / CI `34608392144`: fixture properties lacked `@var` tags.
- `295dfdb679adbbaeb4a7f22c42cbdb4041c7b26d` / CI `34608520475`: fixture property comments lacked required short descriptions.

Genuine RED:

- `17d59e5c54f8efcf310b2b014546c5f878228e9b` / CI `34608646882`.
- PHPStan: 309/309 clean.
- PHPUnit: 726 tests / 3,056 assertions with exactly one intended error: missing `WpRagAiChatbot\Admin\Rest\PlaygroundHybridRetrieverResolver`.
- Package and JS jobs passed; the behavior gate was reached rather than being blocked by lint/static analysis.

GREEN:

- Production implementation: `141c382c36e5acbb494652a66e5a91a8c57e8ccd` / CI `34608789998`.
- PHPStan: 310/310 clean.
- PHPUnit: 726/726 tests, 3,060 assertions.
- Composer audit: no vulnerability advisories.
- `js-quality`: GREEN.
- `package`: GREEN.
- Complete `wordpress-smoke`: GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and shutdown.

## Review

Scoped correctness/security/performance review: 0 Critical / 0 Important unresolved.

- Correctness: delegates retrieval behavior to the existing production `HybridRetriever` and preserves its optional reranker path.
- Security: accepts no request-controlled credentials, providers, models, vector-store options, retrieval limits, or filters.
- Performance: composition is O(1) object wiring and performs no retrieval/provider/network work.
- PR #18 currently has no unresolved inline review threads.
- A separate genuinely fresh independent Task 6 closeout review remains mandatory after chat graph, REST registration, and integration coverage are complete.

## Exact Next Work

Under a fresh genuine RED, compose the existing grounding, prompt, memory, citation, `PlaygroundRetrievalCapture`, `ChatOrchestrator`, and `ProductionPlaygroundExecutor` around the verified semantic/hybrid retrieval composition so one Playground execution traverses the existing M10/M11 path exactly once. Route restoration remains a later separate RED/GREEN cycle.
