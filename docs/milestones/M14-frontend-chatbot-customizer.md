# M14 — Frontend Floating/Embedded Chatbot & Complete Visual Customizer

Status: NOT STARTED

## Goal
Deliver the production public chatbot surfaces and shared live appearance customizer.

## Dependencies
M11 backend chat, M12 admin shell.

## In Scope
Floating launcher; embedded/fullscreen/mobile; shortcode; Gutenberg block; streaming messages; markdown/links/citations; quick replies; product-card rendering foundation; loading/errors/retry/session/history where enabled; full appearance schema; live preview; custom CSS; theme support.

## Out of Scope
Advanced proactive rules M15; commerce actions M18.

## Architecture
Compiled React/TS widget consumes public-safe bot config and normalized chat stream; appearance schema shared by preview/runtime.

## Acceptance Criteria
No provider secrets in bundles/HTML/API; widget works desktop/mobile; long answers/URLs/source cards don't overflow; assets only load when applicable; appearance preview matches runtime.

## Tasks
Pending plan.

## TDD Evidence
Backend/component behavior tests required.

## Integration Test Evidence
REST/stream/widget integration.

## E2E / Visual Verification
Desktop/tablet/mobile, light/dark/system if implemented, loading/empty/error, long answer/URL, citation/source cards, keyboard.

## Security Review
XSS/markdown/link handling, public config exposure, session ownership, custom CSS policy, cross-site embed deferred security.

## Accessibility Review where UI exists
Required.

## Performance Review where relevant
Conditional asset loading, bundle size, rendering/stream frequency.

## Code Review Findings
Pending.

## Fixes
Pending.

## Fresh Verification Commands
Pending.

## Fresh Verification Results
Pending.

## Commits
Pending.

## Files Changed
Pending.

## Known Limitations
Pending.

## Documentation Updated
Pending.

## Completion Checklist
All mandatory gates.

## Next Milestone
M15 — Display Rules/RTL/Accessibility.
