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

### Inbox presentation
Initial test checkpoint `62431837d4f768e49582611b4064b32965b0998e`, CI `34851138461`: **NOT RED**. Prettier rejected the new test before TypeScript could reach the intended missing component.

Formatting checkpoint `4e3176970a4e1c62b4419185c494d92eadb9d33e`, CI `34851307732`: **NOT RED**. One remaining Prettier failure still prevented the intended behavior failure.

RED: `6fe80dbea6d32c822ee630039b8d72ba1c8ecaae`, CI `34851535437`. Lint passed and TypeScript failed only because `./conversation-inbox-screen` did not exist. This is the intended missing behavior.

Implementation checkpoint `8cd1cd3eb57d1b11f524f129d95906cbd7f32701`, CI `34851705773`: **NOT GREEN**. Lint and typecheck passed and the full Jest suite reached the new presentation tests, but the test-only element factory incorrectly stringified nested DOM nodes, so the expected labels/empty state were not represented in the fixture DOM.

Harness repair / GREEN: `151753f068fb9202f8c26e051d36a25b49b0bf6b`, CI `34851886474`. `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` suite all passed on the exact SHA.

Behavior: `ConversationInboxScreen()` now provides semantic conversation rows, a stable empty state, explicit search/bot/from/to labels associated with native inputs, singular/plural message counts, unassigned-bot labeling, and bounded Previous/Next pagination controls. The component is presentation-only and does not introduce a second conversation data authority.

## Review
Independent reviewer transport is unavailable in this runtime, so no independent review is claimed.

Repository-approved fallback scoped correctness/security/performance/accessibility/architecture review for the list-state slice: 0 Critical, 0 Important findings.

- Correctness: query parameter names/order match the existing protected M16 REST contract; optional values are trimmed; URLSearchParams performs encoding; unassigned mode suppresses a bot filter.
- Security/privacy: this module accepts no credentials, provider/model/retrieval overrides, SQL identifiers, or transcript data. The server remains authoritative for validation and capability enforcement.
- Performance: pure bounded string serialization with fixed page size; no I/O or unbounded work.
- Accessibility: no UI is rendered in this slice; accessibility review applies to the upcoming screen controls.
- Architecture/duplication: this is a UI request serializer only and reuses the existing protected REST/read authority rather than duplicating conversation retrieval.

Repository-approved fallback scoped review for the inbox presentation slice: 0 Critical, 0 Important findings.

- Correctness: rendering is deterministic from server-projected summary data and preserves bounded page state without synthesizing transcript data.
- Security/privacy: no raw HTML injection, credentials, model/provider overrides, or direct storage access are introduced; text values are rendered through element children.
- Performance: rendering is linear in the already bounded page response and performs no network work itself.
- Accessibility: filters use explicit visible labels associated with native inputs; pagination is grouped under a labelled `nav`; disabled states are native button states; empty state remains textual and stable.
- Architecture/duplication: the screen is presentation-only and is intended to consume the existing protected REST authority rather than duplicate conversation querying.

## Exact next unfinished unit
Wire protected conversation list/detail loading into the admin shell with stale-response generation guards so older list/detail responses cannot replace newer navigation/filter state. Then complete detail transcript rendering and explicit delete confirmation/focus restoration.
