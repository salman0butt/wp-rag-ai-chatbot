# M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger

Status: IN PROGRESS — Tasks 1-6 COMPLETE; Task 7 IN PROGRESS; Task 8 PENDING

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

The Task 6 Playground executes the existing production M10/M11 path once. It does not perform a separate diagnostic retrieval. Request-local composition resolves persisted bot/retrieval/semantic configuration through the existing provider/vector-store registries and composes existing semantic + lexical retrieval, fusion/confidence/access controls, grounding, prompt, memory, citation and chat orchestration. `PlaygroundRetrievalCapture` observes the exact M10 `RetrievalResult` consumed by M11 for Task 5 projection.

## Acceptance Criteria
Admin can trace why a source/chunk was selected; secrets/personal data are redacted appropriately; jobs show recoverable status; Playground results correlate with the exact backend retrieval/RAG execution used for the answer.

## Tasks

1. **Knowledge source inventory — COMPLETE.** Protected bounded source inventory over the existing source repository. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.
2. **Source detail plus bounded document/chunk inspection — COMPLETE.** Protected allow-listed source detail and bounded persisted child inspection with source/document correlation and UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.
3. **Recoverable job status and safe lifecycle controls — COMPLETE.** Protected bounded persisted job status plus M09-backed enqueue/cancel/retry controls with stable invalid-transition guards and allow-listed safe diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.
4. **Knowledge manager admin UI — COMPLETE.** Bounded server-authoritative Knowledge UI with safe lifecycle errors, responsive/keyboard-accessible navigation, and latest-request-wins selected-resource/page correlation. Evidence: `docs/progress/M13-TASK4-*`.
5. **Structured debug trace projection and redaction — COMPLETE.** Explicit bounded/redacted `DebugTrace` projection over M10 retrieval evidence with raw-query omission, semantic/lexical channel allow-lists, ≤20 candidates, ≤4 approved evidence records per candidate, 2,000-byte UTF-8-safe content, and 256-byte candidate scalar limits. Evidence: `docs/progress/M13-TASK5-DEBUG-TRACE.md`.
6. **Playground REST execution — COMPLETE.** Protected bounded `POST /admin/debug/playground` reusing the existing production M10/M11 pipeline exactly once, with persisted configuration authority, closed request schema, stable safe failures, bounded/redacted success projection, exact-retrieval correlation, and real WordPress route smoke. Evidence: `docs/progress/M13-TASK6-*`, especially `M13-TASK6-PLAYGROUND-CLOSEOUT.md`.
7. **Playground UI — IN PROGRESS.** Consume only the Task 6 trace DTO; render structured candidate/score/filter/rerank/context/answer/citation/model/latency/usage/error sections with safe async/accessibility behavior.
8. **M13 integration, smoke, review and closeout — PENDING.**

## Task 6 Final Evidence

Task 6 was delivered through strict-TDD prerequisites recorded under `docs/progress/M13-TASK6-*`. Important final checkpoints include:

- request-handler genuine RED `3b4bdb1df6de76474e60693abbb97dd28a92672b` / CI `34659505729` and exact-head GREEN `1b85b0185689472bd38035f1757da29b7ef8058d` / CI `34659592284`;
- citation-lineage genuine RED `dee49f4b6bfd12a2d40ba724f9f42b838d2fab8d` / CI `34669687968` and exact-head GREEN `bbf0a03b95cf359a9a5c4ea643bf323ca24008de` / CI `34671321083`;
- WordPress Playground route/smoke exact-head GREEN `0230ef184ef9a7558cb38e6541a5d8e207f21fb5` / CI `34671573305`, with `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all successful.

Earlier valid RED/GREEN and invalid NOT RED/NOT GREEN checkpoints remain authoritative in their dedicated progress records and git/CI history.

## Integration Test Evidence

- Tasks 1-3 correlate with persisted source/document/chunk/job repositories.
- Task 4 browser tests cover bounded REST loading, server-authoritative navigation/mutation refresh, safe errors, responsive long content, keyboard semantics, and stale concurrent navigation.
- Task 5 correlates directly with M10 `RetrievalResult`, `RetrievalTrace`, `RetrievalCandidate`, and `ChannelEvidence` fixtures; no duplicate retrieval pipeline is introduced.
- Task 6 integration proves the real Playground graph executes semantic + lexical retrieval once and projects Task 5 diagnostics from that same retrieval execution. Real WordPress smoke proves route registration, administrator capability enforcement, bounded request parsing, and rejection of request-level provider/runtime overrides without requiring live AI credentials.
- Full milestone integration remains pending Tasks 7-8.

## Security Review

Tasks 1-6 have 0 unresolved Critical/Important findings after their documented fresh-session reviews and strict-TDD fixes. Task 6 accepts only persisted selector identifiers plus one bounded question, rejects unknown request fields, exposes no credential/provider exception body, resolves provider/embedding/vector-store identity from persisted production authority, and preserves Task 5 redaction/bounds. Citation lineage is projection-bounded to 256 UTF-8-safe bytes.

Independent reviewer/subagent transport was not exposed during the final Task 6 closeout runtime; the repository-approved fresh fallback correctness/security/performance/architecture review was used and this limitation is recorded in `docs/progress/M13-TASK6-PLAYGROUND-CLOSEOUT.md`.

## Accessibility Review where UI exists
Task 4’s responsive/keyboard-accessible admin UI is complete with no unresolved accessibility issue. Tasks 5-6 introduce no new UI. Task 7 and final M13 closeout require another accessibility pass.

## Performance Review where relevant
Tasks 1-5 preserve their documented bounded page/projection limits. Task 6 uses one production retrieval/chat execution, request-local O(1) composition, bounded diagnostic/citation projection, and no second diagnostic retrieval. Task 7 must preserve server-authoritative data and avoid unbounded browser rendering.

## Current Task 7 Continuation

1. Write the smallest Jest/UI RED for rendering one representative bounded Task 6 trace fixture and stable safe error code.
2. Consume only the Task 6 DTO through the existing nonce-authenticated M12 admin client; do not render arbitrary backend/provider messages.
3. Implement semantic sections/disclosures for candidates/scores/filter/rerank/context/answer/citations/model/latency/usage plus labelled question submission and live async state.
4. Verify long URLs/chunks, keyboard flow, mobile layout, loading/empty/error states, and latest-request correctness where concurrency applies.
5. Perform scoped correctness/security/performance/accessibility review, resolve all Critical/Important findings under strict RED → GREEN, and obtain exact-head GREEN before Task 7 completion.

## Known Limitations
Tasks 7-8 remain unfinished. M13 is not merge-ready. Final milestone review, exact-final-head CI, merge, and post-merge `main` verification remain mandatory.

## Documentation Updated
Task 1-5 evidence remains under `docs/progress/`. Task 6 durable evidence is under `docs/progress/M13-TASK6-*`; final Task 6 evidence is summarized in `M13-TASK6-PLAYGROUND-CLOSEOUT.md` and `M13-TASK6-PLAYGROUND-WP-SMOKE.md`.

## Completion Checklist
Incomplete. Tasks 1-6 are complete. Task 7 is in progress. Task 8, final milestone review, exact-final-head CI, merge, and post-merge main verification remain mandatory.

## Next Milestone
M14 — Frontend Chatbot/Customizer, only after genuine M13 completion.
