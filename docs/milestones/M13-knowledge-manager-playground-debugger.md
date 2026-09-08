# M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger

Status: IN PROGRESS — Tasks 1-3 COMPLETE; Task 4 NEXT

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
4. **Knowledge manager admin UI — NEXT.** Consume Tasks 1-3 through the existing typed nonce-authenticated client; server-authoritative pagination/mutation refresh and accessible admin states are required.
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
- Task 3 implementation checkpoint `f360a2151da5e88de25e5c36746c23f496b04def`, CI `34272080802`, was not GREEN because PHP quality stopped on a redundant impossible null comparison.
- Task 3 verification-harness checkpoint `ac9f1947c616b12853a4a06bb684b091cab8a4d3`, CI `34272440264`, was not GREEN because five pre-existing route-count expectations had not yet incorporated the two new Task 3 routes.
- Task 3 verified implementation/harness head `645f58fa08a1c3dff3d31128a700071a94c2c97a`, CI `34272705657` GREEN across `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`; PHPStan 288/288 clean and PHPUnit 687/687 tests / 2,897 assertions.

## Integration Test Evidence

- Task 1 source inventory uses persisted source repository fixtures.
- Task 2 verifies persisted source → document → chunk correlation and the existing lexical chunk projection.
- Task 3 verifies bounded persisted job paging and proves unsupported terminal cancellation performs no mutation query/update against the concrete M09 repositories.
- Full milestone integration remains pending Tasks 4-8.

## E2E / Visual Verification
Desktop/mobile; large result sets; loading/empty/error; long URLs/chunks; keyboard accessibility remain required for Tasks 4, 7 and 8.

## Security Review

- Task 1 review `5144187297`: 0 Critical / 0 Important.
- Task 2 final review `5145555022`: one Important UTF-8 truncation issue found and resolved; 0 Critical / 0 Important unresolved.
- Task 3 review `5146492264`: 0 Critical / 0 Important. Job output excludes payload/idempotency/lease internals, routes use centralized admin capability, unsupported transitions are guarded before mutation, and error fields reuse the bounded M09 sanitized diagnostic contract.
- Admin capability and safe allow-list/redaction boundaries remain mandatory for all remaining tasks.

## Accessibility Review where UI exists
Required for Tasks 4, 7 and final closeout. Tasks 1-3 are server-only.

## Performance Review where relevant
Tasks 1-3 enforce bounded pagination; Task 2 additionally caps chunk text at 2,000 bytes while preserving valid UTF-8. Task 3 job projection is linear over a page capped at 100 and performs no provider/network work. Later trace/playground results must remain bounded.

## Code Review Findings
Task 1: no blocking findings. Task 2: one Important UTF-8 truncation boundary issue resolved before closeout; no Critical/Important findings remain unresolved. Task 3: no Critical/Important findings.

## Fixes
Task 2 replaced unsafe raw byte-boundary chunk truncation with UTF-8-safe trailing-byte correction after a genuine failing regression test. Task 3 corrected one impossible REST request null check and reconciled only stale aggregate route-count harness expectations after production routing was introduced.

## Fresh Verification Commands
Repository CI (`composer verify:php`, JS verification, package assertion, complete WordPress smoke) remains authoritative at each exact task head.

## Fresh Verification Results
Task 3 implementation/harness head `645f58fa08a1c3dff3d31128a700071a94c2c97a`, CI `34272705657`: all four permanent jobs GREEN. PHPStan 288/288 clean; PHPUnit 687 tests / 2,897 assertions; Composer audit clean; complete WordPress smoke passed activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and cleanup.

## Commits
See task progress evidence files and PR #18 for the complete milestone history.

## Files Changed
See PR #18.

## Known Limitations
Tasks 4-8 remain unfinished; M13 is not merge-ready.

## Documentation Updated
Task 1, Task 2 and Task 3 durable progress records plus this milestone ledger and global `STATUS.md`.

## Completion Checklist
Incomplete. Tasks 4-8, final review, exact-final-head CI, merge and post-merge main verification remain mandatory.

## Next Milestone
M14 — Frontend Chatbot/Customizer, only after genuine M13 completion.
