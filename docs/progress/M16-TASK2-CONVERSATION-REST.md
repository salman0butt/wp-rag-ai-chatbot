# M16 Task 2 — Protected Conversation REST Evidence

Status: IN PROGRESS

## Scope

Task 2 exposes the existing M16 conversation administration authorities through capability-protected WordPress REST routes without widening public runtime authority.

Required route surface:

- `GET /wp-rag-ai-chatbot/v1/admin/conversations`;
- `GET /wp-rag-ai-chatbot/v1/admin/conversations/{id}`;
- `DELETE /wp-rag-ai-chatbot/v1/admin/conversations/{id}`;
- all routes use the established `AdminCapability::can_manage` permission callback;
- list/detail/delete callbacks bind only to the existing `ConversationReadRepository`, `ConversationAdminRepository`, and `ConversationRestResource` authorities;
- list/detail inputs remain bounded and malformed inputs fail closed.

## Recovered baseline

- Prior Task 2 projection GREEN: `7aad66985bc2fb97873758babd0212eaa00ef2bb`, CI `34811204273`.
- `ConversationRestResource` already provides safe bounded list/detail/delete projections.
- `WpdbConversationReadRepository` and `WpdbConversationAdminRepository` remain the canonical persistence authorities.
- `AdminRestBootstrap` did not yet register or bind conversation administration routes at this checkpoint.

## Protected-route RED

- RED SHA: `d3933fa8aa4cf212fc266ee48c78214ee15bb1ea`
- CI: `34815743821`
- Composer validation: GREEN.
- PHPCS: GREEN.
- PHPStan: GREEN.
- PHPUnit: RED at the new protected conversation-route contract because the required conversation routes are not registered yet.
- JavaScript quality: GREEN.
- Package: GREEN.
- WordPress smoke: GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, Playground REST, and widget surfaces.

This is genuine behavioral RED because the PHP prerequisite quality gates passed and PHPUnit reached the intended missing-route contract.

The test requires:

- collection `GET` callback `AdminRestBootstrap::list_conversations`;
- member `GET` callback `AdminRestBootstrap::get_conversation`;
- member `DELETE` callback `AdminRestBootstrap::delete_conversation`;
- `AdminCapability::can_manage` on every route.

## Execution limitation

Production implementation was not started after RED because the native repository patch/command transport repeatedly returned a transient HTTP 429 from its execution tunnel. GitHub read/create operations and Actions remained available. No unsafe full-file replacement was used merely to bypass that transient infrastructure failure.

## Exact next unfinished work

Recover `feat/m16-conversations-leads-feedback-forms` before writing. If `d3933fa8aa4cf212fc266ee48c78214ee15bb1ea` remains the behavioral RED authority (with any later documentation-only checkpoint accounted for), implement the minimum protected conversation route registration and callbacks in `AdminRestBootstrap` through the existing `ConversationRestResource`, `WpdbConversationReadRepository`, and `WpdbConversationAdminRepository` seams. Then add/complete bounded request validation, obtain exact-head GREEN across all required CI jobs, perform correctness/security/performance/architecture review, update this evidence file, and continue immediately to the next unfinished M16 unit.
