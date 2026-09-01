# M23 — Agency Features, Import/Export, White Label, APIs & Integrations

Status: NOT STARTED

## Goal
Add safe portability, agency workflows, secure cross-site embed, and stable developer integration surfaces.

## Dependencies
Stable product domains through M22.

## In Scope
Multiple-bot clone; config export/import; presets/appearance presets; cross-site embed origin/auth model; optional branding removal/white label; documented hooks/filters/interfaces; external API/webhook integration surfaces where approved.

## Out of Scope
Licensing/payment infrastructure unless separately approved.

## Architecture
Versioned validated configuration schema; import performs capability/security validation; extension points expose stable contracts, not internals.

## Acceptance Criteria
Export/import round-trip versioned config; secrets excluded by default; malicious import rejected; cross-site origins enforced; clone preserves/omits identity-sensitive data correctly; extension docs/tests exist.

## Tasks
Pending plan.

## TDD Evidence
Required.

## Integration Test Evidence
Import/export/embed/API integration.

## E2E / Visual Verification
Agency flows and external embed on representative origins/viewports.

## Security Review
CORS/origin/token design, secret export, unsafe import, privilege boundaries.

## Accessibility Review where UI exists
Required.

## Performance Review where relevant
Multi-bot admin scalability and embed asset caching.

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
M24 — Final Performance/Compatibility/Release Audit.
