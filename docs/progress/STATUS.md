# Global Status

- Completed milestones on `main`: **M00-M12**.
- Latest completed milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 PR: **#17 — MERGED** at merge SHA `206dbfcef42cfcee2a998d7f3c386abdb97425a0`.
- Current milestone: **M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger**.
- Active M13 integration PR: **#18 — OPEN, DRAFT**.
- M13 status: **Tasks 1-5 COMPLETE; Task 6 IN PROGRESS; Tasks 7-8 PENDING**.

This file is the concise recovery index. Detailed RED/GREEN, CI, review, security, accessibility, and implementation history remains in the per-task progress records and the M13 milestone ledger linked below.

## M13 completed work

### Task 1 — knowledge source inventory: COMPLETE

Protected bounded `GET /admin/knowledge/sources` over the existing source repository with explicit allow-listing. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.

### Task 2 — source detail plus bounded document/chunk inspection: COMPLETE

Protected allow-listed source detail plus paginated document/chunk inspection, source/document ownership checks, and 2,000-byte UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.

### Task 3 — recoverable job status and safe lifecycle controls: COMPLETE

Protected bounded job inventory plus M09-backed enqueue/cancel/retry controls with stable invalid-transition behavior and sanitized diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.

### Task 4 — Knowledge manager admin UI: COMPLETE

Bounded server-authoritative Knowledge UI over Tasks 1-3 with source/detail/document/chunk/job inspection, lifecycle actions, safe errors, loading/empty/error states, responsive/keyboard-accessible navigation, and latest-request-wins correlation for selected-resource and source-page requests. Fresh-session closeout review found one Important top-level source-page navigation race and resolved it under genuine RED → GREEN. Final Task 4 state: **0 Critical / 0 Important unresolved**. Evidence: the `docs/progress/M13-TASK4-*` records.

### Task 5 — structured retrieval debug trace projection/redaction: COMPLETE

Administrator-safe `DebugTrace` projection over existing M10 retrieval evidence with raw-query omission, explicit field/channel allow-lists, at most 20 candidates, at most 4 approved channel-evidence rows per candidate, 2,000-byte UTF-8-safe content bounds, and 256-byte UTF-8-safe candidate scalar bounds. Fresh-session closeout resolved the final Important boundedness defect under genuine RED → GREEN. Final Task 5 state: **0 Critical / 0 Important unresolved**. Evidence: `docs/progress/M13-TASK5-DEBUG-TRACE.md`.

## Current work

### Task 6 — Playground REST execution: IN PROGRESS

Completed and exact-head-verified Task 6 composition prerequisites now include:

- `ChatRetrievalObserver` plus request-local `PlaygroundRetrievalCapture`, preserving the exact M10 `RetrievalResult` already used by M11 without duplicate retrieval/scoring/reranking;
- typed `PlaygroundExecutor` / `PlaygroundExecutionResult` contracts and `ProductionPlaygroundExecutor`;
- bounded/sanitized `PlaygroundRestResource` behavior and allow-listed success projection;
- explicit fail-closed persisted bot/provider/model selection through `PlaygroundBotConfigurationResolver`;
- explicit fail-closed persisted knowledge-source/vector-collection selection through `PlaygroundRetrievalConfigurationResolver`;
- closed `PlaygroundConfiguration` aggregate and `PlaygroundConfigurationResolver` over the persisted bot/retrieval selectors;
- `PlaygroundGenerationProviderResolver`, resolving persisted `Bot::provider_id` through `ProviderRegistry::generation()` without fallback or generation work;
- `PlaygroundSemanticConfiguration` plus `PlaygroundSemanticConfigurationResolver`, requiring explicit persisted embedding provider/model/dimensions/normalization, distance metric, and vector-store identity rather than inferring runtime defaults from `collection_id`;
- `PlaygroundVectorCollectionResolver`, reconstructing the canonical production `VectorCollection` from the explicit persisted collection ID plus canonical `VectorIndexProfile`, while leaving persisted fingerprint/dimension compatibility enforcement to the existing production vector-store authority.

Latest vector-collection TDD evidence:

- `49b50c43a158d6327e35dbb9734f66300288a469` is **NOT RED** because the initial test fixture strategy was invalid.
- Genuine RED `ef31aeb2de91547d3192121aea05d96778fa43e5` / CI `34544180850`: PHPStan clean; PHPUnit **718 tests / 3,042 assertions** with exactly one intended error because `PlaygroundVectorCollectionResolver` did not exist. `js-quality`, `package`, and complete `wordpress-smoke` were GREEN.
- GREEN `05db6a3db65a8c4db7a6b8cdf670fdc3c3301a4e` / CI `34559781767`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed; PHPStan **306/306** with no errors; PHPUnit **718 tests / 3,045 assertions**; Composer audit found no security advisories.
- Scoped cold correctness/security/performance review: **0 Critical / 0 Important unresolved**. This is not the final independent Task 6 closeout review.

Durable evidence: `docs/progress/M13-TASK6-PLAYGROUND-VECTOR-COLLECTION.md` plus the earlier Task 6 progress records.

### Exact next Task 6 work

1. Under a fresh genuine RED, resolve persisted `PlaygroundSemanticConfiguration::$vector_store_id` through the existing production `VectorStoreRegistry::search()` authority, failing closed for unknown or non-search-capable identifiers and adding no fallback/default store.
2. Resolve the persisted embedding provider through the existing provider registry and compose the existing `EmbeddingService`, canonical `VectorCollection`, and production vector search implementation into `SemanticRetriever`.
3. Compose the existing semantic + lexical retrieval, grounding, prompt, memory, citation, `PlaygroundRetrievalCapture`, `ChatOrchestrator`, and `ProductionPlaygroundExecutor` components, executing the existing M10/M11 path exactly once.
4. Do not build a parallel Playground retrieval/provider stack and do not accept arbitrary request-level credentials, provider/model overrides, embedding overrides, vector-store options, or retrieval limits.
5. Once production composition is exact-head GREEN, restore the protected `POST /admin/debug/playground` regression and register the route behind `AdminCapability::can_manage`.
6. Bound/validate question plus explicit persisted selector identifiers; return only the already-defined bounded/allow-listed success projection and stable safe errors.
7. Add REST/integration/WordPress smoke coverage and perform the genuinely fresh independent Task 6 correctness/security/performance review before marking Task 6 COMPLETE.

Tasks 7-8 remain pending. Do not start Task 7 until Task 6 is genuinely complete. Do not merge PR #18 until all M13 tasks, final milestone review, exact-final-head CI, and post-merge `main` verification are complete.

## Durable recovery

- `docs/milestones/M13-knowledge-manager-playground-debugger.md` — M13 milestone ledger.
- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md` — auto-approved design.
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md` — auto-approved implementation plan.
- `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md` — Task 1 evidence.
- `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md` — Task 2 evidence.
- `docs/progress/M13-TASK3-JOB-LIFECYCLE.md` — Task 3 evidence.
- `docs/progress/M13-TASK4-*` — Task 4 implementation and closeout evidence.
- `docs/progress/M13-TASK5-DEBUG-TRACE.md` — Task 5 debug trace/redaction/boundedness evidence.
- `docs/progress/M13-TASK6-PLAYGROUND-REST.md` — Task 6 resource/observer continuation evidence.
- `docs/progress/M13-TASK6-PLAYGROUND-BOT-CONFIGURATION.md` — persisted bot selector evidence.
- `docs/progress/M13-TASK6-PLAYGROUND-RETRIEVAL-CONFIGURATION.md` — persisted source/collection selector evidence.
- `docs/progress/M13-TASK6-PLAYGROUND-CONFIGURATION.md` — closed configuration aggregate evidence.
- `docs/progress/M13-TASK6-PLAYGROUND-CONFIGURATION-RESOLVER.md` — explicit selector composition evidence.
- `docs/progress/M13-TASK6-PLAYGROUND-GENERATION-PROVIDER.md` — persisted generation-provider resolution evidence.
- `docs/progress/M13-TASK6-PLAYGROUND-SEMANTIC-CONFIGURATION.md` — persisted embedding/vector-store semantic identity evidence.
- `docs/progress/M13-TASK6-PLAYGROUND-VECTOR-COLLECTION.md` — canonical vector-collection reconstruction evidence and current continuation point.
