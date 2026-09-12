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
- [ ] Task 6 — floating launcher/panel UI.
  - [x] Task 6A — deterministic accessible responsive launcher/panel shell.
  - [ ] Task 6B — non-streaming public conversation submit/loading/error/retry behavior.
  - [ ] Task 6C — safe message/citation/link/history presentation and Task 6 closeout.
- [ ] Task 7 — streaming public chat UX.
- [ ] Task 8 — administrator visual customizer/live preview.
- [ ] Task 9 — block/direct/fullscreen embedding surfaces.
- [ ] Task 10 — milestone integration, visual verification, review, and closeout.

## TDD Evidence
Per-task RED/GREEN chronology is recorded under `docs/progress/M14-*`. Task 4 final production-composition chronology and invalid checkpoints are summarized in `docs/progress/M14-TASK4-CLOSEOUT.md`; Task 5 mount/build/smoke chronology is recorded in `docs/progress/M14-TASK5-PUBLIC-WIDGET-MOUNT.md`; Task 6A shell chronology is recorded in `docs/progress/M14-TASK6A-WIDGET-SHELL.md`.

## Integration Test Evidence
Task 4 real WordPress REST smoke covers malformed public requests, arbitrary runtime-override rejection, abuse-control ordering, and disabled-bot fail-closed behavior. Task 5 proves shortcode registration, no eager assets, invalid/disabled fail-closed mounts, valid conditional asset enqueue, deterministic mount output, and public bootstrap secret/runtime-authority exclusion. Task 6A exact-head CI `34725063417` is GREEN across all permanent jobs at implementation SHA `772a125fd4cf7beb8705a3eecc2c136d4c9c71a6`.

## E2E / Visual Verification
Task 6A now provides bounded desktop/mobile shell hooks, light/dark/system presentation, keyboard open/close/Escape behavior, focus restoration, and accessible launcher/dialog semantics. Task 6B/6C and Tasks 7-10 still require loading/error/chat-content, long-answer/URL/citation/source-card, streaming, customizer, embed, and final visual verification.

## Security Review
Task 6A browser appearance projection is explicit and finite; it cannot become arbitrary CSS/HTML or provider/model/credential/embedding/vector/retrieval authority. The public chat browser work must continue to call the Task 4 server authority with only `bot_id`, `question`, and optional `conversation_id`. XSS/markdown/link handling and custom CSS policy remain later gates.

## Accessibility Review where UI exists
Task 6A fallback review found one Important issue: visually blank launcher/close controls and an unnamed panel. A strict RED/GREEN remediation now renders visible labels and a bot-scoped named dialog. Final Task 6A review state is **0 Critical / 0 Important unresolved**.

## Performance Review where relevant
Assets remain conditional from Task 5. Task 6A rendering is small and idempotent per mount. Task 6B/7 must retain in-flight request bounds and avoid duplicated network/runtime work.

## Code Review Findings
Independent reviewer transport was unavailable for Task 6A, so the repository-approved scoped fallback correctness/security/performance/accessibility/architecture review was used. The one Important accessibility/UX finding was resolved; **0 Critical / 0 Important unresolved** remain for Task 6A.

## Fixes
Task 6A preserves invalid evidence honestly: `c9f5221cb4fb04a3b07f275f1bce5020ec8b5480` / CI `34723561305` and `8320fa84a797390260cc981cb6112509972e6be1` / CI `34724956542` are **NOT RED** because lint/formatting blocked the intended Jest assertions. Corrected genuine RED checkpoints and GREEN evidence are recorded in `docs/progress/M14-TASK6A-WIDGET-SHELL.md`.

## Fresh Verification Commands
CI is authoritative for scheduled connector-only runs. Permanent gates include Composer validation/audit, PHPCS, PHPStan, PHPUnit, JavaScript verification/audit/gating, production package assertion, and real WordPress smoke.

## Fresh Verification Results
Task 6A implementation head `772a125fd4cf7beb8705a3eecc2c136d4c9c71a6`: CI `34725063417` GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## Commits
See `docs/progress/M14-*` for exact task commit chronology. Active milestone PR is #19 on `feat/m14-frontend-chatbot-customizer`.

## Files Changed
Tracked by PR #19; per-task progress documents identify the focused implementation surfaces.

## Known Limitations
Task 6 is not complete: Task 6B/6C conversation interaction and safe presentation remain. Streaming UX, visual customizer, broader embed surfaces, and final milestone integration also remain Tasks 7-10.

## Documentation Updated
`docs/progress/STATUS.md`, per-task M14 evidence records, this milestone ledger, and M14 Superpowers design/plans.

## Completion Checklist
Milestone remains open until Tasks 6-10, final UI/security/accessibility/performance review, exact-final-head CI, merge gate, and post-merge main verification complete.

## Current Work
Task 6B — non-streaming public conversation submit/loading/error/retry behavior. Reuse the existing Task 4 `POST /wp-rag-ai-chatbot/v1/chat` authority and keep the browser request schema closed to `bot_id`, `question`, and optional `conversation_id`.

## Next Milestone
M15 — Display Rules/RTL/Accessibility.
