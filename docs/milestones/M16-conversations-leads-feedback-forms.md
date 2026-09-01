# M16 — Conversations, Leads, Feedback & Conversational Forms

Status: NOT STARTED

## Goal
Add operational conversation review, lead capture, response feedback, and focused conversational forms.

## Dependencies
M11-M15, M02 persistence.

## In Scope
Conversation/message admin; lead fields/status/export/notifications/webhooks; before/inside/after capture; thumbs/report/unanswered review; linked conversation; forms with text/email/phone/number/textarea/select/radio/checkbox, validation, conditional steps.

## Out of Scope
Huge general-purpose form builder/CRM.

## Architecture
Conversation/lead/form domains are separate but linked by IDs/events. Personal data retention/consent metadata is explicit.

## Acceptance Criteria
Ownership/admin permissions pass; exports escape CSV safely; validation is server-authoritative; webhook/email failures are observable; feedback can inspect retrieved sources and corrected knowledge flow where designed.

## Tasks
Pending plan.

## TDD Evidence
Required.

## Integration Test Evidence
Persistence/REST/notification/webhook integration.

## E2E / Visual Verification
Conversation list/detail, lead/form flows, validation/error/mobile/keyboard.

## Security Review
PII, CSRF, spam, CSV injection, webhook SSRF/signing, IDOR.

## Accessibility Review where UI exists
Required.

## Performance Review where relevant
Pagination/retention/export batching.

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
M17 — Human Handoff.
