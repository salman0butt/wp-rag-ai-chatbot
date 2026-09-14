# M16 Task 3 — Conversation Admin Inbox / Detail UI

Status: IN PROGRESS

## Scope
Build the protected administrator conversations inbox/detail experience on top of the existing M16 REST/read authorities. Keep request construction bounded, reuse the established admin API client, and do not introduce a second conversation/transcript authority.

## Completed slices

### Route state
Conversation hash routing now resolves bounded page state plus an optional decoded conversation selection. Exact-head implementation SHA `69945fb04b2ff0e4d9ed225c814801b586339373` passed permanent CI run `34846678203`.

### Inbox list request state
RED: `e67b75b6835c95876644dddf832297191e89ef57`, CI `34850203407`. JavaScript lint completed successfully and TypeScript then failed only because `./conversation-admin-state` did not yet exist. This is the intended missing behavior.

Implementation checkpoint: `56505c09f5dc7cf5b1c9f04a2107a4311e61f58a`, CI `34850420473`: **NOT GREEN**. `lint:js` stopped on two Prettier formatting errors before typecheck/tests; no GREEN claim is made for this SHA.

Formatting repair / GREEN: `e46a4de03b365e026e11e78191747fb23ebfc732`, CI `34850588833`. `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed on the exact SHA.

Behavior: `buildConversationListPath()` serializes the UI page plus optional bot/date/search/unassigned filters to the protected `/admin/conversations` endpoint, trims optional values, keeps page size fixed at 25, normalizes invalid UI page state to page 1, and makes `unassigned_only` mutually exclusive with `bot_id`. Server-side `ConversationListRequest` remains the validation/authorization authority.

## Review
Independent reviewer transport is unavailable in this runtime, so no independent review is claimed.

Repository-approved fallback scoped correctness/security/performance/accessibility/architecture review for the list-state slice: 0 Critical, 0 Important findings.

- Correctness: query parameter names/order match the existing protected M16 REST contract; optional values are trimmed; URLSearchParams performs encoding; unassigned mode suppresses a bot filter.
- Security/privacy: this module accepts no credentials, provider/model/retrieval overrides, SQL identifiers, or transcript data. The server remains authoritative for validation and capability enforcement.
- Performance: pure bounded string serialization with fixed page size; no I/O or unbounded work.
- Accessibility: no UI is rendered in this slice; accessibility review applies to the upcoming screen controls.
- Architecture/duplication: this is a UI request serializer only and reuses the existing protected REST/read authority rather than duplicating conversation retrieval.

## Exact next unfinished unit
Add the first presentational inbox behavior with Jest coverage for semantic conversation list rendering, pagination, and labelled search/bot/date controls. Then wire data loading with stale-response generation guards and detail rendering before delete confirmation/focus restoration.
