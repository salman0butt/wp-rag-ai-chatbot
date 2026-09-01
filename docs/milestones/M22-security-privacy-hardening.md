# M22 — Security, Privacy, Abuse Protection & Production Hardening

Status: NOT STARTED

## Goal
Perform a comprehensive adversarial review and close security/privacy/abuse gaps accumulated across implemented features.

## Dependencies
M01-M21.

## In Scope
Capabilities/REST/nonces/validation/escaping/SQL/XSS/CSRF/SSRF/uploads/secrets/logging/rate limiting/cost abuse/spam/prompt/retrieval/tool injection/IDOR/ownership/Woo auth/cross-site authorization; retention/IP/consent/disclosures/export/erasure/WordPress privacy integration; security regression suite.

## Out of Scope
Treating this milestone as permission to leave earlier milestones insecure.

## Architecture
Defense in depth at trust boundaries; centralized primitives only where they reduce mistakes without creating god security managers.

## Acceptance Criteria
Threat model mapped to tests; no unresolved Critical/Important security findings; secrets scan clean; anonymous/authenticated/admin boundaries verified; privacy flows work end-to-end.

## Tasks
Pending hardening plan and broad review.

## TDD Evidence
Regression tests for every fixed vulnerability.

## Integration Test Evidence
Security/ownership/privacy integration required.

## E2E / Visual Verification
Consent/privacy/auth/error workflows where user-facing.

## Security Review
Primary milestone deliverable plus independent review.

## Accessibility Review where UI exists
Privacy/consent UI required.

## Performance Review where relevant
Rate-limit storage/cleanup, security controls under load.

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
M23 — Agency/API/Integrations.
