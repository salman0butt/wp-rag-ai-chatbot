# Global Status

- Completed milestones on `main`: **M00-M13**.
- Latest completed milestone: **M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger**.
- M13 PR: **#18 — MERGED** at merge SHA `a514dd658f20e3103bbe676a0eef8b00a37a23ea`.
- M13 post-merge CI: **`34683129496` — GREEN** for `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.
- Current milestone: **M14 — Frontend Chatbot/Customizer** on PR **#19** (`feat/m14-frontend-chatbot-customizer`).

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

## M14 — IN PROGRESS

### Task 1 — shared normalized appearance configuration — COMPLETE
`AppearanceConfig` provides deterministic browser-safe defaults, explicit allow-listed normalization, rejection of unknown keys, safe six-digit colors, bounded corner radius, and finite appearance enums. Genuine RED was `709c64f2c4817fd85408753644c58db4dbf7f551` / CI `34683797716`; after two explicitly preserved NOT GREEN implementation checkpoints, genuine GREEN is `e9dac2b506747085def72c560088c0d87b1afeb3` / CI `34684305472`, with all permanent gates green. Evidence: `docs/progress/M14-TASK1-APPEARANCE-CONFIG.md`.

### Task 2 — bot-scoped appearance persistence and public-safe widget configuration — COMPLETE
Bot-scoped normalized appearance is persisted through the existing bot database authority, including safe defaults for legacy blank values. The browser-facing projection is an explicit allow-list of public bot identity/name plus normalized appearance and fails closed for missing/disabled bots. Task 2B genuine RED is `c70c02a7c6222a36c4b144c782b9c6d4a8562cb2` / CI `34686937908`; genuine GREEN is `d29d2b947a4c6c6ba3dfff67cc4ec4d203cb1bfa` / CI `34687055933`. Evidence: `docs/progress/M14-TASK2-WIDGET-CONFIG.md`.

### Task 3 — protected admin appearance save/read contracts — COMPLETE
Administrator-only bot-scoped appearance `GET`/`PUT` contracts reuse `BotAppearanceRepository`, `AppearanceConfig`, and `AdminCapability::can_manage`. Invalid identifiers, unknown/unsafe appearance input, and persistence failures fail closed with stable non-sensitive errors. Resource RED was `f2cd956482caa82cd5186ccbe6ad92c271475ec8` / CI `34687383105`, with GREEN `f67d97a1c433bf7c52e9c6d9aa99f902d5853e90` / CI `34687459910`. The route chronology preserves an initial NOT RED at `9215b4e7601a0057a42ef039f78181ef57e4ddec` / CI `34687646158`, a genuine repaired RED at `06895ac2505eaf846069f3a4f921de65f2e09b0e` / CI `34689869102`, two NOT GREEN implementation checkpoints, and final GREEN `078e74d8d373cd0ec0c1881dc6cb0b1122d87a1e` / CI `34690345814`. Evidence: `docs/progress/M14-TASK3-ADMIN-APPEARANCE.md`.

### Task 4 — public chat request/runtime composition and abuse controls — COMPLETE
The bounded public chat REST path now rejects request-level runtime overrides, applies abuse controls before expensive runtime work, resolves persisted production authorities server-side, reuses the existing M10/M11 retrieval/chat composition exactly once, and persists bounded owner-scoped conversation history. Final integration head `e850863d5487ee7603547413bdb9c667c8d04b8e` / CI `34717786952` is GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`. Scoped fallback correctness/security/performance/architecture review has **0 Critical / 0 Important unresolved**. Evidence: `docs/progress/M14-TASK4E-PUBLIC-CHAT-REST.md`, `docs/progress/M14-TASK4E-SHARED-RESPONDER-EVIDENCE.md`, and `docs/progress/M14-TASK4-CLOSEOUT.md`.

### Task 5 — conditional public asset/bootstrap/shortcode mount — COMPLETE
The public shortcode/mount seam now resolves only the existing public-safe widget projection, fails closed for invalid/disabled bots, loads the dedicated widget assets only after a valid mount, and exposes no provider/model/credential/embedding/vector/retrieval authority to the browser. Production implementation head `9619f53cc7130dacbb835f54652ff46f412442f4` / CI `34721951718` is GREEN. Additional real WordPress mount/bootstrap verification is GREEN at `18436c394f826e83885cbb43bff30b509a00a205` / CI `34722486144`; the preceding `a1e0ad17905aba666444ce45c8f4db57db834272` / CI `34722293078` is explicitly **NOT RED** because only the smoke assertion's escaped-JSON representation was wrong. Scoped fallback review has **0 Critical / 0 Important unresolved**. Evidence: `docs/progress/M14-TASK5-PUBLIC-WIDGET-MOUNT.md`.

### Task 6 — floating launcher/panel UI — IN PROGRESS
Task 6A floating-shell work is **COMPLETE**. The public widget now mounts idempotently, opens/closes with focus restoration and Escape handling, projects only bounded normalized appearance values, uses viewport-safe responsive CSS, and exposes visible/native accessible controls plus a named dialog. Final Task 6A implementation head `772a125fd4cf7beb8705a3eecc2c136d4c9c71a6` / CI `34725063417` is GREEN across all permanent jobs. Fallback review resolved one Important accessibility/UX finding and has **0 Critical / 0 Important unresolved**. Evidence: `docs/progress/M14-TASK6A-WIDGET-SHELL.md`.

Task 6 remains open for the authoritative plan's Task 6B/6C conversation interaction and safe presentation slices.

## Current work

Continue M14 Task 6 on existing PR #19 at **Task 6B — non-streaming public conversation submit/loading/error/retry behavior**. Reuse the existing Task 4 `POST /wp-rag-ai-chatbot/v1/chat` authority. The browser may send only `bot_id`, `question`, and optional `conversation_id`; do not introduce provider/model/embedding/vector/retrieval overrides or a second chat/retrieval runtime.

## Durable recovery

- `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md` — M14 auto-approved design and task order.
- `docs/superpowers/plans/2026-09-13-m14-task6-launcher-panel-ui.md` — authoritative Task 6A-6C execution plan.
- `docs/progress/M14-TASK1-APPEARANCE-CONFIG.md` — Task 1 evidence.
- `docs/progress/M14-TASK2-WIDGET-CONFIG.md` — Task 2 persistence/projection evidence and scoped review.
- `docs/progress/M14-TASK3-ADMIN-APPEARANCE.md` — Task 3 protected REST evidence and review.
- `docs/progress/M14-TASK4-CLOSEOUT.md` — Task 4 production composition, integration, CI, and review evidence.
- `docs/progress/M14-TASK5-PUBLIC-WIDGET-MOUNT.md` — Task 5 mount/build/smoke evidence and scoped review.
- `docs/progress/M14-TASK6A-WIDGET-SHELL.md` — Task 6A shell TDD, CI, accessibility remediation, and review evidence.
- PR #19 — active M14 implementation branch and execution source of truth.
- `docs/milestones/M14-frontend-chatbot-customizer.md` — current M14 milestone ledger.
