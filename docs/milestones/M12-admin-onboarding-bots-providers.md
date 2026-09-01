# M12 — Admin Onboarding, Bot Management & Provider Configuration

Status: NOT STARTED

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

## Acceptance Criteria
Admin capabilities enforced; onboarding handles unavailable provider/capability states; multiple bots are isolated; secrets remain write-only/masked; typecheck/build/component/E2E flows pass.

## Tasks
Pending plan.

## TDD Evidence
Backend and component tests required.

## Integration Test Evidence
Admin REST + persistence integration.

## E2E / Visual Verification
Desktop/mobile admin, loading/empty/error, keyboard navigation, provider capability errors.

## Security Review
Capabilities/nonces/REST permissions/secret exposure/CSRF/XSS.

## Accessibility Review where UI exists
Required.

## Performance Review where relevant
Lazy screens, paginated bot lists, no frontend widget asset regression.

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
M13 — Knowledge Manager/Debugger.
