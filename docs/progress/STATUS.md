# Global Status

- Completed milestones on `main`: **M00-M12**.
- Latest completed milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 PR: **#17 — MERGED** at merge SHA `206dbfcef42cfcee2a998d7f3c386abdb97425a0`.
- Current milestone: **M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger**.
- Active M13 integration PR: **#18 — OPEN, DRAFT**.

## M13 current progress

### Task 1 — knowledge source inventory: COMPLETE

- Protected bounded `GET /admin/knowledge/sources` over the existing source repository.
- Explicit allow-list excludes persisted source `config` and `sourceHash`.
- Genuine RED `d60f761b52ab29ae1363cf248435984bcfdb3376`, CI `34248330696`.
- Verified implementation `813e14817ec067180b55ac19e09d27995baf348e`, CI `34248981984` GREEN.
- Review `5144187297`: 0 Critical / 0 Important.
- Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.

### Task 2 — source detail plus bounded document/chunk inspection: COMPLETE

- Protected source detail, paginated document summary, and paginated persisted chunk inspection.
- Child pages capped at 100; chunk text capped at 2,000 bytes with a truncation indicator and UTF-8-safe boundary handling.
- Explicit DTOs exclude source config/hash, raw document content/metadata/hash, and chunk metadata/content hash.
- Source/document ownership is verified before chunk inspection.
- Genuine initial RED `f7c43911db163bc07d52e0584847d0290b0da6fc`, CI `34259395295`.
- Final UTF-8 closeout RED `ccbb70bba6132e04e62781561a1a21e2a97035f7`, CI `34262896019`: PHPStan clean; 677 tests / 2,838 assertions with exactly one expected failure.
- Final implementation `4a74f587648db3bb36c417fc691596278ef80c44`, CI `34263141368` GREEN across `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.
- Final review `5145555022`: 1 Important UTF-8 truncation issue found and resolved; 0 Critical / 0 Important unresolved.
- Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.

### Task 3 — recoverable job status and safe lifecycle controls: COMPLETE

- Protected bounded job inventory plus enqueue/cancel/retry endpoints over the existing M09 queue/repository/state-transition seams.
- Job pages are capped at 100 and expose only allow-listed operational fields; payload, idempotency and lease internals are excluded.
- Unsupported terminal cancellation and non-failed retry return stable `invalid_transition` before any mutation/enqueue call.
- Persisted error code/message fields reuse the M09 sanitized diagnostic contract rather than raw exception/provider payloads.
- Genuine RED `c7e122f641c7734905711416a9bc318f8cc78fb0`, CI `34267619024`: static analysis clean; PHPUnit 687 tests / 2,897 assertions with exactly two expected missing-route errors; JS/package/WordPress smoke green.
- Final implementation/harness head `645f58fa08a1c3dff3d31128a700071a94c2c97a`, CI `34272705657`: all four permanent jobs GREEN; PHPStan 288/288 clean; PHPUnit 687/687 tests / 2,897 assertions; Composer audit clean.
- Review `5146492264`: 0 Critical / 0 Important.
- Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.

## Current work

**Task 4 — Knowledge manager admin UI** is the authoritative next unfinished unit.

Per the approved M13 plan:

- consume Tasks 1–3 REST DTOs through the existing typed nonce-authenticated admin client;
- begin with genuine Jest RED coverage for paginated source rendering, selected detail, loading/empty/error states, supported job actions, and keyboard-labelled controls;
- keep pagination and lifecycle mutation refresh server-authoritative;
- do not cache secret or unbounded data in browser state;
- add constrained-width and long-content accessibility/CSS coverage;
- verify Jest, PHP integration, package, and complete WordPress smoke before advancing to Task 5.

## Durable recovery

- `docs/milestones/M13-knowledge-manager-playground-debugger.md` — M13 milestone ledger.
- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md` — auto-approved design.
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md` — auto-approved implementation plan.
- `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md` — Task 1 evidence.
- `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md` — Task 2 evidence.
- `docs/progress/M13-TASK3-JOB-LIFECYCLE.md` — Task 3 evidence.
- PR #18 — milestone-wide draft integration record.
