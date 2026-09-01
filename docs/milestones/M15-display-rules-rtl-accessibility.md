# M15 — Display Rules, Proactive Triggers, Multilingual/RTL & Accessibility

Status: NOT STARTED

## Goal
Make chatbot presence/engagement deterministic, localized, RTL-capable, responsive, and accessible.

## Dependencies
M14.

## In Scope
Include/exclude URL; post type; role/auth state; Woo areas; device; schedule; delay; scroll; exit intent; inactivity; CSS click; URL pattern; first visit; page starters; deterministic rule engine; RTL/localization; keyboard/screen reader semantics.

## Out of Scope
Building a general marketing automation platform.

## Architecture
Validated rule data + pure/testable evaluation engine; browser-only signals passed as explicit facts.

## Acceptance Criteria
Rules have deterministic unit fixtures; conflicting-rule precedence documented; RTL layouts and keyboard focus work; reduced-motion/accessibility considerations handled; triggers clean up listeners/timers.

## Tasks
Pending plan.

## TDD Evidence
Required rules and UI behavior tests.

## Integration Test Evidence
Widget/rule integration.

## E2E / Visual Verification
Desktop/mobile/RTL/keyboard/screen-reader/trigger scenarios.

## Security Review
CSS selector/input validation; role/auth facts sourced securely where server-sensitive.

## Accessibility Review where UI exists
Primary milestone requirement.

## Performance Review where relevant
Listener/timer efficiency and conditional asset behavior.

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
M16 — Conversations/Leads/Feedback/Forms.
