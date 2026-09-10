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

The Task 6 playground must execute the existing M10/M11 path once. It must not perform a separate retrieval solely for diagnostics because that could diverge from the retrieval evidence actually used for grounding/generation. A request-local `ChatRetrievalObserver` now exposes the exact `RetrievalResult` already produced inside `ChatOrchestrator` without changing existing callers.

## Acceptance Criteria
Admin can trace why a source/chunk was selected; secrets/personal data are redacted appropriately; jobs show recoverable status; playground results correlate with the exact backend retrieval/RAG execution used for the answer.

## Tasks

1. **Knowledge source inventory — COMPLETE.** Protected bounded source inventory over the existing source repository. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.
2. **Source detail plus bounded document/chunk inspection — COMPLETE.** Protected allow-listed source detail and bounded persisted child inspection with source/document correlation and UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.
3. **Recoverable job status and safe lifecycle controls — COMPLETE.** Protected bounded persisted job status plus M09-backed enqueue/cancel/retry controls with stable invalid-transition guards and allow-listed safe diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.
4. **Knowledge manager admin UI — COMPLETE.** Bounded server-authoritative Knowledge UI with safe lifecycle errors, responsive/keyboard-accessible navigation, and latest-request-wins selected-resource/page correlation. Fresh-session closeout resolved one Important page-navigation race under genuine RED → GREEN. Evidence: `docs/progress/M13-TASK4-*`.
5. **Structured debug trace projection and redaction — COMPLETE.** Explicit bounded/redacted `DebugTrace` projection over M10 retrieval evidence with raw-query omission, semantic/lexical channel allow-lists, ≤20 candidates, ≤4 approved evidence records per candidate, 2,000-byte UTF-8-safe content, and 256-byte candidate scalar limits. Fresh-session closeout resolved the final Important scalar-bound defect. Evidence: `docs/progress/M13-TASK5-DEBUG-TRACE.md`.
6. **Playground REST execution — IN PROGRESS.** Exact-retrieval observation seam is complete and exact-head verified; production composition/resource/REST registration and closeout remain. Evidence: `docs/progress/M13-TASK6-PLAYGROUND-REST.md`.
7. **Playground UI — PENDING.**
8. **M13 integration, smoke, review and closeout — PENDING.**

## Task 6 TDD Evidence

- Test-preparation commit `3b280d7c1893db68cccdd6f567807ab6366e49f2` / CI `34442472735` stopped at test-file PHPCS and is explicitly **not RED**.
- Genuine RED `2a598018070a37f83e8d254c5e6918774a0bb6a2` / CI `34442552312`: PHPStan clean; PHPUnit 693 tests / 2,930 assertions with exactly two failures proving the retrieval-observer contract/constructor seam was absent.
- Intermediate implementation checks `d5a7c80f67c364d3d56aae80f57fbcc3d5e43744`, `9e331386e1d9fb723cf926f8e9c513456144afd1`, and `1f2ca6541520a1ce06d566f570aa34f4e1c3a4bc` were convention/static-analysis failures and are explicitly **not GREEN**.
- `25e35b4a565e5e7d3e19d50e4f6ffdd91a8092ca` adds `ChatRetrievalObserver`.
- GREEN production head `060fd76f9b97a587f612edb56dc4bf61832f3751` invokes the optional observer once immediately after successful production retrieval with the exact `RetrievalResult` used downstream. CI `34443128752` passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.

Earlier Task 1-5 RED/GREEN history remains authoritative in their per-task progress records and git/CI history.

## Integration Test Evidence

- Tasks 1-3 correlate with persisted source/document/chunk/job repositories.
- Task 4 browser tests cover bounded REST loading, server-authoritative navigation/mutation refresh, safe errors, responsive long content, keyboard semantics, and stale concurrent navigation.
- Task 5 correlates directly with M10 `RetrievalResult`, `RetrievalTrace`, `RetrievalCandidate`, and `ChannelEvidence` fixtures; no duplicate retrieval pipeline is introduced.
- Task 6 now has the exact-retrieval observation seam needed to preserve that same correlation through one M11 execution. Full REST integration remains pending.
- Full milestone integration remains pending Tasks 6-8.

## Security Review

Tasks 1-5 have 0 unresolved Critical/Important findings after their documented fresh-session reviews and fixes. Task 6’s completed observer subunit adds no serialization boundary, raw provider payload, credential, query, or upstream exception exposure. The observer is optional/request-local and consumes only the existing M10 `RetrievalResult`; REST output must still pass through Task 5 `DebugTraceProjector`. A genuinely fresh independent Task 6 correctness/security/performance review remains mandatory before Task 6 completion.

## Accessibility Review where UI exists
Task 4’s responsive/keyboard-accessible admin UI is complete with no unresolved accessibility issue. Task 5 and the completed Task 6 observer subunit introduce no UI. Task 7 and final M13 closeout require another accessibility pass.

## Performance Review where relevant
Tasks 1-5 preserve their documented bounded page/projection limits. The Task 6 observer adds constant-count observation of the already-produced retrieval result and does not trigger a second retrieval/scoring/reranking pass. The future playground REST response must preserve Task 5’s hard DTO bounds.

## Current Task 6 Continuation

1. Recover the concrete M03/M10/M11 provider, retrieval, memory, grounding, prompt, and citation dependency factories/registries.
2. Add the smallest request-local production chat composition/executor seam that accepts `ChatRetrievalObserver`; do not create a parallel provider/retrieval stack.
3. Under a new genuine RED, add protected `POST /admin/debug/playground` behind `AdminCapability::can_manage`.
4. Bound/validate the test question and explicit existing bot/retrieval configuration identifiers.
5. Execute one M11 request, capture its exact M10 result via the observer, project with Task 5 `DebugTraceProjector`, and return only bounded/allow-listed result fields.
6. Map internal/provider failures to stable repository-owned safe error codes without serializing upstream messages.
7. Add REST/integration/WordPress smoke coverage.
8. Perform fresh independent Task 6 review, resolve all Critical/Important findings under RED → GREEN, and require exact-final-head four-job GREEN before Task 6 COMPLETE or Task 7 starts.

## Fresh Verification Results

Task 6 observer production head `060fd76f9b97a587f612edb56dc4bf61832f3751`, CI `34443128752`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all GREEN.

## Known Limitations
Task 6 remains unfinished at its production composition/REST boundary. Tasks 7-8 remain unfinished. M13 is not merge-ready.

## Documentation Updated
Task 1-5 evidence remains under `docs/progress/`. Task 6 observer seam and exact continuation state are recorded in `docs/progress/M13-TASK6-PLAYGROUND-REST.md` and `docs/progress/STATUS.md`.

## Completion Checklist
Incomplete. Tasks 1-5 are complete. Task 6 is in progress. Tasks 7-8, final milestone review, exact-final-head CI, merge, and post-merge main verification remain mandatory.

## Next Milestone
M14 — Frontend Chatbot/Customizer, only after genuine M13 completion.
