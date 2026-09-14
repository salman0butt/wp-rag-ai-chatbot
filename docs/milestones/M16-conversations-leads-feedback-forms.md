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
  - 1B bounded admin conversation query contract: IN PROGRESS
    - pagination bounds authority: COMPLETE
    - immutable list summary projection: COMPLETE
    - canonical admin list read repository: COMPLETE
    - bot/date/transcript filters: NEXT
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

### Task 1B — bounded admin conversation query contract
- Pagination RED: `e926ce31f891eda7650c9abff2a02509fbd46f9b`, CI `34801469329`. PHPCS and PHPStan passed; PHPUnit failed only because `ConversationListQuery` was missing.
- NOT GREEN: `e1d2a20d8ee260715e8f85e631872932eb62451a`, CI `34801584479`. PHPStan rejected reassignment of promoted readonly properties before PHPUnit ran.
- NOT GREEN: `513438c66e9844843cbeccfbc4a5656a3eaeec9e`, CI `34801891777`. PHPCS stopped on missing `@var` property documentation.
- NOT GREEN: `5727e17ec77b7686710d8538c346f31c46a58da3`, CI `34801950925`. PHPCS required property-doc short descriptions before PHPStan/PHPUnit.
- Pagination GREEN: `1ce2c82609af07f7b29fc685dadfb79304679014`, CI `34802027933`. Composer validation, PHPCS, PHPStan, PHPUnit, Composer audit, JavaScript quality/live-gating/package assertions, package build/assertion, and the complete WordPress smoke suite all passed on the exact SHA.
- Summary projection RED: `da136efb817b3724104d0cc9dde950c6c2fedac4`, CI `34802297133`. PHPCS and PHPStan passed; PHPUnit failed only because `ConversationSummary` was missing.
- Summary projection GREEN: `c2d2d5725b5a67f38c6c307e9e83fc2100d4f932`, CI `34802423148`. PHP quality, JavaScript quality, package, and full WordPress smoke all passed on the exact SHA.
- Read repository initial checkpoint: `0c5d1b2c5a02666a890da28343331901622547b9`, CI `34802687164`: **NOT RED**. PHPCS stopped on two test-array alignment warnings before PHPStan/PHPUnit.
- Read repository RED: `5a047f23f5055a367daa263a0c64f100b50d8400`, CI `34802796170`. Composer validation, PHPCS and PHPStan passed; PHPUnit failed only because `WpdbConversationReadRepository` was missing.
- Read contract intermediate implementation: `acbccce14986d591034b98e19f8aba7848ca0f84`; this was an implementation checkpoint, not claimed GREEN.
- Read repository GREEN: `ae185b5f55c954140f230a72c12e19185392d5a1`, CI `34802886455`. PHP quality, JavaScript quality, package, and full WordPress smoke all passed on the exact SHA.

## Integration Test Evidence
Task 1A exact-head WordPress smoke in CI `34801027067` passed activation, database, providers, knowledge, file-ingestion, WooCommerce knowledge, Playground REST, and widget-surface smoke checks.

Task 1B pagination exact-head WordPress smoke in CI `34802027933` passed activation, database, providers, knowledge, file-ingestion, WooCommerce knowledge, Playground REST, and widget-surface smoke checks.

Task 1B summary exact-head WordPress smoke in CI `34802423148` passed activation, database, providers, knowledge, file-ingestion, WooCommerce knowledge, Playground REST, and widget-surface smoke checks.

Task 1B read-repository exact-head WordPress smoke in CI `34802886455` passed activation, database, providers, knowledge, file-ingestion, WooCommerce knowledge, Playground REST, and widget-surface smoke checks.

## E2E / Visual Verification
Pending UI-bearing M16 tasks.

## Security Review
Task 1A fallback scoped review: no Critical or Important findings. New public conversations persist only the validated route bot ID plus trusted owner scope; historical unassigned rows remain valid; no `owner_scope` parsing/backfill guessing was introduced; existing conversation IDs are not recreated. Independent reviewer transport was unavailable in this runtime, so no independent review is claimed.

Task 1B pagination fallback scoped review: 0 Critical, 0 Important. `ConversationListQuery` normalizes immutable page controls to page >= 1 and page size 1-100 before a repository can consume them; this slice introduces no SQL, credentials, owner-scope changes, or request-controlled identifiers. Independent reviewer transport was unavailable in this runtime, so no independent review is claimed.

Task 1B summary fallback scoped review: 0 Critical, 0 Important. `ConversationSummary` is immutable and exposes only conversation identity, explicit nullable bot association, start/latest timestamps, and message count; it does not expose owner scope or message content. Independent reviewer transport was unavailable in this runtime, so no independent review is claimed.

Task 1B read-repository fallback scoped review: 0 Critical, 0 Important. The dedicated repository reads only canonical conversation/message tables, joins messages on both `conversation_id` and `owner_scope`, uses prepared `%i`/`%d` placeholders, exposes no owner scope/message content through the summary DTO, and does not accept request-controlled SQL identifiers. Historical unassigned rows remain valid. Independent reviewer transport was unavailable in this runtime, so no independent review is claimed.

## Accessibility Review where UI exists
Pending UI-bearing M16 tasks.

## Performance Review where relevant
Task 1A adds one bounded conversation insert only when a new public conversation is created; existing-conversation requests do not add that write. No Important performance finding.

Task 1B pagination adds constant-time scalar normalization only. No Important performance finding.

Task 1B summary is an immutable in-memory DTO with no I/O. No Important performance finding.

Task 1B read repository limits the returned rows and uses the existing `conversation_owner_id (conversation_id, owner_scope, id)` message index for the canonical join. The aggregate currently ranks the eligible conversation set before `LIMIT`; bot/date/search filtering remains the next Task 1B work and should be used to constrain admin queries. No Critical/Important performance finding for this slice.

## Code Review Findings
Task 1A fallback correctness/security/performance/architecture review: 0 Critical, 0 Important. The production path reuses `ConversationRepository`, `WpdbConversationRepository`, `ProductionPublicChatExecutor`, and the existing runtime composition root rather than creating a parallel chat or persistence path.

Task 1B pagination fallback correctness/security/performance/architecture review: 0 Critical, 0 Important. The immutable query object is a bounded domain authority and does not duplicate persistence or admin transport concerns.

Task 1B summary fallback correctness/security/performance/architecture review: 0 Critical, 0 Important. The projection remains separate from the M11 write repository and is suitable for the dedicated read repository required by the M16 plan.

Task 1B read-repository fallback correctness/security/performance/architecture review: 0 Critical, 0 Important. The new `ConversationReadRepository` keeps M11 write persistence narrow; `WpdbConversationReadRepository` reuses `Connection` and `TableNames`, performs stable latest-activity ordering with a deterministic conversation-id row tiebreak, preserves nullable bot association, and normalizes database row types before projection.

## Fixes
- Added backward-compatible nullable `bot_id` to conversation identity/repository persistence and schema version 14 migration.
- Hydrated historical rows with `bot_id = null` as unassigned.
- Composed `WpdbConversationRepository` into the existing public production executor resolver.
- New public chats now create an owner-scoped conversation with the explicit validated route bot ID before running the existing M11 responder exactly once.
- Added immutable conversation-list pagination normalization with page >= 1 and page size bounded to 1-100; repaired readonly/static-analysis and WPCS prerequisite failures without rewriting TDD history.
- Added immutable `ConversationSummary` list projection with nullable historical bot association and latest-message timestamp.
- Added dedicated canonical conversation administration read contract/repository with stable recency pagination and message aggregates, without expanding the M11 write repository.

## Fresh Verification Commands
Permanent CI workflow gates on exact implementation SHAs: Composer validation, PHPCS, PHPStan, PHPUnit, Composer audit, JavaScript verification/audit/live-gating/package assertion, package build/assertion, and full WordPress smoke.

## Fresh Verification Results
Task 1A exact-head CI `34801027067`: SUCCESS.

Task 1B pagination exact-head CI `34802027933`: SUCCESS.

Task 1B summary exact-head CI `34802423148`: SUCCESS.

Task 1B read repository exact-head CI `34802886455`: SUCCESS.

## Commits
Task 1A includes RED checkpoints and implementation commits from `b9ea7f9dd8d0a485a1c69f4077109656f436002b` through `7be973f5d97b043af5d962cb1e00f0833ecea403`, preserving invalid NOT GREEN evidence explicitly.

Task 1B pagination includes RED `e926ce31f891eda7650c9abff2a02509fbd46f9b`, implementation/static-analysis checkpoint `e1d2a20d8ee260715e8f85e631872932eb62451a`, repair checkpoints `513438c66e9844843cbeccfbc4a5656a3eaeec9e` and `5727e17ec77b7686710d8538c346f31c46a58da3`, and verified GREEN `1ce2c82609af07f7b29fc685dadfb79304679014`.

Task 1B summary includes RED `da136efb817b3724104d0cc9dde950c6c2fedac4` and verified GREEN `c2d2d5725b5a67f38c6c307e9e83fc2100d4f932`.

Task 1B read repository includes invalid NOT RED `0c5d1b2c5a02666a890da28343331901622547b9`, formatting repair/genuine RED `5a047f23f5055a367daa263a0c64f100b50d8400`, intermediate contract `acbccce14986d591034b98e19f8aba7848ca0f84`, and verified GREEN `ae185b5f55c954140f230a72c12e19185392d5a1`.

## Files Changed
Task 1A touched conversation domain/repository/schema/migration coverage plus the existing public executor/resolver/bootstrap path and focused unit tests.

Task 1B adds `ConversationListQuery`, `ConversationSummary`, `ConversationReadRepository`, `WpdbConversationReadRepository`, focused unit coverage, and durable evidence updates.

## Known Limitations
Task 1B still needs bounded bot/date/transcript-search filters on the canonical read repository. Conversation detail/delete, lead capture, feedback, forms, exports, and associated UI remain later M16 work.

## Documentation Updated
This ledger records Task 1A evidence plus Task 1B pagination, summary, and canonical read-repository RED/NOT RED/GREEN chronology, fallback review, and exact next unfinished work.

## Completion Checklist
M16 remains open. Task 1A plus Task 1B pagination, immutable list-summary, and canonical read-repository slices are complete; remaining Task 1B filter/search work plus Tasks 1C-9 remain.

## Next Milestone
M17 — Human Handoff.
