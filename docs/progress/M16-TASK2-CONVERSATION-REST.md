# M16 Task 2 — Protected Conversation REST Evidence

Status: COMPLETE

## Scope

Task 2 exposes the existing M16 conversation administration authorities through capability-protected WordPress REST routes without widening public runtime authority.

Completed route surface:

- `GET /wp-rag-ai-chatbot/v1/admin/conversations`;
- `GET /wp-rag-ai-chatbot/v1/admin/conversations/{id}`;
- `DELETE /wp-rag-ai-chatbot/v1/admin/conversations/{id}`;
- all routes use the established `AdminCapability::can_manage` permission callback;
- list/detail/delete callbacks bind only to the existing `ConversationReadRepository`, `ConversationAdminRepository`, and `ConversationRestResource` authorities;
- list/detail inputs remain bounded and malformed inputs fail closed.

## Recovered baseline

- Prior Task 2 projection GREEN: `7aad66985bc2fb97873758babd0212eaa00ef2bb`, CI `34811204273`.
- `ConversationRestResource` already provided safe bounded list/detail/delete projections.
- `WpdbConversationReadRepository` and `WpdbConversationAdminRepository` remained the canonical persistence authorities.
- `AdminRestBootstrap` did not yet register or bind conversation administration routes at the initial Task 2 checkpoint.

## Protected-route RED

- RED SHA: `d3933fa8aa4cf212fc266ee48c78214ee15bb1ea`
- CI: `34815743821`
- Composer validation: GREEN.
- PHPCS: GREEN.
- PHPStan: GREEN.
- PHPUnit: RED at the new protected conversation-route contract because the required conversation routes were not registered yet.
- JavaScript quality: GREEN.
- Package: GREEN.
- WordPress smoke: GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, Playground REST, and widget surfaces.

This was genuine behavioral RED because the PHP prerequisite quality gates passed and PHPUnit reached the intended missing-route contract.

The route contract required:

- collection `GET` callback `AdminRestBootstrap::list_conversations`;
- member `GET` callback `AdminRestBootstrap::get_conversation`;
- member `DELETE` callback `AdminRestBootstrap::delete_conversation`;
- `AdminCapability::can_manage` on every route.

## Protected routes and bounded request wiring

The production route/bootstrap work reused the existing conversation authorities and established administrator capability boundary. Subsequent bounded request work added normalized list pagination/filter parsing and wired the normalized `ConversationListQuery` into the canonical read repository without introducing request-controlled SQL identifiers or a parallel administration data path.

- Bounded list/filter checkpoint GREEN: `399842e617ff40b678b2b7a6b3e0a0a366ed0670`
- CI: `34821611979` — GREEN.

The resulting administrator list request contract clamps pagination, bot/unassigned selection, date range, and transcript search through the existing immutable query authority. Detail reads remain capped by the existing 100-message repository/resource boundary. Invalid detail identifiers fail closed as not-found rather than escaping persistence details.

## Invalid-delete RED

A final security/correctness review found that malformed delete identifiers could still surface `InvalidArgumentException` from the canonical administrator repository instead of returning a bounded REST projection.

- RED SHA: `8ce707647df9bb57f0977223e888b8f20046041f`
- CI: `34825243327`
- Composer validation: GREEN.
- PHPCS: GREEN.
- PHPStan: GREEN.
- PHPUnit: genuine behavioral RED at `ConversationRestResourceContractTest::test_delete_maps_invalid_identifier_to_bounded_error`.
- The intended repository exception, including a synthetic sensitive validation detail, escaped the REST resource at RED.
- Package: GREEN.

This was genuine RED: prerequisite PHP quality gates passed and PHPUnit reached exactly the new malformed-delete behavior.

## Final GREEN

The minimum production repair stayed at the REST projection boundary: `ConversationRestResource::delete()` now maps canonical `InvalidArgumentException` validation failures to the stable public error `invalid_conversation_id` / `Conversation identifier is invalid.`. Repository validation remains authoritative; no validation/persistence implementation was duplicated and no exception detail is exposed.

- Final Task 2 implementation GREEN SHA: `302c77e4c0ae7fea49c5007cbee4c3b4d66d937a`
- CI: `34825392498` — GREEN on the exact implementation SHA.
- `php-quality`: GREEN — Composer validation, PHPCS, PHPStan, PHPUnit, and Composer audit.
- `js-quality`: GREEN — JavaScript verification, live provider gating, live Qdrant gating, package assertion.
- `package`: GREEN.
- `wordpress-smoke`: GREEN — activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, Playground REST, and widget surfaces.

## Scoped fallback review

Independent reviewer/subagent transport was unavailable in this execution environment, so no independent review is claimed. Repository-approved scoped fallback review was performed across the complete Task 2 surface.

Findings after the invalid-delete repair:

- Correctness: **0 Critical / 0 Important**. List/detail/delete callbacks use the existing production conversation authorities; list parsing is bounded; detail remains capped; missing and malformed inputs fail closed.
- Security/privacy: **0 Critical / 0 Important**. Every route uses `AdminCapability::can_manage`; owner scope remains internal; no provider credentials/model/retrieval configuration is projected; malformed delete exceptions are converted to stable public errors and do not leak exception text or PII.
- Performance: **0 Critical / 0 Important**. Pagination/search bounds and the existing 100-message detail cap constrain administrator reads; the delete projection adds only malformed-input exception mapping.
- Architecture/duplication: **0 Critical / 0 Important**. REST remains a projection over `ConversationReadRepository`, `ConversationAdminRepository`, and `ConversationRestResource`; canonical database and conversation authorities stay single-source-of-truth.
- Accessibility: not applicable to this transport-only task; Task 3 owns the administrator UI accessibility surface.

## Completion

Task 2 is complete at the verified implementation checkpoint above. Documentation-only status commits that follow must receive their own exact-head CI evidence before being treated as the latest verified branch head.

## Exact next unfinished work

M16 Task 3 — administrator inbox/detail UI. Start test-first with the smallest route/state contract for the conversation inbox and detail transcript, then add pagination, search, bot/date filters, empty/error states, stale-response guards, semantic transcript rendering, delete confirmation, and focus restoration through the existing protected conversation REST surface.