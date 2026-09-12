# Global Status

- Completed milestones on `main`: **M00-M12**.
- Latest completed milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 PR: **#17 — MERGED** at merge SHA `206dbfcef42cfcee2a998d7f3c386abdb97425a0`.
- Current milestone: **M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger**.
- Active M13 integration PR: **#18 — OPEN, DRAFT**.
- M13 status: **Tasks 1-6 COMPLETE; Task 7 IN PROGRESS; Task 8 PENDING**.

This file is the concise recovery index. Detailed RED/GREEN, CI, review, security, accessibility, and implementation history remains in the per-task progress records and M13 milestone ledger.

## M13 completed work

### Task 1 — knowledge source inventory: COMPLETE

Protected bounded `GET /admin/knowledge/sources` over the existing source repository with explicit allow-listing. Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.

### Task 2 — source detail plus bounded document/chunk inspection: COMPLETE

Protected allow-listed source detail plus paginated document/chunk inspection, source/document ownership checks, and 2,000-byte UTF-8-safe chunk truncation. Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.

### Task 3 — recoverable job status and safe lifecycle controls: COMPLETE

Protected bounded job inventory plus M09-backed enqueue/cancel/retry controls with stable invalid-transition behavior and sanitized diagnostics. Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.

### Task 4 — Knowledge manager admin UI: COMPLETE

Bounded server-authoritative Knowledge UI over Tasks 1-3 with source/detail/document/chunk/job inspection, lifecycle actions, safe errors, loading/empty/error states, responsive/keyboard-accessible navigation, and latest-request-wins correlation. Fresh-session closeout resolved the remaining Important page-navigation race under genuine RED → GREEN. Evidence: `docs/progress/M13-TASK4-*`.

### Task 5 — structured retrieval debug trace projection/redaction: COMPLETE

Administrator-safe `DebugTrace` projection over existing M10 retrieval evidence with raw-query omission, explicit field/channel allow-lists, at most 20 candidates, at most 4 approved channel-evidence rows per candidate, 2,000-byte UTF-8-safe content bounds, and 256-byte UTF-8-safe candidate scalar bounds. Fresh-session closeout resolved the final Important boundedness defect under genuine RED → GREEN. Evidence: `docs/progress/M13-TASK5-DEBUG-TRACE.md`.

### Task 6 — Playground REST execution: COMPLETE

Protected bounded `POST /admin/debug/playground` now executes the existing production M10/M11 path exactly once. It resolves persisted bot/source/collection/provider/embedding/vector-store authority, composes the existing semantic + lexical retrieval/chat graph, observes the exact retrieval result used for generation, projects through Task 5, and returns only allow-listed bounded success/error DTOs.

The request contract accepts only `bot_id`, positive `source_id`, `collection_id`, and a bounded UTF-8 question. Unknown keys—including credentials, provider/model overrides, embedding overrides, vector-store options and retrieval-limit overrides—fail closed as `invalid_request`.

Final Task 6 closeout evidence includes:

- request-handler RED `3b4bdb1df6de76474e60693abbb97dd28a92672b` / CI `34659505729` → GREEN `1b85b0185689472bd38035f1757da29b7ef8058d` / CI `34659592284`;
- citation-lineage RED `dee49f4b6bfd12a2d40ba724f9f42b838d2fab8d` / CI `34669687968` → GREEN `bbf0a03b95cf359a9a5c4ea643bf323ca24008de` / CI `34671321083`;
- real WordPress route/smoke exact-head GREEN `0230ef184ef9a7558cb38e6541a5d8e207f21fb5` / CI `34671573305`, with all four permanent jobs GREEN and the new Playground route smoke passing;
- fresh final fallback review: **0 Critical / 0 Important unresolved**. Independent reviewer/subagent transport was unavailable and is not falsely claimed.

Durable evidence: `docs/progress/M13-TASK6-*`, especially `M13-TASK6-PLAYGROUND-CLOSEOUT.md`, `M13-TASK6-PLAYGROUND-WP-SMOKE.md`, and `M13-TASK6-PLAYGROUND-CITATION-BOUNDS.md`.

## Current work

### Task 7 — Playground UI: IN PROGRESS

Consume only the Task 6 bounded trace DTO through the existing nonce-authenticated M12 admin client. Render the question form and structured diagnostic sections for candidates/scores/filter/rerank/context/answer/citations/model/latency/usage/errors with safe loading/empty/error handling, labelled controls, responsive long-content behavior, keyboard flow, and no arbitrary backend/provider-message rendering.

### Exact next Task 7 work

1. Re-read the M13 design/plan plus current M12 admin router/client/screens/tests.
2. Under a genuine Jest RED, specify one representative Task 6 trace fixture and stable safe error rendering.
3. Implement only the smallest Playground UI/API-client seam required for GREEN; consume the server DTO without duplicating backend scoring/ranking logic.
4. Continue with structured diagnostic sections, async/latest-request correctness, responsive/keyboard accessibility, and long content under additional strict RED → GREEN cycles.
5. Perform correctness/security/performance/accessibility review and resolve all Critical/Important findings before Task 7 closeout.
6. Require exact-head `php-quality`, `js-quality`, `package`, and `wordpress-smoke` GREEN before Task 7 completion.

Task 8 remains pending. Do not merge PR #18 until Task 7, Task 8, final M13 review, exact-final-head CI, merge gates, and post-merge `main` verification are all complete.

## Durable recovery

- `docs/milestones/M13-knowledge-manager-playground-debugger.md` — M13 milestone ledger.
- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md` — auto-approved design.
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md` — auto-approved implementation plan.
- `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md` — Task 1 evidence.
- `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md` — Task 2 evidence.
- `docs/progress/M13-TASK3-JOB-LIFECYCLE.md` — Task 3 evidence.
- `docs/progress/M13-TASK4-*` — Task 4 implementation and closeout evidence.
- `docs/progress/M13-TASK5-DEBUG-TRACE.md` — Task 5 debug trace/redaction/boundedness evidence.
- `docs/progress/M13-TASK6-*` — Task 6 composition, route, request, smoke and closeout evidence.
