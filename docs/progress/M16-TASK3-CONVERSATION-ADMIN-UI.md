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

### Request generation guard and detail presentation
The existing M16 Task 3 branch now includes the conversation admin loader/generation guard plus bounded transcript/detail presentation with an explicit confirmation dialog before deletion. The loader owns list/detail request generations so stale completions cannot overwrite newer navigation state; the detail screen consumes the protected server projection rather than constructing a second transcript authority.

Latest pre-composition exact-head GREEN: `39171ed7ce1b827151a9c3ded5a80dd7408ced3c`, CI `34867130525`.

### Admin-shell composition
RED: `99c2139ad703ee2818c8a7c8b4f4afe18ca211f4`, CI `34868166945`. Package, PHP and WordPress smoke passed; JavaScript lint passed; TypeScript then failed exactly because `conversationList` and `conversationDetail` were not accepted by `AdminShellProps`. This is the intended missing shell-composition behavior and is a valid RED.

Implementation checkpoint: `ccfd7a5e50c87304e3923786d6e799288e84fd54`, CI `34868709352`: **NOT GREEN**. Package/build passed, but `verify:js` stopped on four Prettier-only errors in `src-js/index.ts` before the implementation could be called GREEN.

Formatting repair / GREEN: `0447a7706f9ae96ff490f80bf29a1ecafafd9f12`, CI `34869371247`. `package`, `php-quality`, `js-quality`, and the complete `wordpress-smoke` suite all passed on the exact SHA.

Behavior: `AdminShell()` now composes the existing `ConversationInboxScreen` for a protected list projection and the existing `ConversationDetailScreen` for a selected transcript. Detail takes precedence when supplied; delete confirmation/callbacks are passed to the detail presentation boundary; no request-level credentials, provider/model overrides, retrieval settings, or direct storage access are introduced.

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

Repository-approved fallback scoped review for the admin-shell composition slice: **0 Critical, 0 Important** findings.

- Correctness: list/detail composition consumes the already-bounded REST projection types; detail deterministically takes precedence over the list when selected.
- Security/privacy: server capability enforcement remains authoritative; the composition accepts no credentials, arbitrary provider/model options, retrieval overrides, raw HTML, or storage identifiers beyond the existing protected conversation DTOs.
- Performance: composition is linear in already-bounded projected data and adds no new network work, loops over unbounded state, polling, or duplicate retrieval.
- Accessibility: it reuses the previously reviewed semantic inbox controls and explicit `alertdialog` deletion confirmation rather than replacing native/labelled controls.
- Architecture/duplication: the shell only composes existing loader/presentation authorities; it creates no parallel conversation repository, REST path, transcript parser, or chat/RAG pipeline.

## Exact next unfinished unit
Wire the existing `createConversationAdminLoader` into `bootstrapAdminApp()` so `#/conversations` loads the protected bounded list/detail state, navigation changes invalidate stale detail/list completions through the existing generation guard, and the shell receives only the current route projection. Then wire inbox detail navigation and protected DELETE confirmation/focus restoration without duplicating request-generation logic.
