# Global Status

- Completed milestones on `main`: **M00-M12**.
- Latest completed milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 PR: **#17 — MERGED** at merge SHA `206dbfcef42cfcee2a998d7f3c386abdb97425a0`.
- Current milestone: **M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger**.
- Active M13 integration PR: **#18 — OPEN, DRAFT**.
- M13 status: **Tasks 1-5 COMPLETE; Task 6 NEXT; Tasks 7-8 PENDING**.

This file is the concise recovery index. Detailed RED/GREEN, CI, review, security, accessibility, and implementation history remains in the per-task progress records and the M13 milestone ledger linked below.

## M13 completed work

### Task 1 — knowledge source inventory: COMPLETE

Protected bounded `GET /admin/knowledge/sources` over the existing source repository with explicit allow-listing. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.

### Task 2 — source detail plus bounded document/chunk inspection: COMPLETE

Protected allow-listed source detail plus paginated document/chunk inspection, source/document ownership checks, and 2,000-byte UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.

### Task 3 — recoverable job status and safe lifecycle controls: COMPLETE

Protected bounded job inventory plus M09-backed enqueue/cancel/retry controls with stable invalid-transition behavior and sanitized diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.

### Task 4 — Knowledge manager admin UI: COMPLETE

Bounded server-authoritative Knowledge UI over Tasks 1-3 with source/detail/document/chunk/job inspection, lifecycle actions, safe errors, loading/empty/error states, responsive/keyboard-accessible navigation, and latest-request-wins correlation for selected-resource and source-page requests.

Fresh-session closeout review found one Important top-level source-page navigation race and resolved it under genuine RED → GREEN. Final Task 4 state: **0 Critical / 0 Important unresolved**. Evidence: the `docs/progress/M13-TASK4-*` records, especially `M13-TASK4-KNOWLEDGE-NAVIGATION-RACE.md` and `M13-TASK4-KNOWLEDGE-PAGE-RACE.md`.

### Task 5 — structured retrieval debug trace projection/redaction: COMPLETE

Administrator-safe `DebugTrace` projection over existing M10 retrieval evidence:

- raw query omitted in favor of SHA-256 query hash + byte count;
- explicit field projection, never recursive/raw provider serialization;
- channel diagnostics restricted to repository-owned `semantic` / `lexical` identifiers;
- at most 20 candidate projections;
- at most 4 approved channel-evidence rows per candidate;
- at most 2,000 UTF-8-safe bytes of candidate content with truncation indicator;
- at most 256 UTF-8-safe bytes for candidate identifier/classification strings;
- existing stable retrieval failure/rerank codes reused.

Fresh-session Task 5 closeout review found one Important remaining boundedness defect: candidate scalar strings were not hard-capped. Genuine RED `98bea8bb675d2eafc77f6117565ccd9d396d2049` / CI `34438769550` reached PHPUnit with 691 tests / 2,917 assertions and exactly one expected 385-byte scalar-bound failure. GREEN production fix `fb774b832594a19b702d4c6caabacc5094b5e30b` / CI `34438873422` passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`. Final Task 5 review state: **0 Critical / 0 Important unresolved**.

Evidence: `docs/progress/M13-TASK5-DEBUG-TRACE.md`.

## Current work

**Task 6 — Playground REST execution** is the authoritative next unfinished unit.

Required continuation:

- add protected `POST /admin/debug/playground` behind `AdminCapability::can_manage`;
- bound and validate the test question and explicit existing bot/retrieval configuration identifiers;
- execute existing M10/M11 production retrieval/RAG seams without duplicating retrieval, scoring, reranking, persistence, or provider logic;
- project retrieval diagnostics through the completed Task 5 `DebugTraceProjector`;
- expose only bounded/allow-listed answer, citation, model, latency, usage/cost fields already available from production results;
- map internal/provider failures to stable repository-owned safe codes such as `retrieval_unavailable` and `playground_failed` without serializing raw exception/provider bodies;
- follow strict TDD with genuine RED → GREEN evidence, fresh review, and exact-head four-job CI before Task 6 completion.

Tasks 7-8 remain pending. Do not merge PR #18 until all M13 tasks, final milestone review, exact-final-head CI, and post-merge `main` verification are complete.

## Durable recovery

- `docs/milestones/M13-knowledge-manager-playground-debugger.md` — M13 milestone ledger.
- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md` — auto-approved design.
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md` — auto-approved implementation plan.
- `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md` — Task 1 evidence.
- `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md` — Task 2 evidence.
- `docs/progress/M13-TASK3-JOB-LIFECYCLE.md` — Task 3 evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-SOURCE-RENDERING.md` — Task 4 source rendering.
- `docs/progress/M13-TASK4-KNOWLEDGE-BOOTSTRAP.md` — Task 4 router/bootstrap/loading.
- `docs/progress/M13-TASK4-KNOWLEDGE-DETAIL-UI.md` — Task 4 selected source/document.
- `docs/progress/M13-TASK4-KNOWLEDGE-CHUNKS.md` — Task 4 chunk inspection/navigation.
- `docs/progress/M13-TASK4-KNOWLEDGE-JOBS.md` — Task 4 job inventory.
- `docs/progress/M13-TASK4-KNOWLEDGE-JOB-ACTIONS.md` — Task 4 cancel/retry.
- `docs/progress/M13-TASK4-KNOWLEDGE-JOB-ENQUEUE.md` — Task 4 enqueue.
- `docs/progress/M13-TASK4-KNOWLEDGE-JOB-ERRORS.md` — Task 4 safe lifecycle errors.
- `docs/progress/M13-TASK4-KNOWLEDGE-STATES.md` — Task 4 loading/empty/error.
- `docs/progress/M13-TASK4-KNOWLEDGE-RESPONSIVE.md` — Task 4 responsive/accessibility.
- `docs/progress/M13-TASK4-KNOWLEDGE-NAVIGATION-RACE.md` — Task 4 selected-resource race hardening.
- `docs/progress/M13-TASK4-KNOWLEDGE-PAGE-RACE.md` — Task 4 page race hardening/closeout.
- `docs/progress/M13-TASK5-DEBUG-TRACE.md` — Task 5 debug trace/redaction/boundedness evidence.
