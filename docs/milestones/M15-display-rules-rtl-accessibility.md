# M15 — Display Rules, Proactive Triggers, Multilingual/RTL & Accessibility

Status: IN PROGRESS

## Goal
Make chatbot presence/engagement deterministic, localized, RTL-capable, responsive, and accessible.

## Dependencies
M14 — COMPLETE.

## In Scope
Include/exclude URL; post type; role/auth state; Woo areas; device; schedule; delay; scroll; exit intent; inactivity; CSS click; URL pattern; first visit; page starters; deterministic rule engine; RTL/localization; keyboard/screen reader semantics.

## Out of Scope
Building a general marketing automation platform.

## Architecture
Validated rule data + one pure/testable TypeScript evaluation engine; browser-only signals passed as explicit bounded facts. PHP owns validation/normalization/persistence/public projection, not a duplicate evaluation engine.

## Acceptance Criteria
Rules have deterministic unit fixtures; conflicting-rule precedence documented; RTL layouts and keyboard focus work; reduced-motion/accessibility considerations handled; triggers clean up listeners/timers.

## Tasks

1. **COMPLETE** — deterministic TypeScript display-rule domain: defaults/disable, URL precedence, audience/post/Woo/device, site-time schedules, page starters, locale/direction.
2. **IN PROGRESS** — immutable PHP normalized config, bot persistence, protected admin REST, public projection.
3. **PENDING** — WordPress server context fact projection.
4. **PENDING** — runtime visibility integration using the Task 1 authority exactly once.
5. **PENDING** — proactive trigger coordinator.
6. **PENDING** — browser-signal integrations and lifecycle cleanup.
7. **PENDING** — localized/RTL widget semantics and accessible presentation.
8. **PENDING** — admin configuration/preview integration.
9. **PENDING** — end-to-end/accessibility/performance/security verification and closeout.

Detailed executable plan: `docs/superpowers/plans/2026-09-13-m15-display-rules-rtl-accessibility.md`.

## TDD Evidence
Task 1 evidence: `docs/progress/M15-TASK1-DISPLAY-RULES.md`.

Task 1 exact final implementation GREEN: `e9d4adbd076333142e000a38332cfdc8fd9e8853`, CI `34755743017` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN.

Task 1 includes honest NOT RED/NOT GREEN chronology and one Important schedule review finding resolved through a dedicated regression RED→GREEN cycle.

## Integration Test Evidence
Task 1 pure-domain integration/regression coverage is GREEN. Full widget/context integration remains Tasks 3–4.

## E2E / Visual Verification
Pending later milestone tasks: desktop/mobile/RTL/keyboard/screen-reader/trigger scenarios.

## Security Review
Task 1: 0 unresolved Critical/Important findings. Display policy remains presentation-only; no user object/credentials/provider/model/embedding/vector authority is exposed.

## Accessibility Review where UI exists
Task 1 is pure-domain only. Runtime `lang`/`dir`, localized labels, focus/reading order, reduced motion and keyboard semantics remain later tasks.

## Performance Review where relevant
Task 1 uses bounded collections/patterns/prompts and deterministic matching; 0 unresolved Critical/Important findings.

## Code Review Findings
Task 1D review found one Important empty-schedule semantic defect; resolved and exact-head GREEN. No unresolved Critical/Important Task 1 findings.

## Fixes
Task 1 review regression fix: `32d50bd996d314b3513766ad4ff1e855c068f978`.

## Fresh Verification Commands
Permanent GitHub CI gates: `php-quality`, `js-quality`, `package`, `wordpress-smoke`.

## Fresh Verification Results
Task 1 exact implementation SHA `e9d4adbd076333142e000a38332cfdc8fd9e8853`: CI `34755743017` fully GREEN.

## Known Limitations
Task 2+ remains unfinished. Existing early runtime display-rule integration must be reconciled at Task 4 so the final runtime invokes the pure evaluator exactly once with server/browser facts.

## Documentation Updated
- `docs/progress/M15-TASK1-DISPLAY-RULES.md`
- `docs/superpowers/specs/2026-09-13-m15-display-rules-rtl-accessibility-design.md`
- `docs/superpowers/plans/2026-09-13-m15-display-rules-rtl-accessibility.md`
- this milestone ledger

## Completion Checklist
M15 is not complete. Task 1 is complete; Task 2A is the current unfinished unit.

## Next Unfinished Unit
Task 2A — immutable PHP `DisplayRulesConfig`: establish PHPUnit RED for defaults, strict shape/unknown-key rejection, finite enums and bounded collection/string/timer/scroll/glob/selector/prompt/localization normalization, then implement following recovered M14 PHP config conventions.

## Next Milestone
M16 — Conversations/Leads/Feedback/Forms, only after M15 is genuinely complete and merged/post-merge verified.
