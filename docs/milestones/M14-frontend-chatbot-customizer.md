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
- [ ] Task 7 — streaming public chat UX.
- [ ] Task 8 — administrator visual customizer/live preview.
- [ ] Task 9 — block/direct/fullscreen embedding surfaces.
- [ ] Task 10 — milestone integration, visual verification, review, and closeout.

## TDD Evidence
Per-task RED/GREEN chronology is recorded under `docs/progress/M14-*`. Task 4 final production-composition chronology and invalid checkpoints are summarized in `docs/progress/M14-TASK4-CLOSEOUT.md`; Task 5 mount/build/smoke chronology, including the preserved representation-only NOT RED checkpoint, is recorded in `docs/progress/M14-TASK5-PUBLIC-WIDGET-MOUNT.md`.

## Integration Test Evidence
Task 4 real WordPress REST smoke covers malformed public requests, arbitrary runtime-override rejection, abuse-control ordering, and disabled-bot fail-closed behavior. Task 5 extends real WordPress smoke to prove shortcode registration, no eager assets, invalid/disabled fail-closed mounts, valid conditional asset enqueue, deterministic mount output, and public bootstrap secret/runtime-authority exclusion. Task 5 corrected verification head `18436c394f826e83885cbb43bff30b509a00a205` / CI `34722486144` is GREEN across all permanent jobs.

## E2E / Visual Verification
Pending Tasks 6-10: desktop/tablet/mobile, light/dark/system if implemented, loading/empty/error, long answer/URL, citation/source cards, keyboard.

## Security Review
Task 5 scoped fallback review: 0 Critical / 0 Important unresolved. Public browser bootstrap remains explicitly allow-listed; shortcode input cannot become provider/model/credential/embedding/vector/retrieval authority; invalid/disabled mounts fail closed. XSS/markdown/link handling, custom CSS policy, and UI-specific embed security remain gates for later UI tasks.

## Accessibility Review where UI exists
Task 5 introduced only the mount/bootstrap shell. Accessibility becomes an active implementation/review gate in Task 6 and remains required for Tasks 6-10.

## Performance Review where relevant
Task 5 proves public widget script/style assets are not enqueued globally and are loaded only after a valid public mount resolves. The bootstrap enqueue path is idempotent. Rendering/stream frequency remains a Task 6/7 gate.

## Code Review Findings
Task 5 independent reviewer transport was unavailable; repository-approved scoped fallback correctness/security/performance/accessibility-boundary/architecture review found 0 Critical / 0 Important unresolved. Continue task-scoped review after every meaningful implementation unit.

## Fixes
Task 5 preserves `a1e0ad17905aba666444ce45c8f4db57db834272` / CI `34722293078` as **NOT RED** because its only failure was a smoke assertion representation mismatch for JSON-escaped slashes. The corrected verification-only checkpoint `18436c394f826e83885cbb43bff30b509a00a205` / CI `34722486144` is fully GREEN; no production behavior was rewritten to manufacture RED/GREEN chronology.

## Fresh Verification Commands
CI is authoritative for scheduled connector-only runs. Permanent gates include Composer validation/audit, PHPCS, PHPStan, PHPUnit, JavaScript verification/audit/gating, production package assertion, and real WordPress smoke.

## Fresh Verification Results
Task 5 corrected verification head `18436c394f826e83885cbb43bff30b509a00a205`: CI `34722486144` GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## Commits
See `docs/progress/M14-*` for exact task commit chronology. Active milestone PR is #19 on `feat/m14-frontend-chatbot-customizer`.

## Files Changed
Tracked by PR #19; per-task progress documents identify the focused implementation surfaces.

## Known Limitations
Tasks 6-10 remain unfinished. The mount/bootstrap exists, but the interactive launcher/panel, streaming UX, customizer, broader embed surfaces, and final milestone verification are not complete yet.

## Documentation Updated
`docs/progress/STATUS.md`, per-task M14 evidence records, this milestone ledger, and M14 Superpowers design/plans.

## Completion Checklist
Milestone remains open until Tasks 6-10, final UI/security/accessibility/performance review, exact-final-head CI, merge gate, and post-merge main verification complete.

## Current Work
Task 6 — floating launcher/panel UI. Build the interactive browser widget on the dedicated `src-js/widget.ts` entry and Task 5 bootstrap contract using strict JavaScript TDD, with accessibility and bounded public configuration as first-class gates. Do not create a second chat/retrieval/runtime authority.

## Next Milestone
M15 — Display Rules/RTL/Accessibility.
