# M12 — Admin Onboarding, Bot Management & Provider Configuration

Status: IN PROGRESS

## Goal
Build a professional WordPress-native admin shell, onboarding, multi-bot management, and provider/model configuration UI.

## Dependencies
M03, M11 backend contracts, M01 frontend tooling.

## In Scope
Admin navigation/shell; onboarding; bot CRUD/config; provider credential/model/capability UI; validation/errors; settings progressive disclosure; usage-safe model selection.

## Out of Scope
Knowledge/debugger UI M13; appearance editor M14; analytics M21.

## Architecture
React/TS where useful, backed by granular capability-protected REST endpoints; no secrets returned to JS.

Selected design: `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md`.
Implementation plan: `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md`.
Both are **AUTO-APPROVED — SCHEDULED MODE**.

## Acceptance Criteria
Admin capabilities enforced; onboarding handles unavailable provider/capability states; multiple bots are isolated; secrets remain write-only/masked; typecheck/build/component/E2E flows pass.

## Tasks
1. **ACTIVE** — Admin foundation and capability-protected REST bootstrap.
2. Bot aggregate/repository and persistence.
3. Bot CRUD REST resources with pagination/isolation.
4. Provider credential/configuration REST resource with write-only secrets.
5. Provider model/capability and onboarding-readiness resources.
6. React admin shell and typed API layer.
7. Onboarding flow.
8. Bot management screens.
9. Provider/model configuration screens.
10. Integration/E2E, security, accessibility, performance, review, durable closeout.

## TDD Evidence
Task 1 RED pending. Backend and component tests required throughout.

## Integration Test Evidence
Admin REST + persistence integration required.

## E2E / Visual Verification
Desktop/mobile admin, loading/empty/error, keyboard navigation, provider capability errors.

## Security Review
Capabilities/nonces/REST permissions/secret exposure/CSRF/XSS.

## Accessibility Review where UI exists
Required.

## Performance Review where relevant
Lazy screens, paginated bot lists, no frontend widget asset regression.

## Code Review Findings
Pending implementation/review.

## Fixes
Pending.

## Fresh Verification Commands
Pending actual execution/evidence.

## Fresh Verification Results
`main` recovery SHA `3bd73cb99efc1f051c884f8f522f08b0c9938a84` had exact-SHA `php-quality`, `js-quality`, `package`, and `wordpress-smoke` checks GREEN before M12 branch creation.

## Commits
- `f0176ed61d1341dec0e134a300613f384d2cc8e3` — M12 design.
- `8439d6be7f622a3291c92a0c22cd8e1bdf9eb983` — M12 implementation plan.

## Files Changed
Planning/durable-state files only so far.

## Known Limitations
No M12 production behavior has been implemented yet. Task 1 must begin with genuine behavioral RED evidence.

## Documentation Updated
M12 design, implementation plan, milestone ledger, and global status checkpoint.

## Completion Checklist
All mandatory gates remain open until Task 1-10 implementation, verification, review, exact-final-SHA CI, merge, and post-merge `main` CI complete.

## Next Milestone
M13 — Knowledge Manager/Debugger.
