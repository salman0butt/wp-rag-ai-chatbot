# M14 Task 4C — Public Chat Persisted Runtime Resolution

Status: IN PROGRESS on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Completed slice — enabled persisted bot authority

Public chat now resolves the enabled persisted bot before any provider, retrieval, embedding, vector-store, memory, grounding, or generation work.

`PublicChatRuntimeResolver` accepts only the bounded public `bot_id`, canonicalizes it through `BotId`, loads the bot through the existing `BotRepository`, rejects missing or disabled bots with a stable non-sensitive failure, and returns the persisted `Bot` whose `provider_id` and `model_id` remain server-owned runtime authority.

No request-level provider/model/embedding/vector-store/retrieval configuration was introduced.

## Strict TDD evidence

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

## Review

Scoped fallback review found no unresolved Critical or Important findings.

### Correctness

- exactly one `BotRepository::find()` lookup occurs for the bounded bot identifier;
- missing bots fail closed;
- disabled bots fail closed;
- enabled persisted bot configuration is returned unchanged as the server runtime authority.

### Security / privacy

- the public request cannot select provider or model values through this resolver;
- no credentials, prompt text, raw client identity, embedding settings, vector-store settings, or retrieval limits are accepted or exposed;
- failures use a stable non-sensitive message.

### Performance

One repository lookup plus deterministic bot validation only; no paid/network runtime work occurs.

### Architecture / duplication

The resolver is a narrow composition boundary over the existing `BotRepository`; it does not create another chat or retrieval pipeline.

Independent reviewer transport was not available for this slice; no independent-review claim is made.

## Exact continuation

Task 4C is not complete yet. The next unfinished slice is the server-owned knowledge/retrieval binding required to construct the existing M11 chat graph without accepting source, collection, embedding, vector-store, grounding, output-token, or retrieval-limit values from public input.

Recover the existing Knowledge/Retrieval/Playground composition and database authorities first. If a persisted bot-to-knowledge binding already exists, reuse it. If it does not, introduce only the smallest server-owned binding required by the M14 product model and verify it with a separate strict RED -> GREEN cycle before Task 4D.
