# M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger

Status: IN PROGRESS — Tasks 1-2 COMPLETE; Task 3 NEXT

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
3. **Recoverable job status and safe lifecycle controls — NEXT.** Reuse M09 state-transition seams; unsupported transitions must not mutate persisted state.
4. **Knowledge manager admin UI — PENDING.**
5. **Structured debug trace projection and redaction — PENDING.**
6. **Playground REST execution — PENDING.**
7. **Playground UI — PENDING.**
8. **M13 integration, smoke, review and closeout — PENDING.**

## TDD Evidence

- Task 1 genuine RED `d60f761b52ab29ae1363cf248435984bcfdb3376`, CI `34248330696`; verified implementation `813e14817ec067180b55ac19e09d27995baf348e`, CI `34248981984` GREEN.
- Task 2 genuine initial RED `f7c43911db163bc07d52e0584847d0290b0da6fc`, CI `34259395295`.
- Task 2 final correctness RED `ccbb70bba6132e04e62781561a1a21e2a97035f7`, CI `34262896019`: PHPStan clean; PHPUnit 677 tests / 2,838 assertions with exactly one UTF-8 boundary failure.
- Task 2 final implementation `4a74f587648db3bb36c417fc691596278ef80c44`, CI `34263141368` GREEN across all four permanent jobs.

## Integration Test Evidence

- Task 1 source inventory uses persisted source repository fixtures.
- Task 2 verifies persisted source → document → chunk correlation and the existing lexical chunk projection.
- Full milestone integration remains pending Tasks 3-8.

## E2E / Visual Verification
Desktop/mobile; large result sets; loading/empty/error; long URLs/chunks; keyboard accessibility remain required for Tasks 4, 7 and 8.

## Security Review

- Task 1 review `5144187297`: 0 Critical / 0 Important.
- Task 2 final review `5145555022`: one Important UTF-8 truncation issue found and resolved; 0 Critical / 0 Important unresolved.
- Admin capability and safe allow-list/redaction boundaries remain mandatory for all remaining tasks.

## Accessibility Review where UI exists
Required for Tasks 4, 7 and final closeout. Tasks 1-2 are server-only.

## Performance Review where relevant
Tasks 1-2 enforce bounded pagination; Task 2 additionally caps chunk text at 2,000 bytes while preserving valid UTF-8. Later trace/playground results must remain bounded.

## Code Review Findings
Task 1: no blocking findings. Task 2: one Important UTF-8 truncation boundary issue resolved before closeout; no Critical/Important findings remain unresolved.

## Fixes
Task 2 replaced unsafe raw byte-boundary chunk truncation with UTF-8-safe trailing-byte correction after a genuine failing regression test.

## Fresh Verification Commands
Repository CI (`composer verify:php`, JS verification, package assertion, complete WordPress smoke) remains authoritative at each exact task head.

## Fresh Verification Results
Task 2 implementation head `4a74f587648db3bb36c417fc691596278ef80c44`, CI `34263141368`: all four permanent jobs GREEN.

## Commits
See task progress evidence files and PR #18 for the complete milestone history.

## Files Changed
See PR #18.

## Known Limitations
Tasks 3-8 remain unfinished; M13 is not merge-ready.

## Documentation Updated
Task 1 and Task 2 durable progress records plus this milestone ledger and global `STATUS.md`.

## Completion Checklist
Incomplete. Tasks 3-8, final review, exact-final-head CI, merge and post-merge main verification remain mandatory.

## Next Milestone
M14 — Frontend Chatbot/Customizer, only after genuine M13 completion.
