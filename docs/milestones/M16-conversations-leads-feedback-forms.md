# M16 — Conversations, Leads, Feedback & Conversational Forms

Status: IN PROGRESS

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
- Task 1 — Conversation admin read model + explicit bot association: IN PROGRESS
  - 1A explicit bot association + public creation path: COMPLETE
  - 1B bounded admin conversation query contract: NEXT
  - 1C detail projection and delete semantics: PENDING
- Task 2 — Protected admin conversation REST: PENDING
- Task 3 — Admin inbox/detail UI: PENDING
- Task 4 — Lead/contact capture: PENDING
- Task 5 — Conversation ratings/feedback: PENDING
- Task 6 — Bot-scoped custom forms: PENDING
- Task 7 — Visitor runtime integration: PENDING
- Task 8 — CSV export: PENDING
- Task 9 — Permanent integration and closeout: PENDING

## TDD Evidence
### Task 1A — explicit bot association
- Repository persistence RED: `b9ea7f9dd8d0a485a1c69f4077109656f436002b`, CI `34797327559`. PHPCS/PHPStan passed; PHPUnit failed on missing persisted `bot_id` (`null` vs `bot-1`).
- Migration RED: `77d22d34c93f53db061a545ba5a985f2e251f05a`, CI `34797648258`. PHPCS/PHPStan passed; PHPUnit failed because schema version 14, `V014AddConversationBotAssociation`, and the `bot_id` schema column were absent.
- Public creation-path RED: `125a465911317e2ae86c8cfaf5dae97adfe8e2b9`, CI `34800896785`. PHPCS/PHPStan passed; PHPUnit failed because the production public executor forwarded `conversation_id = null` instead of creating an explicitly bot-associated conversation.
- NOT GREEN: `9d9500765cb0533fae3f65eb9dccac1bf16a339b`, CI `34798052111`. PHPCS stopped PHP quality on assignment alignment before PHPStan/PHPUnit; repaired without rewriting history.
- Final Task 1A GREEN: `7be973f5d97b043af5d962cb1e00f0833ecea403`, CI `34801027067`. `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed on the exact implementation SHA.

## Integration Test Evidence
Task 1A exact-head WordPress smoke in CI `34801027067` passed activation, database, providers, knowledge, file-ingestion, WooCommerce knowledge, Playground REST, and widget-surface smoke checks.

## E2E / Visual Verification
Pending UI-bearing M16 tasks.

## Security Review
Task 1A fallback scoped review: no Critical or Important findings. New public conversations persist only the validated route bot ID plus trusted owner scope; historical unassigned rows remain valid; no `owner_scope` parsing/backfill guessing was introduced; existing conversation IDs are not recreated. Independent reviewer transport was unavailable in this runtime, so no independent review is claimed.

## Accessibility Review where UI exists
Pending UI-bearing M16 tasks.

## Performance Review where relevant
Task 1A adds one bounded conversation insert only when a new public conversation is created; existing-conversation requests do not add that write. No Important performance finding.

## Code Review Findings
Task 1A fallback correctness/security/performance/architecture review: 0 Critical, 0 Important. The production path reuses `ConversationRepository`, `WpdbConversationRepository`, `ProductionPublicChatExecutor`, and the existing runtime composition root rather than creating a parallel chat or persistence path.

## Fixes
- Added backward-compatible nullable `bot_id` to conversation identity/repository persistence and schema version 14 migration.
- Hydrated historical rows with `bot_id = null` as unassigned.
- Composed `WpdbConversationRepository` into the existing public production executor resolver.
- New public chats now create an owner-scoped conversation with the explicit validated route bot ID before running the existing M11 responder exactly once.

## Fresh Verification Commands
Permanent CI workflow gates on exact Task 1A implementation SHA: Composer validation, PHPCS, PHPStan, PHPUnit, Composer audit, JavaScript verification/audit/live-gating/package assertion, package build/assertion, and full WordPress smoke.

## Fresh Verification Results
Task 1A exact-head CI `34801027067`: SUCCESS.

## Commits
Task 1A includes RED checkpoints and implementation commits from `b9ea7f9dd8d0a485a1c69f4077109656f436002b` through `7be973f5d97b043af5d962cb1e00f0833ecea403`, preserving invalid NOT GREEN evidence explicitly.

## Files Changed
Task 1A touched conversation domain/repository/schema/migration coverage plus the existing public executor/resolver/bootstrap path and focused unit tests.

## Known Limitations
Conversation admin read/list/detail/delete, lead capture, feedback, forms, exports, and associated UI are not implemented yet; they remain later M16 tasks.

## Documentation Updated
This ledger records Task 1A RED/GREEN/review evidence and activates Task 1B.

## Completion Checklist
M16 remains open. Task 1A is complete; Tasks 1B-9 remain.

## Next Milestone
M17 — Human Handoff.
