# M14 Task 4C — Public Chat Persisted Runtime Resolution

Status: COMPLETE on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Completed slice — enabled persisted bot authority

Public chat now resolves the enabled persisted bot before any provider, retrieval, embedding, vector-store, memory, grounding, or generation work.

`PublicChatRuntimeResolver` accepts only the bounded public `bot_id`, canonicalizes it through `BotId`, loads the bot through the existing `BotRepository`, rejects missing or disabled bots with a stable non-sensitive failure, and uses the persisted `Bot` whose `provider_id` and `model_id` remain server-owned runtime authority.

No request-level provider/model/embedding/vector-store/retrieval configuration was introduced.

## Strict TDD evidence — persisted bot authority

### Genuine RED

Commit: `0200faa04ef902dac7ef226406869aa46082be7c`

CI: `34694677377`

Composer validation, PHPCS, and PHPStan passed. PHPUnit then executed 779 tests / 3,253 assertions and failed exactly three new assertions because `PublicChatRuntimeResolver` was missing. This is a genuine behavioral RED.

### Genuine GREEN

Commit: `26c5cc6742b79065cb9b449d22b0087dcb4bb633`

CI: `34694734635`

All permanent gates passed on the exact implementation SHA:

- `php-quality` — GREEN, including PHPUnit and Composer audit;
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and Playground REST smoke.

## Completed slice — server-owned retrieval binding persistence

Task 4C has the smallest persisted bot-to-retrieval authority required by the public runtime. `BotRetrievalBinding` bounds a positive source ID and a collection identifier, `WpdbBotRetrievalBindingRepository` stores only those trusted values on the existing bot row, and database schema v12 adds nullable `retrieval_source_id` and `retrieval_collection_id` columns while preserving the existing appearance field and bot indexes.

Existing bot rows migrate without a retrieval binding so public chat fails closed until trusted server-side configuration exists. Public callers still cannot provide source, collection, embedding, vector-store, grounding, output-token, or retrieval-limit values.

### Genuine RED

Commit: `31eef515f4da3380e6034b9838e9fb97e2f32d22`

CI: `34695650088`

`package`, `js-quality`, and `wordpress-smoke` passed. In `php-quality`, Composer validation, PHPCS, and PHPStan passed before PHPUnit failed on `BotRetrievalBindingMigrationContractTest` because `V012AddBotRetrievalBinding` did not exist. This is a genuine behavior-level RED.

The later test-only cleanup commit `cf1e276b0b904981c6be862c4f9d63755f21d4ba` / CI `34695668883` preserved the same intended PHPUnit RED while decoupling the older appearance-migration contract from the latest schema version.

### NOT GREEN checkpoint

Commit: `1fcc72d98a65796bf6881c7044bab8b92258b0a2`

CI: `34695913331`

The migration class existed and PHP quality was GREEN, but `wordpress-smoke` failed at the real database smoke because schema v12 had not yet been registered in `DatabaseBootstrap`. This checkpoint is explicitly **NOT GREEN**.

### Genuine GREEN

Commit: `3e1879046af6296d176409ce980898e509f92687`

CI: `34695925918`

All permanent gates passed on the exact implementation SHA:

- `php-quality` — GREEN, including coding standards, PHPStan, PHPUnit, and Composer audit;
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and Playground REST smoke.

## Completed slice — trusted combined public runtime authority

`PublicChatRuntime` now carries the enabled persisted `Bot` and its persisted `BotRetrievalBinding` as one trusted server-side runtime authority. `PublicChatRuntimeResolver` performs the bot lookup first, rejects missing/disabled bots before retrieval work, then loads the binding exactly once and rejects an unconfigured bot before any provider/retrieval/generation work can begin.

The public request still contributes only the bounded bot identifier used to select persisted state. It cannot provide provider/model/source/collection/embedding/vector-store/grounding/output-token/retrieval-limit authority.

### NOT RED checkpoint

Commit: `b72af1dd2e0c7e2bff687c1147600adadc3f1204`

CI: `34696169999`

Composer validation completed, but PHPCS reported assignment-alignment warnings and stopped the PHP job before static analysis or PHPUnit reached the intended behavior. This checkpoint is explicitly **NOT RED**.

### Genuine RED

Commit: `cb6f9d5d1d9a30b3e64502764123aedc42dc1c8e`

CI: `34696219439`

Composer validation, PHPCS, and PHPStan passed. PHPUnit executed 788 tests / 3,287 assertions and failed exactly two Task 4C assertions: `PublicChatRuntime` was missing and an enabled bot without a persisted retrieval binding did not fail closed. This is a genuine behavioral RED.

### Genuine GREEN

Commit: `4b762936f77c4d6f6f2cfd26f55e18659aefb464`

CI: `34696295902`

All permanent gates passed on the exact implementation SHA:

- `php-quality` — GREEN, including coding standards, PHPStan, PHPUnit, and Composer audit;
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and Playground REST smoke.

## Review

Scoped fallback review found no unresolved Critical or Important findings in Task 4C.

### Correctness

- exactly one `BotRepository::find()` lookup occurs for the bounded bot identifier;
- missing and disabled bots fail closed before retrieval lookup;
- exactly one persisted retrieval-binding lookup occurs for an enabled bot;
- missing retrieval binding fails closed before provider/retrieval/generation work;
- the resulting runtime carries the persisted bot and exact persisted retrieval binding without caller-supplied runtime overrides;
- schema v12 is registered through the existing migration runner and real WordPress database smoke passes.

### Security / privacy

- the public request cannot select provider, model, source, collection, embedding, vector-store, grounding, output-token, or retrieval-limit values;
- retrieval authority is server-owned persistence, not public input;
- no credentials, prompt text, raw client identity, or internal runtime configuration is exposed by the runtime resolver;
- existing bots migrate unconfigured and therefore fail closed rather than inheriting guessed retrieval authority;
- failures use stable non-sensitive messages.

### Performance

Runtime resolution is bounded to one persisted bot lookup and, only for an enabled bot, one retrieval-binding lookup. No paid provider, embedding, vector-search, retrieval, or generation work occurs during Task 4C resolution.

### Architecture / duplication

Task 4C is a narrow persisted authority/composition seam. It does not create another chat or retrieval pipeline and is designed to feed the existing M11 production graph exactly once.

Independent reviewer transport was not available for these slices; no independent-review claim is made.

## Exact continuation

Task 4C is complete. Continue immediately with Task 4D — production chat handler.

Recover the existing M11/Playground composition authorities, map `PublicChatRequest` plus trusted `PublicChatRuntime` into one internal `ChatRequest` using only persisted server-owned model/provider/retrieval authority and repository-defined grounding/output-token policy, execute the existing M11 chat graph exactly once, and project only public-safe answer/conversation/citation data.

Use a separate strict TDD RED -> GREEN slice for this handler. Do not add a second retrieval/chat implementation or any public request-level provider/model/source/collection/embedding/vector-store/grounding/output-token/retrieval-limit override surface.
