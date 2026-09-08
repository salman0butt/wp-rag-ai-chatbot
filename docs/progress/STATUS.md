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

## Current work

**Task 3 — Recoverable job status and safe lifecycle controls** is the authoritative next unfinished unit.

Per the approved M13 plan:

- reuse M09 job repository/queue/state-transition seams;
- expose bounded job status plus only supported enqueue/cancel/retry transitions;
- begin with genuine RED tests proving unsupported transitions do not mutate persisted job state;
- return stable `invalid_transition` errors for unsupported transitions;
- expose only safe error code/message fields and never serialize raw exception/provider payloads;
- verify behavior against persisted job fixtures before advancing to Task 4.

## Durable recovery

- `docs/milestones/M13-knowledge-manager-playground-debugger.md` — M13 milestone ledger.
- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md` — auto-approved design.
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md` — auto-approved implementation plan.
- `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md` — Task 1 evidence.
- `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md` — Task 2 evidence.
- PR #18 — milestone-wide draft integration record.
