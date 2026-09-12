# Global Status

- Completed milestones on `main`: **M00-M13**.
- Latest completed milestone: **M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger**.
- M13 PR: **#18 — MERGED** at merge SHA `a514dd658f20e3103bbe676a0eef8b00a37a23ea`.
- M13 post-merge CI: **`34683129496` — GREEN** for `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.
- Next milestone: **M14 — Frontend Chatbot/Customizer**.

This file is the concise recovery index. Detailed RED/GREEN, CI, review, security, accessibility, and implementation history remains in the per-task progress records and milestone ledgers.

## M13 — COMPLETE

### Task 1 — knowledge source inventory
Protected bounded `GET /admin/knowledge/sources` over the existing source repository with explicit allow-listing. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.

### Task 2 — source detail plus bounded document/chunk inspection
Protected allow-listed source detail plus paginated document/chunk inspection, source/document ownership checks, and UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.

### Task 3 — recoverable job status and safe lifecycle controls
Protected bounded job inventory plus M09-backed enqueue/cancel/retry controls with stable invalid-transition behavior and sanitized diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.

### Task 4 — Knowledge manager admin UI
Bounded server-authoritative Knowledge UI with safe errors, responsive/keyboard-accessible navigation, and latest-request-wins resource/page correlation. Evidence: `docs/progress/M13-TASK4-*`.

### Task 5 — structured retrieval debug trace projection/redaction
Administrator-safe bounded/redacted `DebugTrace` projection over existing M10 retrieval evidence. Evidence: `docs/progress/M13-TASK5-DEBUG-TRACE.md`.

### Task 6 — Playground REST execution
Protected bounded `POST /admin/debug/playground` executes the existing production M10/M11 path exactly once using persisted runtime authority and a closed request schema. Evidence: `docs/progress/M13-TASK6-*`, especially `M13-TASK6-PLAYGROUND-CLOSEOUT.md`.

### Task 7 — Playground UI
Bounded Task 6 DTO rendering with labelled submission, structured diagnostics, safe error mapping, live status, stale-request/navigation invalidation, responsive long-content behavior, and native keyboard disclosure semantics. Evidence: `docs/progress/M13-TASK7-PLAYGROUND-CONCURRENCY.md` and `docs/progress/M13-TASK7-PLAYGROUND-CLOSEOUT.md`.

### Task 8 — integration, smoke, review and closeout
Representative real WordPress smoke covers administrator Knowledge and Playground capability/route behavior. Final fallback correctness/security/performance/accessibility/architecture review has **0 Critical / 0 Important unresolved**. Exact-final-head CI `34682971096` was GREEN before merge; PR #18 merged at `a514dd658f20e3103bbe676a0eef8b00a37a23ea`; fresh post-merge `main` CI `34683129496` is GREEN. Evidence: `docs/progress/M13-TASK8-CLOSEOUT.md`.

## Current work

Recover M14 from its milestone/product/architecture/spec state and begin the next unfinished unit only after confirming no newer branch/PR already owns it.

## Durable recovery

- `docs/milestones/M13-knowledge-manager-playground-debugger.md` — completed M13 milestone ledger.
- `docs/progress/M13-TASK8-CLOSEOUT.md` — final merge/post-merge evidence.
- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md` — M13 design.
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md` — M13 implementation plan.
