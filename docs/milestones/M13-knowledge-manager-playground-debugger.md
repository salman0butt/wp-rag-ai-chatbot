# M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger

Status: IN PROGRESS — Tasks 1-7 COMPLETE; Task 8 IN PROGRESS

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

Task 7 remains a projection/rendering layer only. It consumes the Task 6 DTO through the existing administrator client and owns no ranking, scoring, provider, embedding, vector-store, retrieval, generation, or authorization authority.

## Acceptance Criteria
Admin can trace why a source/chunk was selected; secrets/personal data are redacted appropriately; jobs show recoverable status; Playground results correlate with the exact backend retrieval/RAG execution used for the answer; administrator UI states remain safe, bounded, responsive and keyboard accessible.

## Tasks

1. **Knowledge source inventory — COMPLETE.** Protected bounded source inventory over the existing source repository. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.
2. **Source detail plus bounded document/chunk inspection — COMPLETE.** Protected allow-listed source detail and bounded persisted child inspection with source/document correlation and UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.
3. **Recoverable job status and safe lifecycle controls — COMPLETE.** Protected bounded persisted job status plus M09-backed enqueue/cancel/retry controls with stable invalid-transition guards and allow-listed safe diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.
4. **Knowledge manager admin UI — COMPLETE.** Bounded server-authoritative Knowledge UI with safe lifecycle errors, responsive/keyboard-accessible navigation, and latest-request-wins selected-resource/page correlation. Evidence: `docs/progress/M13-TASK4-*`.
5. **Structured debug trace projection and redaction — COMPLETE.** Explicit bounded/redacted `DebugTrace` projection over M10 retrieval evidence with raw-query omission, semantic/lexical channel allow-lists, ≤20 candidates, ≤4 approved evidence records per candidate, 2,000-byte UTF-8-safe content, and 256-byte candidate scalar limits. Evidence: `docs/progress/M13-TASK5-DEBUG-TRACE.md`.
6. **Playground REST execution — COMPLETE.** Protected bounded `POST /admin/debug/playground` reusing the existing production M10/M11 pipeline exactly once, with persisted configuration authority, closed request schema, safe bounded output, exact-retrieval correlation, and real WordPress route smoke. Evidence: `docs/progress/M13-TASK6-*`, especially `M13-TASK6-PLAYGROUND-CLOSEOUT.md`.
7. **Playground UI — COMPLETE.** Bounded Task 6 DTO rendering with labelled submission, structured diagnostics, safe stable-error mapping, polite async status, latest-request/navigation invalidation, responsive long-content behavior and native keyboard-reachable candidate disclosure. Evidence: `docs/progress/M13-TASK7-PLAYGROUND-CONCURRENCY.md` and `docs/progress/M13-TASK7-PLAYGROUND-CLOSEOUT.md`.
8. **M13 integration, smoke, review and closeout — IN PROGRESS.** Recover existing smoke/integration coverage, add only genuinely missing coverage, perform final milestone review, satisfy exact-final-head CI/merge gates, merge, and verify post-merge `main`.

## Task 6 Final Evidence

Important final checkpoints include:

- request-handler genuine RED `3b4bdb1df6de76474e60693abbb97dd28a92672b` / CI `34659505729` and exact-head GREEN `1b85b0185689472bd38035f1757da29b7ef8058d` / CI `34659592284`;
- citation-lineage genuine RED `dee49f4b6bfd12a2d40ba724f9f42b838d2fab8d` / CI `34669687968` and exact-head GREEN `bbf0a03b95cf359a9a5c4ea643bf323ca24008de` / CI `34671321083`;
- WordPress Playground route/smoke exact-head GREEN `0230ef184ef9a7558cb38e6541a5d8e207f21fb5` / CI `34671573305`.

Earlier valid RED/GREEN and invalid NOT RED/NOT GREEN checkpoints remain authoritative in their dedicated progress records and git/CI history.

## Task 7 Final Evidence

Responsive/accessibility closeout preserved the following chronology:

- `50273d9694fc2c4387bd4d3be354c501a907d48b` / CI `34681551478` — **NOT RED** because verification failed before intended Jest evidence;
- `ffe63b8145c0b6ef7647a254359e888e1196abfb` / CI `34681634157` — genuine RED; lint/typecheck passed and only the new responsive Playground tests failed on the missing root hook;
- `afafa56e6defc79800ab19ecd2fa0d60cdd795c7` / CI `34681766674` — genuine GREEN, all permanent jobs GREEN;
- `1a89a2a62ef6be685080ddf8c32327f0a587ef09` / CI `34681912450` — responsive CSS hardening, all permanent jobs GREEN;
- `5d5f734aa89a0a837c1db95a73dd34d74ad2f282` / CI `34682140441` — long-content/native-disclosure verification, all permanent jobs GREEN.

Earlier Task 7 rendering/form/runtime and concurrency evidence remains authoritative in git/CI and `docs/progress/M13-TASK7-PLAYGROUND-CONCURRENCY.md`.

## Integration Test Evidence

- Tasks 1-3 correlate with persisted source/document/chunk/job repositories.
- Task 4 browser tests cover bounded REST loading, server-authoritative navigation/mutation refresh, safe errors, responsive long content, keyboard semantics, and stale concurrent navigation.
- Task 5 correlates directly with M10 retrieval evidence fixtures; no duplicate retrieval pipeline is introduced.
- Task 6 integration proves the real Playground graph executes semantic + lexical retrieval once and projects Task 5 diagnostics from that same retrieval execution. Real WordPress smoke proves route registration, administrator capability enforcement, bounded request parsing, and rejection of request-level provider/runtime overrides without requiring live AI credentials.
- Task 7 Jest coverage proves bounded DTO rendering, safe error mapping, live status, request/navigation concurrency, responsive root semantics, long-content preservation and native disclosure structure.
- Final milestone integration/closeout remains Task 8.

## Security Review

Tasks 1-7 have 0 unresolved Critical/Important findings after their documented scoped reviews and strict-TDD fixes. Task 6 preserves persisted runtime authority and Task 7 does not broaden the request/response surface or render arbitrary backend/provider error messages.

Independent reviewer/subagent transport was unavailable during Task 6/7 final closeout and is not falsely claimed; the repository-approved fallback review process was used and documented.

## Accessibility Review where UI exists

Task 4’s responsive/keyboard-accessible Knowledge UI is complete. Task 7 adds labelled Playground controls, live polite loading status, alert semantics for errors, native keyboard-reachable `details`/`summary` candidate disclosures, long-content wrapping and 44px mobile action/disclosure targets. No unresolved Critical/Important accessibility finding is known. Final M13 accessibility review remains part of Task 8.

## Performance Review where relevant

Tasks 1-5 preserve bounded page/projection limits. Task 6 uses one production retrieval/chat execution and bounded diagnostic/citation projection. Task 7 adds only bounded browser rendering, O(1) request-generation guards and CSS/layout rules; it introduces no duplicate retrieval, generation or polling.

## Current Task 8 Continuation

1. Recover existing M13 WordPress smoke/integration coverage before writing anything; do not duplicate capability/route tests already delivered in Tasks 1-7.
2. If a real Task 8 coverage gap exists, specify the smallest genuine RED and implement only that missing integration/smoke behavior.
3. Perform final M13 correctness/security/performance/accessibility/architecture review; resolve every Critical/Important finding under strict TDD.
4. Reconcile milestone/status/PR closeout documentation.
5. Require exact-final-head `php-quality`, `js-quality`, `package`, and `wordpress-smoke` GREEN.
6. Merge only when PR #18 is mergeable, no conflicting/newer work exists, acceptance criteria are satisfied, and all merge gates are green.
7. Recover the merged `main` SHA and require fresh post-merge default-branch CI before marking M13 complete.
8. Once M13 is genuinely complete, recover M14 and continue autonomously if repository instructions permit it.

## Known Limitations

Task 8, final milestone review, exact-final-head CI, merge, and post-merge `main` verification remain mandatory. M13 is not yet complete merely because Task 7 is complete.

## Completion Checklist

Incomplete. Tasks 1-7 are complete. Task 8 is in progress. Final milestone review, exact-final-head CI, merge, and post-merge main verification remain mandatory.

## Next Milestone
M14 — Frontend Chatbot/Customizer, only after genuine M13 completion.
