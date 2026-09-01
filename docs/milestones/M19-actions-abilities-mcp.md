# M19 — AI Actions, Function Calling, Webhooks, Booking, Abilities & MCP Integration

Status: NOT STARTED

## Goal
Create a typed, auditable, permission-aware action framework and interoperable WordPress Abilities/MCP exposure.

## Dependencies
M03 provider tool capability, M11 chat, domain services including Woo/Leads.

## In Scope
Action registry/definitions/input schema/output shape/risk/authz/timeout/audit/errors; built-ins for approved product/contact/lead/cart/order/webhook/email/booking/external API cases; provider tool-call loop; WordPress Abilities adapters; explicit MCP exposure.

## Out of Scope
Arbitrary PHP/SQL/shell execution; automatic public exposure of privileged abilities.

## Architecture
Model proposes -> schema validate -> authenticate/authorize/risk policy -> execute PHP handler -> audit -> normalized result. Abilities reuse application services.

## Acceptance Criteria
Invalid schemas never execute; anonymous privileged calls rejected; read/write risk policies differ; duplicate/loop limits exist; tool injection from retrieved data cannot bypass authorization; MCP exposure explicit.

## Tasks
Pending plan; likely split due scope.

## TDD Evidence
Mandatory action/security regression tests.

## Integration Test Evidence
Provider tool loop + domain actions + Abilities integration.

## E2E / Visual Verification
Representative approval/confirmation/action states where UI applies.

## Security Review
Primary milestone focus: authn/authz, SSRF, tool injection, webhooks, email abuse, audit, timeouts.

## Accessibility Review where UI exists
Required.

## Performance Review where relevant
Tool loop/call limits and timeouts.

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
M20 — Multimodal/Voice.
