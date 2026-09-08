# M12 Task 9 — Production Model Resource Wiring

Status: **COMPLETE SLICE — TASK 9 REMAINS ACTIVE**

## Scope

This checkpoint connects the existing provider settings UI to the existing Task 5 server-side model resource without introducing a browser-side provider catalog or compatibility engine.

On a selected provider route, the admin shell now:

- requests `GET /admin/models?provider_id=<provider>&purpose=generation` through the existing nonce-authenticated same-origin admin client;
- URI-encodes the selected provider ID;
- accepts only the server-returned `models` collection;
- reduces each accepted item to string `model_id` and `display_name` fields before passing it to `ProviderSettingsScreen`;
- keeps model state associated with the provider ID that produced it;
- clears and refetches credential/model state when the provider route changes, preventing stale model choices from being rendered for a different provider;
- leaves credential replacement on its existing credential-only refresh contract.

No direct browser-to-provider call, client-side model catalog, client-side compatibility rules, new credential path, or secret-bearing state was added.

## Design decision

The Task 5 `/admin/models` resource remains authoritative for provider/purpose compatibility. The browser is intentionally only a renderer of normalized server choices.

Credential state and model state use separate loaded-provider markers. This prevents an unrelated credential refresh from falsely asserting that the model list is current while still allowing credential replacement to retain its established narrow request sequence.

## Strict TDD evidence

### Formatting-only checkpoint — not RED

- Commit: `cbe62d6dea14197527fd1f41c40630d9abdab13e`
- CI: `34228386987`
- Result: stopped on Prettier before Jest.
- Classification: **NOT RED**.

### Genuine RED

- Commit: `d12a293902a6acb752a0aac08fbf5d9578c1f540`
- CI: `34228553287`
- Lint: passed.
- Typecheck: passed.
- Jest: **42 tests, 41 passed, exactly 1 failed**.
- Expected failure: provider settings never requested `/admin/models`, so the new production-wiring assertion failed on the missing third request.

### Implementation checkpoints

- `9c1e157bf76cbdcda4d61e391bf8e1fc25192528` — minimum production model-resource wiring. CI `34229490901` stopped on three Prettier findings before typecheck/Jest, so it was **not GREEN**.
- `021490589c9ff1db92ecab6fc1f0a88165815f69` — formatting-only correction. CI `34229822204` passed formatting/typecheck and the new model-wiring test, but exposed an older credential-replacement fixture whose call numbering predated the newly required initial model read: **42 tests, 41 passed, 1 failed**. This was not GREEN.
- `342e88967723f9612e5cf3eb982347177088ded5` — updated the old credential regression fixture to mock the new server model read while preserving its secret-safety assertions.

### GREEN

- Implementation head: `342e88967723f9612e5cf3eb982347177088ded5`
- Exact-head CI: `34229981092`
- `php-quality`: GREEN.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment cleanup.

## Review

Scoped correctness/security/accessibility/performance review `5142118163`, anchored to implementation head `342e88967723f9612e5cf3eb982347177088ded5`:

- Critical: **0**
- Important: **0**

Review conclusions:

- Correctness: provider model reads use `purpose=generation`, normalized server choices, provider-keyed state, and provider-change clearing/refetch.
- Security: existing nonce-authenticated same-origin admin transport is reused; no browser-to-provider request or secret-bearing model path was introduced.
- Accessibility: the existing labelled `ProviderSettingsScreen` model selector is reused.
- Performance: one bounded server model-resource request occurs for initial provider selection/provider change; no polling or unbounded client catalog exists.

A separate outer subagent execution transport was unavailable in this runtime. Final broader Task 9/M12 independent closeout review remains required where available.

## Exact continuation point

Task 9 remains active. The next unfinished slice is **safe actionable provider/capability error states**.

Under a fresh genuine RED, map the existing stable server error codes:

- `missing_credential`
- `provider_unavailable`
- `unsupported_capability`

into deterministic provider-screen guidance that is accessible (alert/live semantics where appropriate), actionable, and safe. Do not expose provider/upstream exception text, credentials, ciphertext, or arbitrary response messages. Preserve server authority for compatibility and credentials.

After that slice, perform final Task 9 correctness/security/accessibility/performance review plus an independent reviewer where available, resolve all Critical/Important findings, and obtain exact-final-SHA green CI before advancing to Task 10.
