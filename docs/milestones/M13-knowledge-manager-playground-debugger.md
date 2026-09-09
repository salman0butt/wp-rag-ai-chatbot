# M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger

Status: IN PROGRESS — Tasks 1-4 COMPLETE; Task 5 NEXT

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

## Acceptance Criteria
Admin can trace why a source/chunk was selected; secrets/personal data are redacted appropriately; jobs show recoverable status; playground results correlate with backend trace fixtures.

## Tasks

1. **Knowledge source inventory — COMPLETE.** Protected bounded source inventory over the existing source repository. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.
2. **Source detail plus bounded document/chunk inspection — COMPLETE.** Protected allow-listed source detail and bounded persisted child inspection with source/document correlation and UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.
3. **Recoverable job status and safe lifecycle controls — COMPLETE.** Protected bounded persisted job status plus M09-backed enqueue/cancel/retry controls with stable invalid-transition guards and allow-listed safe diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.
4. **Knowledge manager admin UI — COMPLETE.** The existing nonce-authenticated admin client consumes Tasks 1-3 with bounded server-authoritative source/detail/document/chunk/job state; enqueue/cancel/retry; safe stable mutation errors; explicit loading/empty/error states; responsive/long-content and keyboard-accessible navigation; latest-request-wins source/detail/document/chunk selection; and latest-request-wins bounded source-page pagination. Fresh-session independent closeout review `5158649984` found one Important page-navigation race, resolved under genuine RED → GREEN evidence with 0 Critical / 0 Important unresolved. Evidence is in the `docs/progress/M13-TASK4-*` records, including `M13-TASK4-KNOWLEDGE-NAVIGATION-RACE.md` and `M13-TASK4-KNOWLEDGE-PAGE-RACE.md`.
5. **Structured debug trace projection and redaction — PENDING.**
6. **Playground REST execution — PENDING.**
7. **Playground UI — PENDING.**
8. **M13 integration, smoke, review and closeout — PENDING.**

## TDD Evidence

- Task 1 genuine RED `d60f761b52ab29ae1363cf248435984bcfdb3376`, CI `34248330696`; verified implementation `813e14817ec067180b55ac19e09d27995baf348e`, CI `34248981984` GREEN.
- Task 2 genuine initial RED `f7c43911db163bc07d52e0584847d0290b0da6fc`, CI `34259395295`.
- Task 2 final correctness RED `ccbb70bba6132e04e62781561a1a21e2a97035f7`, CI `34262896019`: PHPStan clean; PHPUnit 677 tests / 2,838 assertions with exactly one UTF-8 boundary failure.
- Task 2 final implementation `4a74f587648db3bb36c417fc691596278ef80c44`, CI `34263141368` GREEN across all four permanent jobs.
- Task 3 genuine RED `c7e122f641c7734905711416a9bc318f8cc78fb0`, CI `34267619024`: static analysis clean; PHPUnit 687 tests / 2,897 assertions with exactly two expected missing-route errors; JS/package/WordPress smoke green.
- Task 3 verified implementation/harness head `645f58fa08a1c3dff3d31128a700071a94c2c97a`, CI `34272705657` GREEN across `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`; PHPStan 288/288 clean and PHPUnit 687/687 tests / 2,897 assertions.
- Task 4 is decomposed into bounded RED/GREEN UI slices with durable evidence under `docs/progress/M13-TASK4-*`.
- Task 4 selected-route async-navigation race RED `e0d6332cf85b64dd07e108c488e79746a8bc00b9`, CI `34361171785`: lint/typecheck passed; Jest 26 suites / 63 tests with exactly one expected latest-selection failure. Production implementation `fab6a157bdeb3ef8095d0780a7980ea104b8ff88` makes the newest selected Knowledge route authoritative. Exact branch head `caba788276ce1c05ab042046e0f3032038e57f79`, CI `34361588728`, passed all four permanent jobs with Jest 26/26 suites and 63/63 tests GREEN.
- Task 4 final page-navigation combined genuine RED `1caa472ed8da7cba4fb76d05bdf38e099800bc13`, CI `34392061167`: lint/typecheck passed; Jest 28 suites / 65 tests with exactly two expected failures (stale page success overwrite and stale page failure forcing error). Production fix `73875144415582b9b77793498840cfa88595c301`; guarded runner `34392453519` passed full JavaScript verification with 28/28 suites and 65/65 tests GREEN before committing the fix.

## Integration Test Evidence

- Task 1 source inventory uses persisted source repository fixtures.
- Task 2 verifies persisted source → document → chunk correlation and the existing lexical chunk projection.
- Task 3 verifies bounded persisted job paging and proves unsupported terminal cancellation performs no mutation query/update against the concrete M09 repositories.
- Task 4 browser tests cover bounded REST loading, server-authoritative re-entry/mutation refresh, persisted-ID selection, document/chunk navigation, lifecycle actions, sanitized errors, loading/empty/error states, responsive long content, keyboard semantics, and stale concurrent navigation responses.
- Full milestone integration remains pending Tasks 5-8.

## E2E / Visual Verification
Task 4 responsive/long-content/keyboard behavior has Jest coverage and exact-head WordPress smoke is green. Full milestone desktop/mobile/playground visual verification remains required in Tasks 7-8.

## Security Review

- Task 1 review `5144187297`: 0 Critical / 0 Important.
- Task 2 final review `5145555022`: one Important UTF-8 truncation issue found and resolved; 0 Critical / 0 Important unresolved.
- Task 3 review `5146492264`: 0 Critical / 0 Important. Job output excludes payload/idempotency/lease internals, routes use centralized admin capability, unsupported transitions are guarded before mutation, and error fields reuse the bounded M09 sanitized diagnostic contract.
- Fresh-session independent Task 4 closeout review `5158649984`: 1 Important page-navigation race found and resolved under genuine RED → GREEN; 0 Critical / 0 Important unresolved. Admin capability, safe allow-lists, sanitized error boundaries, bounded reads, constant-time request correlation, and UI accessibility semantics remain intact.
- Admin capability and safe allow-list/redaction boundaries remain mandatory for all remaining tasks.

## Accessibility Review where UI exists
Task 4 uses native links/buttons/forms, selected-state `aria-current`, labelled pagination/form controls, `role="status"`/polite live loading feedback, `role="alert"` safe error feedback, mobile-friendly targets, and responsive long-content handling. Fresh-session independent review `5158649984` found no unresolved accessibility issue. Task 7 and final M13 closeout require another accessibility pass.

## Performance Review where relevant
Tasks 1-3 enforce bounded pagination; Task 2 additionally caps chunk text at 2,000 bytes while preserving valid UTF-8. Task 3 job projection is linear over a page capped at 100 and performs no provider/network work. Task 4 keeps page requests bounded at 20, adds no polling/unbounded browser cache, and uses constant-time selection-generation correlation for async navigation. Later trace/playground results must remain bounded.

## Code Review Findings
Task 1: no blocking findings. Task 2: one Important UTF-8 truncation boundary issue resolved before closeout; no Critical/Important findings remain unresolved. Task 3: no Critical/Important findings. Fresh-session independent Task 4 closeout review `5158649984` found one Important top-level page-navigation race; `73875144415582b9b77793498840cfa88595c301` resolves it with 0 Critical / 0 Important unresolved.

## Fixes
Task 2 replaced unsafe raw byte-boundary chunk truncation with UTF-8-safe trailing-byte correction after a genuine failing regression test. Task 3 corrected one impossible REST request null check and reconciled only stale aggregate route-count harness expectations after production routing was introduced. Task 4 latest-request-wins correlation prevents stale source/document/chunk responses and stale top-level source-page successes/failures from overwriting a newer hash-selected state.

## Fresh Verification Commands
Repository CI (`composer verify:php`, JS verification, package assertion, complete WordPress smoke) remains authoritative at each exact task head.

## Fresh Verification Results
Task 4 race-fix head `caba788276ce1c05ab042046e0f3032038e57f79`, CI `34361588728`: all four permanent jobs GREEN; JavaScript verification included 26/26 suites and 63/63 tests GREEN. Cleanup of the redundant one-shot runner is tracked separately and must also retain exact-head permanent CI GREEN before Task 4 closeout.

## Commits
See task progress evidence files and PR #18 for the complete milestone history.

## Files Changed
See PR #18.

## Known Limitations
Tasks 5-8 remain unfinished; M13 is not merge-ready. Task 5 structured debug-trace projection/redaction is the next unfinished unit.

## Documentation Updated
Task 1-3 evidence plus bounded Task 4 evidence records, including loading/error, responsive/long-content, safe lifecycle errors, and concurrent navigation-race hardening; global `STATUS.md` remains the recovery index.

## Completion Checklist
Incomplete. Task 4 is complete. Tasks 5-8, final milestone review, exact-final-head CI, merge and post-merge main verification remain mandatory.

## Next Milestone
M14 — Frontend Chatbot/Customizer, only after genuine M13 completion.
