# Global Status

- Completed milestones on `main`: **M00-M12**.
- Latest completed milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 PR: **#17 — MERGED** at merge SHA `206dbfcef42cfcee2a998d7f3c386abdb97425a0`.
- Current milestone: **M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger**.
- Active M13 integration PR: **#18 — OPEN, DRAFT**.
- M13 status: **Tasks 1-7 COMPLETE; Task 8 IN PROGRESS**.

This file is the concise recovery index. Detailed RED/GREEN, CI, review, security, accessibility, and implementation history remains in the per-task progress records and M13 milestone ledger.

## M13 completed work

### Task 1 — knowledge source inventory: COMPLETE

Protected bounded `GET /admin/knowledge/sources` over the existing source repository with explicit allow-listing. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.

### Task 2 — source detail plus bounded document/chunk inspection: COMPLETE

Protected allow-listed source detail plus paginated document/chunk inspection, source/document ownership checks, and 2,000-byte UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.

### Task 3 — recoverable job status and safe lifecycle controls: COMPLETE

Protected bounded job inventory plus M09-backed enqueue/cancel/retry controls with stable invalid-transition behavior and sanitized diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.

### Task 4 — Knowledge manager admin UI: COMPLETE

Bounded server-authoritative Knowledge UI over Tasks 1-3 with source/detail/document/chunk/job inspection, lifecycle actions, safe errors, loading/empty/error states, responsive/keyboard-accessible navigation, and latest-request-wins correlation. Evidence: `docs/progress/M13-TASK4-*`.

### Task 5 — structured retrieval debug trace projection/redaction: COMPLETE

Administrator-safe `DebugTrace` projection over existing M10 retrieval evidence with raw-query omission, explicit field/channel allow-lists, at most 20 candidates, at most 4 approved channel-evidence rows per candidate, 2,000-byte UTF-8-safe content bounds, and 256-byte UTF-8-safe candidate scalar bounds. Evidence: `docs/progress/M13-TASK5-DEBUG-TRACE.md`.

### Task 6 — Playground REST execution: COMPLETE

Protected bounded `POST /admin/debug/playground` executes the existing production M10/M11 path exactly once. It resolves persisted bot/source/collection/provider/embedding/vector-store authority, composes the existing semantic + lexical retrieval/chat graph, observes the exact retrieval result used for generation, projects through Task 5, and returns only allow-listed bounded success/error DTOs.

The request contract accepts only `bot_id`, positive `source_id`, `collection_id`, and a bounded UTF-8 question. Unknown keys—including credentials, provider/model overrides, embedding overrides, vector-store options and retrieval-limit overrides—fail closed as `invalid_request`.

Final evidence: `docs/progress/M13-TASK6-*`, especially `M13-TASK6-PLAYGROUND-CLOSEOUT.md`.

### Task 7 — Playground UI: COMPLETE

The administrator Playground now consumes only the Task 6 DTO and provides:

- labelled bounded selectors/question submission through the existing nonce-authenticated admin client;
- structured answer, citations, model/execution, usage, retrieval trace and bounded candidate diagnostics;
- repository-owned safe error copy only;
- polite live loading status;
- latest-request-wins and route-lifecycle invalidation for stale async completions;
- responsive long-content rendering and desktop/mobile form layout;
- native keyboard-reachable `details`/`summary` candidate disclosure;
- 44px mobile submit/disclosure targets.

Responsive/accessibility hardening chronology:

- `50273d9694fc2c4387bd4d3be354c501a907d48b` / CI `34681551478` — **NOT RED**, pre-test JavaScript verification failure;
- `ffe63b8145c0b6ef7647a254359e888e1196abfb` / CI `34681634157` — genuine RED proving the missing responsive root hook;
- `afafa56e6defc79800ab19ecd2fa0d60cdd795c7` / CI `34681766674` — genuine GREEN, all permanent jobs passed;
- `1a89a2a62ef6be685080ddf8c32327f0a587ef09` / CI `34681912450` — responsive CSS hardening, all permanent jobs passed;
- `5d5f734aa89a0a837c1db95a73dd34d74ad2f282` / CI `34682140441` — long-content/native-disclosure verification, all permanent jobs passed.

Final Task 7 fallback review: **0 Critical / 0 Important unresolved**. Independent reviewer/subagent transport was unavailable and is not falsely claimed.

Durable evidence: `docs/progress/M13-TASK7-PLAYGROUND-CONCURRENCY.md` and `docs/progress/M13-TASK7-PLAYGROUND-CLOSEOUT.md`.

## Current work

### Task 8 — M13 integration, smoke, review and closeout: IN PROGRESS

Recover existing M13 WordPress smoke and integration coverage before adding anything. Add only genuinely missing capability/representative-route smoke under strict RED → GREEN; do not duplicate coverage already delivered during Tasks 1-7. Then perform final correctness, security, performance and accessibility review, resolve all Critical/Important findings, reconcile milestone/PR documentation, require exact-final-head `php-quality`, `js-quality`, `package`, and `wordpress-smoke` GREEN, merge only when all gates are satisfied, and verify fresh post-merge `main` CI before marking M13 complete.

## Durable recovery

- `docs/milestones/M13-knowledge-manager-playground-debugger.md` — M13 milestone ledger.
- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md` — auto-approved design.
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md` — auto-approved implementation plan.
- `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md` — Task 1 evidence.
- `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md` — Task 2 evidence.
- `docs/progress/M13-TASK3-JOB-LIFECYCLE.md` — Task 3 evidence.
- `docs/progress/M13-TASK4-*` — Task 4 implementation/closeout evidence.
- `docs/progress/M13-TASK5-DEBUG-TRACE.md` — Task 5 evidence.
- `docs/progress/M13-TASK6-*` — Task 6 evidence.
- `docs/progress/M13-TASK7-*` — Task 7 UI/concurrency/responsive/accessibility evidence.
