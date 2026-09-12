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
- [ ] Task 5 — conditional public asset/bootstrap/shortcode mount.
- [ ] Task 6 — floating launcher/panel UI.
- [ ] Task 7 — streaming public chat UX.
- [ ] Task 8 — administrator visual customizer/live preview.
- [ ] Task 9 — block/direct/fullscreen embedding surfaces.
- [ ] Task 10 — milestone integration, visual verification, review, and closeout.

## TDD Evidence
Per-task RED/GREEN chronology is recorded under `docs/progress/M14-*`. Task 4 final production-composition chronology and invalid checkpoints are summarized in `docs/progress/M14-TASK4-CLOSEOUT.md`.

## Integration Test Evidence
Task 4 real WordPress REST smoke covers malformed public requests, arbitrary runtime-override rejection, abuse-control ordering, and disabled-bot fail-closed behavior. Final Task 4 head `e850863d5487ee7603547413bdb9c667c8d04b8e` / CI `34717786952` is GREEN across all permanent jobs.

## E2E / Visual Verification
Pending Tasks 6-10: desktop/tablet/mobile, light/dark/system if implemented, loading/empty/error, long answer/URL, citation/source cards, keyboard.

## Security Review
Task 4 scoped fallback review: 0 Critical / 0 Important unresolved. Public request parsing is closed; runtime/provider/model/retrieval authority remains server-side; conversation history is owner-scoped and bounded. XSS/markdown/link handling, custom CSS policy, and UI-specific embed security remain gates for later UI tasks.

## Accessibility Review where UI exists
Required for Tasks 6-10. Task 4 introduced no visual UI.

## Performance Review where relevant
Task 4: 0 Critical / 0 Important unresolved; retrieval/history bounds remain explicit and no duplicate RAG execution path was added. Task 5 must prove conditional asset loading.

## Code Review Findings
Task 4 independent reviewer transport was unavailable; repository-approved scoped fallback review found 0 Critical / 0 Important unresolved. Continue task-scoped review after every meaningful implementation unit.

## Fixes
Task 4 preserved invalid NOT RED / NOT GREEN checkpoints honestly and repaired formatting/static-analysis issues before final green integration head. See per-task evidence.

## Fresh Verification Commands
CI is authoritative for scheduled connector-only runs. Permanent gates include Composer validation/audit, PHPCS, PHPStan, PHPUnit, JavaScript verification/audit/gating, production package assertion, and real WordPress smoke.

## Fresh Verification Results
Task 4 exact final head `e850863d5487ee7603547413bdb9c667c8d04b8e`: CI `34717786952` GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## Commits
See `docs/progress/M14-*` for exact task commit chronology. Active milestone PR is #19 on `feat/m14-frontend-chatbot-customizer`.

## Files Changed
Tracked by PR #19; per-task progress documents identify the focused implementation surfaces.

## Known Limitations
Tasks 5-10 remain unfinished. Public visual mount/UI/streaming/customizer/embed surfaces are not milestone-complete yet.

## Documentation Updated
`docs/progress/STATUS.md`, per-task M14 evidence records, this milestone ledger, and M14 Superpowers design/plans.

## Completion Checklist
Milestone remains open until Tasks 5-10, final UI/security/accessibility/performance review, exact-final-head CI, merge gate, and post-merge main verification complete.

## Current Work
Task 5 — conditional public asset/bootstrap/shortcode mount. Recover existing bootstrap/build/enqueue and shortcode conventions; plan the smallest bounded seam; execute strict TDD and continue through safe unfinished units.

## Next Milestone
M15 — Display Rules/RTL/Accessibility.
