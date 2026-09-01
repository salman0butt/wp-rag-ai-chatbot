# M17 — Human Handoff, Live Support Inbox, Assignments & Routing

Status: NOT STARTED

## Goal
Provide WordPress-native escalation and human takeover while preserving one continuous conversation.

## Dependencies
M16 conversations, M11 chat.

## In Scope
Escalation; human takeover/release; availability; assignments/departments/status; internal notes; notifications; AI handoff summary; transcript; resolution state; routing foundation.

## Out of Scope
Replacing full enterprise contact-center software.

## Architecture
Conversation state machine distinguishes AI/human handling while storing messages in one transcript; internal notes are never exposed publicly or to model context unless explicitly selected.

## Acceptance Criteria
No AI response while human owns conversation unless policy allows; assignment races handled; internal notes private; agent permissions enforced; reconnect state correct.

## Tasks
Pending plan.

## TDD Evidence
Required state/routing tests.

## Integration Test Evidence
Conversation/agent notification integration.

## E2E / Visual Verification
Live inbox states, assignment, takeover, mobile/admin responsiveness, disconnect/reconnect.

## Security Review
Agent capabilities, transcript/PII access, IDOR, notification content leakage.

## Accessibility Review where UI exists
Required.

## Performance Review where relevant
Polling/SSE strategy, inbox pagination, presence expiry.

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
M18 — WooCommerce Conversational Commerce.
