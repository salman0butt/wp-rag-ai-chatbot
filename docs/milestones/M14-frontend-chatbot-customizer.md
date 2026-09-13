# M14 — Frontend Floating/Embedded Chatbot & Complete Visual Customizer

Status: IN PROGRESS

## Goal
Deliver the production public chatbot surfaces and shared live appearance customizer.

## Dependencies
M11 backend chat, M12 admin shell.

## In Scope
Floating launcher; embedded/fullscreen/mobile; shortcode; Gutenberg block; streaming messages; markdown/links/citations; quick replies; product-card rendering foundation; loading/errors/retry/session/history where enabled; full appearance schema; live preview; custom CSS; theme support.

## Out of Scope
Advanced proactive rules M15; commerce actions M18.

## Architecture
Compiled React/TS widget consumes public-safe bot config and normalized chat stream; appearance schema shared by preview/runtime. Public chat runtime authority is resolved server-side from persisted configuration and reuses M10/M11 retrieval/generation composition rather than duplicating it.

## Acceptance Criteria
No provider secrets in bundles/HTML/API; widget works desktop/mobile; long answers/URLs/source cards don't overflow; assets only load when applicable; appearance preview matches runtime.

## Tasks

- [x] Task 1 — shared normalized appearance configuration.
- [x] Task 2 — bot-scoped appearance persistence and public-safe widget configuration.
- [x] Task 3 — protected administrator appearance save/read contracts.
- [x] Task 4 — public chat request/runtime composition, abuse controls, owner-scoped conversation history, REST integration, and review closeout.
- [x] Task 5 — conditional public asset/bootstrap/shortcode mount.
- [x] Task 6 — floating launcher/panel UI.
  - [x] Task 6A — deterministic accessible responsive launcher/panel shell.
  - [x] Task 6B — non-streaming public conversation submit/loading/error/retry behavior.
  - [x] Task 6C — safe message/citation/link/history presentation and Task 6 closeout.
- [ ] Task 7 — streaming public chat UX.
- [ ] Task 8 — administrator visual customizer/live preview.
- [ ] Task 9 — block/direct/fullscreen embedding surfaces.
- [ ] Task 10 — milestone integration, visual verification, review, and closeout.

## TDD Evidence
Per-task RED/GREEN chronology is recorded under `docs/progress/M14-*`. Task 4 final production-composition chronology and invalid checkpoints are summarized in `docs/progress/M14-TASK4-CLOSEOUT.md`; Task 5 mount/build/smoke chronology is recorded in `docs/progress/M14-TASK5-PUBLIC-WIDGET-MOUNT.md`; Task 6 is recorded in `docs/progress/M14-TASK6A-WIDGET-SHELL.md`, `docs/progress/M14-TASK6B-NONSTREAMING-CONVERSATION.md`, and `docs/progress/M14-TASK6C-WIDGET-PRESENTATION.md`.

## Integration Test Evidence
Task 4 real WordPress REST smoke covers malformed public requests, arbitrary runtime-override rejection, abuse-control ordering, and disabled-bot fail-closed behavior. Task 5 proves shortcode registration, no eager assets, invalid/disabled fail-closed mounts, valid conditional asset enqueue, deterministic mount output, and public bootstrap secret/runtime-authority exclusion. Task 6 final implementation head `ddd4de2e1cd1af2ada84a0f703226377c958e030` passed exact-head CI `34729280110` across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## E2E / Visual Verification
Task 6 provides the bounded desktop/mobile launcher/panel shell, keyboard open/close/Escape behavior, focus restoration, accessible launcher/dialog semantics, non-streaming submit/loading/error/retry, safe answer/citation rendering, copy controls, finite transcript history, and safe citation links. Streaming, customizer, broader embed, and final milestone visual/integration verification remain Tasks 7-10.

## Security Review
Task 6 keeps the browser request contract closed to `bot_id`, `question`, and optional server-issued `conversation_id`; model/user/citation strings render as text; citation anchors allow only `http:`/`https:` and use `noopener noreferrer`; unknown response fields and raw server/provider error details are ignored. No provider/model/credential/embedding/vector/retrieval authority is duplicated client-side.

## Accessibility Review where UI exists
Task 6 uses native launcher/close/send/retry/copy/form/details controls, a bot-scoped named dialog, polite live regions, predictable focus transfer/restoration, and Escape dismissal. Task 6A's Important visible-label/dialog-name finding was resolved. Final Task 6 closeout review state is **0 Critical / 0 Important unresolved**.

## Performance Review where relevant
Assets remain conditional from Task 5. The runtime is idempotent per mount, enforces one in-flight public chat request per widget, caps response citations at 8, and caps rendered transcript history at 40 top-level entries.

## Code Review Findings
Independent reviewer transport was unavailable during the connector-only Task 6 runs, so the repository-approved scoped fallback review was used. Important findings resolved during Task 6 include visible launcher/dialog semantics, the real nested public error envelope, finite transcript history, and alignment with the server's nullable `conversation_id` response contract. Final Task 6 review state is **0 Critical / 0 Important unresolved**.

## Fixes
Invalid RED/GREEN checkpoints are preserved honestly in the Task 6 progress records. Task 6C specifically records `4758b0899538b5c2844a1611c0efae4dad74b04b` / CI `34727755857` as **NOT GREEN** because Prettier stopped JavaScript verification before Jest. The final review-driven nullable-conversation repair used RED `de6dc1860fe0225699d50441e39622ae801037bd` / CI `34729171223` and GREEN `ddd4de2e1cd1af2ada84a0f703226377c958e030` / CI `34729280110`.

## Fresh Verification Commands
CI is authoritative for scheduled connector-only runs. Permanent gates include Composer validation/audit, PHPCS, PHPStan, PHPUnit, JavaScript verification/audit/gating, production package assertion, and real WordPress smoke.

## Fresh Verification Results
Task 6 implementation head `ddd4de2e1cd1af2ada84a0f703226377c958e030`: CI `34729280110` GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`. Durable status-only commits made after that implementation must themselves receive exact-final-head CI before the next implementation unit is treated as based on a verified branch head.

## Commits
See `docs/progress/M14-*` for exact task commit chronology. Active milestone PR is #19 on `feat/m14-frontend-chatbot-customizer`.

## Files Changed
Tracked by PR #19; per-task progress documents identify the focused implementation surfaces.

## Known Limitations
Task 6 is complete. Streaming/simulated typing integration, visual customizer/live preview, broader embed surfaces, and final milestone integration/visual verification remain Tasks 7-10.

## Documentation Updated
`docs/progress/STATUS.md`, per-task M14 evidence records, this milestone ledger, and M14 Superpowers design/plans.

## Completion Checklist
Milestone remains open until Tasks 7-10, final UI/security/accessibility/performance review, exact-final-head CI, merge gate, and post-merge main verification complete.

## Current Work
Task 7 — streaming/simulated typing integration. Reuse the existing production streaming authority where supported, fall back to bounded simulated typing from the established non-streaming result when required, and keep the browser free of provider/runtime selection authority.

## Next Milestone
M15 — Display Rules/RTL/Accessibility.
