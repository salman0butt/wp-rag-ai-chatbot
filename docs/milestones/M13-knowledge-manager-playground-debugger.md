# M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger

Status: IN PROGRESS — Tasks 1-5 COMPLETE; Task 6 IN PROGRESS

## Goal
Expose knowledge/source/indexing operations and a deep retrieval/RAG diagnostic playground to administrators.

## Dependencies
M04-M11, M12 admin shell.

## In Scope
Sources/documents/chunks/index status; enqueue/cancel/retry where safe; progress/errors; test question playground; semantic/lexical candidates; raw/normalized/hybrid scores; filters/rerank; selected chunks; context estimate; models; answer/citations; latency/usage/cost/errors.

## Out of Scope
Full eval regression suite M21.

## Architecture
Debug traces are structured domain data with redaction, not arbitrary raw secret dumps. Administrator resources reuse existing persistence/retrieval/job contracts and remain capability protected, bounded, and allow-listed.

The Task 6 playground must execute the existing M10/M11 path once. It must not perform a separate retrieval solely for diagnostics because that could diverge from the retrieval evidence actually used for grounding/generation. Request-local Playground composition now has verified seams for observing the exact M10 `RetrievalResult`, resolving persisted bot/retrieval/semantic configuration, resolving generation and embedding provider capabilities, reconstructing the canonical vector collection, resolving the configured searchable vector store without fallbacks, and composing those semantic dependencies into the existing production `EmbeddingService` and `SemanticRetriever` without executing provider or search work.

## Acceptance Criteria
Admin can trace why a source/chunk was selected; secrets/personal data are redacted appropriately; jobs show recoverable status; playground results correlate with the exact backend retrieval/RAG execution used for the answer.

## Tasks

1. **Knowledge source inventory — COMPLETE.** Protected bounded source inventory over the existing source repository. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.
2. **Source detail plus bounded document/chunk inspection — COMPLETE.** Protected allow-listed source detail and bounded persisted child inspection with source/document correlation and UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.
3. **Recoverable job status and safe lifecycle controls — COMPLETE.** Protected bounded persisted job status plus M09-backed enqueue/cancel/retry controls with stable invalid-transition guards and allow-listed safe diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.
4. **Knowledge manager admin UI — COMPLETE.** Bounded server-authoritative Knowledge UI with safe lifecycle errors, responsive/keyboard-accessible navigation, and latest-request-wins selected-resource/page correlation. Fresh-session closeout resolved one Important page-navigation race under genuine RED → GREEN. Evidence: `docs/progress/M13-TASK4-*`.
5. **Structured debug trace projection and redaction — COMPLETE.** Explicit bounded/redacted `DebugTrace` projection over M10 retrieval evidence with raw-query omission, semantic/lexical channel allow-lists, ≤20 candidates, ≤4 approved evidence records per candidate, 2,000-byte UTF-8-safe content, and 256-byte candidate scalar limits. Fresh-session closeout resolved the final Important scalar-bound defect. Evidence: `docs/progress/M13-TASK5-DEBUG-TRACE.md`.
6. **Playground REST execution — IN PROGRESS.** Exact-retrieval observation, persisted configuration/provider/vector resolution, production semantic-retriever composition, resource/executor contracts, and bounded response behavior are implemented and verified. Production hybrid/chat graph composition, route restoration, integration smoke, and final Task 6 closeout remain. Evidence: `docs/progress/M13-TASK6-*`.
7. **Playground UI — PENDING.**
8. **M13 integration, smoke, review and closeout — PENDING.**

## Task 6 TDD Evidence

Task 6 has progressed through multiple bounded strict-TDD prerequisites. The authoritative per-unit RED/GREEN records are under `docs/progress/M13-TASK6-*`.

Latest completed prerequisite — production semantic-retriever composition:

- `35c4ad7b0eadf2a0b35d48e6cfe28218158737c0` is **NOT RED** because PHP verification stopped at test-file coding-standard issues before PHPUnit reached the intended missing-contract failure.
- Genuine RED `7690eaef19980e676e978dc80019c3dd52fa2367` / CI `34599924148`: PHPStan clean; PHPUnit ran 725 tests / 3,055 assertions with exactly one intended missing-class error for `PlaygroundSemanticRetrieverResolver`; `js-quality` and `package` passed.
- Initial implementation `4e9c2ef063824ced215c95a670699b7aef803942` is **NOT GREEN** because PHPCS stopped on three production docblock-alignment violations before PHP tests validated the implementation; its `js-quality`, `package`, and complete `wordpress-smoke` jobs passed.
- Genuine GREEN `079fe4061f34aa4e0f36fbd542c47a5e4e4d9c86` / CI `34600110031`: PHPStan 309/309 clean; PHPUnit 725/725 tests / 3,056 assertions; Composer audit clean; `js-quality`, `package`, and complete `wordpress-smoke` all passed.

Earlier Task 1-5 and Task 6 RED/GREEN history remains authoritative in the per-task progress records and git/CI history.

## Integration Test Evidence

- Tasks 1-3 correlate with persisted source/document/chunk/job repositories.
- Task 4 browser tests cover bounded REST loading, server-authoritative navigation/mutation refresh, safe errors, responsive long content, keyboard semantics, and stale concurrent navigation.
- Task 5 correlates directly with M10 `RetrievalResult`, `RetrievalTrace`, `RetrievalCandidate`, and `ChannelEvidence` fixtures; no duplicate retrieval pipeline is introduced.
- Task 6 now has exact-retrieval observation plus persisted bot/retrieval/semantic identity, fail-closed production registry resolution for generation/embedding/vector search, canonical collection reconstruction, and production `SemanticRetriever` composition. Full hybrid/chat graph composition and REST integration remain pending.
- Full milestone integration remains pending Tasks 6-8.

## Security Review

Tasks 1-5 have 0 unresolved Critical/Important findings after their documented fresh-session reviews and fixes. Completed Task 6 prerequisite reviews also have 0 unresolved Critical/Important findings. The latest semantic-retriever composer accepts only already-resolved persisted runtime identity plus explicit bounded production configuration, adds no request-level credentials/provider/model/embedding/vector-store/retrieval overrides, performs no provider/network/embedding/search work during composition, and delegates fail-closed provider/store/profile compatibility to existing production authorities. The scoped review found 0 Critical / 0 Important unresolved. A genuinely fresh independent Task 6 correctness/security/performance review remains mandatory after chat-graph/REST/integration completion before Task 6 can close.

## Accessibility Review where UI exists
Task 4’s responsive/keyboard-accessible admin UI is complete with no unresolved accessibility issue. Task 5 and completed Task 6 composition prerequisites introduce no UI. Task 7 and final M13 closeout require another accessibility pass.

## Performance Review where relevant
Tasks 1-5 preserve their documented bounded page/projection limits. Completed Task 6 resolution/composition seams are O(1) request-local wiring and do not trigger duplicate retrieval, generation, vector search, embedding, or reranking work. The future playground REST response must preserve Task 5’s hard DTO bounds.

## Current Task 6 Continuation

1. Under a fresh genuine RED, compose the existing semantic + lexical retrieval, fusion/reranking, grounding, prompt, memory, citation, `PlaygroundRetrievalCapture`, `ChatOrchestrator`, and `ProductionPlaygroundExecutor` graph exactly once; do not create a parallel provider/retrieval stack.
2. Preserve persisted configuration authority and do not accept request-level credentials/provider/model/embedding/vector-store/retrieval overrides.
3. Under a new genuine RED only after production chat composition is exact-head GREEN, restore protected `POST /admin/debug/playground` behind `AdminCapability::can_manage`.
4. Bound/validate the test question and explicit existing persisted selector identifiers.
5. Execute one M11 request, capture its exact M10 result via the observer, project with Task 5 `DebugTraceProjector`, and return only bounded/allow-listed result fields.
6. Map internal/provider failures to stable repository-owned safe error codes without serializing upstream messages.
7. Add REST/integration/WordPress smoke coverage.
8. Perform fresh independent Task 6 review, resolve all Critical/Important findings under RED → GREEN, and require exact-final-head four-job GREEN before Task 6 COMPLETE or Task 7 starts.

## Fresh Verification Results

Latest Task 6 production prerequisite GREEN: semantic-retriever composition production SHA `079fe4061f34aa4e0f36fbd542c47a5e4e4d9c86`, CI `34600110031`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all GREEN. PHPStan reported 0 errors over 309 files, PHPUnit passed 725 tests / 3,056 assertions, and Composer audit reported no vulnerability advisories.

## Known Limitations
Task 6 remains unfinished at hybrid/chat graph composition and REST registration/integration. Tasks 7-8 remain unfinished. M13 is not merge-ready.

## Documentation Updated
Task 1-5 evidence remains under `docs/progress/`. Task 6 durable evidence is recorded in the `docs/progress/M13-TASK6-*` records and `docs/progress/STATUS.md`; the latest continuation point is `docs/progress/M13-TASK6-PLAYGROUND-SEMANTIC-RETRIEVER.md`.

## Completion Checklist
Incomplete. Tasks 1-5 are complete. Task 6 is in progress. Tasks 7-8, final milestone review, exact-final-head CI, merge, and post-merge main verification remain mandatory.

## Next Milestone
M14 — Frontend Chatbot/Customizer, only after genuine M13 completion.
