# M12 Task 9 — Server-Supplied Model Selector Rendering

Status: COMPLETE SLICE — TASK 9 REMAINS ACTIVE

## Scope

This checkpoint covers only the provider-screen rendering boundary for capability-compatible model choices. The component accepts an already-filtered model list and renders exactly those choices. Production bootstrap wiring to the existing Task 5 `/admin/models` resource remains unfinished and is the next Task 9 unit.

No client-side provider/model catalog, compatibility rules, public provider call, credential path, storage path, or secret-bearing state was added.

## Design decision

The existing Task 5 server-side model/capability resource remains authoritative. The browser must not recreate provider capability logic. `ProviderSettingsScreen` therefore receives normalized compatible model metadata from its caller and renders those values directly.

Alternative approaches rejected:

- Hard-coded client model lists: duplicates provider truth and can drift.
- Client-side compatibility filtering: duplicates Task 5 policy and weakens server authority.
- Direct browser provider discovery: violates the existing admin REST/control-plane boundary.

AUTO-APPROVED — SCHEDULED MODE

## Strict TDD evidence

### Non-RED formatting checkpoint

- Commit: `f2c0023174f1d573435ffeff11090cbc83bf22d3`
- CI: `34222914798`
- Result: `js-quality` stopped on two Prettier findings before Jest. This is not counted as behavioral RED.

### Genuine RED

- Commit: `715abf2110dc1d95e726bccd2c75d1a507ea5839`
- CI: `34223027930`
- Lint and TypeScript typecheck passed.
- Jest executed 41 tests: **40 passed / 1 failed**.
- The only failing test was `provider-model-selection.test.ts`; it failed because `select[name="model_id"]` did not exist (`Received: null`).

This proves the test failed for the missing selector behavior rather than formatting, typing, or unrelated regressions.

### GREEN

- Implementation commit: `4002b983699e0dadc8becc0802b3ab54ff175a9d`
- CI: `34223161036`
- `php-quality`: GREEN
- `js-quality`: GREEN
- `package`: GREEN
- `wordpress-smoke`: GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment cleanup.

## Implementation

`ProviderSettingsScreen` now accepts optional `ProviderModelChoice[]` data and, when choices exist, renders:

- a `Generation model` section;
- an explicitly labelled provider-scoped `<select name="model_id">`;
- one option per supplied `model_id` / `display_name` pair.

The implementation does not invent or filter any model choice locally. Existing credential configured/source display and safe replacement behavior are unchanged.

## Review

Scoped correctness/security/accessibility/performance review `5141380619`, anchored to implementation commit `4002b983699e0dadc8becc0802b3ab54ff175a9d`:

- Critical: 0
- Important: 0
- Correctness: only caller/server-supplied options are rendered.
- Security: no new secret, credential, logging, or public-network path.
- Accessibility: explicit label/select association and stable provider-scoped id.
- Performance: linear work over an already-bounded caller-supplied model list.

This review covers the rendering slice only and does not close Task 9.

## Exact next unfinished task

Continue Task 9 under strict TDD with production wiring to the existing Task 5 model resource:

1. on the selected provider screen, request `/admin/models` for that provider and `purpose=generation` through the existing nonce-authenticated admin client;
2. accept only normalized models returned by the server and pass them into `ProviderSettingsScreen`;
3. refresh model state when the provider changes without reusing stale choices from another provider;
4. preserve server authority and add no separate client model catalog or public provider call;
5. expose the existing stable provider/capability error codes as actionable accessible UI without leaking unsafe upstream details.

Task 9 remains ACTIVE until this production wiring, error states, focused/full verification, closeout review, and exact-final-SHA CI are complete.
