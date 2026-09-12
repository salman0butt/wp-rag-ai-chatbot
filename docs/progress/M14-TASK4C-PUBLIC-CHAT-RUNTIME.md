# M14 Task 4C — Public Chat Persisted Runtime Resolution

Status: IN PROGRESS on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Completed slice — enabled persisted bot authority

Public chat now resolves the enabled persisted bot before any provider, retrieval, embedding, vector-store, memory, grounding, or generation work.

`PublicChatRuntimeResolver` accepts only the bounded public `bot_id`, canonicalizes it through `BotId`, loads the bot through the existing `BotRepository`, rejects missing or disabled bots with a stable non-sensitive failure, and returns the persisted `Bot` whose `provider_id` and `model_id` remain server-owned runtime authority.

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

Task 4C now has the smallest persisted bot-to-retrieval authority required by the public runtime. `BotRetrievalBinding` bounds a positive source ID and a collection identifier, `WpdbBotRetrievalBindingRepository` stores only those trusted values on the existing bot row, and database schema v12 adds nullable `retrieval_source_id` and `retrieval_collection_id` columns while preserving the existing appearance field and bot indexes.

Existing bot rows migrate without a retrieval binding so public chat can fail closed until trusted server-side configuration exists. Public callers still cannot provide source, collection, embedding, vector-store, grounding, output-token, or retrieval-limit values.

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

## Review

Scoped fallback review found no unresolved Critical or Important findings in the completed Task 4C slices.

### Correctness

- exactly one `BotRepository::find()` lookup occurs for the bounded bot identifier;
- missing bots fail closed;
- disabled bots fail closed;
- enabled persisted bot configuration is returned unchanged as the server runtime authority;
- retrieval binding persistence uses the existing bot row and preserves prior bot schema fields;
- schema v12 is registered through the existing migration runner and real WordPress database smoke passes.

### Security / privacy

- the public request cannot select provider or model values through this resolver;
- retrieval authority is server-owned persistence, not public input;
- no credentials, prompt text, raw client identity, embedding settings, vector-store settings, grounding controls, output-token controls, or retrieval limits are accepted or exposed;
- existing bots migrate unconfigured and therefore fail closed rather than inheriting guessed retrieval authority;
- failures use stable non-sensitive messages.

### Performance

Runtime resolution remains bounded to persisted repository lookups. The schema change adds no new query path or paid/network work and reuses the existing unique bot identifier for repository access.

### Architecture / duplication

The resolver and retrieval binding are narrow persistence/composition boundaries. They do not create another chat or retrieval pipeline and remain intended to feed the existing M11 production graph exactly once.

Independent reviewer transport was not available for these slices; no independent-review claim is made.

## Exact continuation

Task 4C is not complete yet. The next unfinished slice is to combine the enabled persisted bot with its persisted `BotRetrievalBinding` into one trusted public runtime authority, failing closed when the binding is absent or malformed, without accepting retrieval scope from public input.

After that resolver slice is exact-head GREEN, recover the existing M11/Playground composition authorities and continue toward Task 4D by constructing the production chat handler from persisted bot/provider/model/retrieval authority and delegating to the existing M11 chat graph exactly once.
