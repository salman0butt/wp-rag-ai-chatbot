# M14 Task 4E — Public Chat REST Boundary

Status: IN PROGRESS on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Scope verified so far

The Task 4E domain/resource boundary is verified:

- `PublicChatRestResource` accepts only an already-validated `PublicChatRequest` plus a server-derived opaque client scope;
- `PublicChatAbuseGuard` executes before persisted runtime resolution, executor composition, retrieval, or provider work;
- denied or failed abuse checks fail closed with the stable non-sensitive `rate_limited` code;
- allowed execution resolves persisted `PublicChatRuntime`, composes `ProductionPublicChatExecutor`, executes the existing M11 production graph exactly once, and projects only public answer/conversation/citation fields;
- downstream runtime/provider/retrieval failures collapse to the stable non-sensitive `chat_unavailable` code;
- no request-level credential, provider, model, embedding, vector-store, grounding, token-budget, source/collection, or retrieval-limit authority is introduced.

The WordPress REST route registration is also now wired into the plugin bootstrap:

- one public POST route is registered at `wp-rag-ai-chatbot/v1/chat`;
- anonymous access remains explicit via `allow_public()` because abuse control is enforced inside execution rather than through WordPress authentication;
- route metadata exposes no caller-controlled runtime `args` surface;
- `src/Core/Bootstrap.php` registers `PublicChatRestBootstrap::register_routes()` on the established REST bootstrap path.

The trusted WordPress request adapter is verified:

- `PublicChatWordPressRequestAdapter::request()` delegates payload validation to the closed `PublicChatRequest` contract;
- `PublicChatWordPressRequestAdapter::client_scope()` derives the anonymous scope only from trusted server-side `REMOTE_ADDR` and the server-owned secret supplied by composition;
- spoofable `HTTP_X_FORWARDED_FOR` input is not consulted by this adapter;
- caller-provided runtime override keys remain rejected by `PublicChatRequest`.

The WordPress callback now composes the server-owned abuse-control and persisted-runtime boundary:

- the client scope is derived from `REMOTE_ADDR` plus `wp_salt( 'auth' )`;
- `WpdbPublicChatRateLimitStore` / `PublicChatAbuseGuard` execute before runtime/provider/retrieval work;
- persisted bot and retrieval binding authority is resolved through `WpdbBotRepository` and `WpdbBotRetrievalBindingRepository`;
- the real WordPress REST smoke proves the first 20 bounded anonymous requests reach the fail-closed unavailable path while request 21 is rejected as `rate_limited` before runtime/provider work;
- unknown caller-controlled runtime fields fail closed with `invalid_request`.

The final production executor factory is still deliberately fail-closed. A valid persisted bot must not be considered executable until the callback delegates to the existing M10/M11 production chat composition exactly once. No parallel RAG implementation has been introduced.

## Strict TDD chronology

### Public resource — genuine RED

- SHA: `e578f447d07d75ab3bd3845066871304c06c83eb`
- CI: `34699031215`
- Test-only change: `tests/Unit/Frontend/PublicChatRestResourceTest.php`
- Composer validation: PASS
- PHPCS: PASS
- PHPStan: PASS
- PHPUnit: FAIL at the intended missing `PublicChatRestResource` behavior.

### Public resource — NOT GREEN checkpoints

- SHA: `d8ad948f8a4955b66e97a88106759d04152b869e`
- CI: `34699110732`
- PHPCS failed before PHPStan/PHPUnit completed; explicitly NOT GREEN.

- SHA: `ba2ed12646e5f110b153f52d98c39f85ebc90f60`
- CI: `34699180953`
- Composer validation/PHPCS/PHPStan passed; PHPUnit exposed an invalid test fixture (`public-bot` did not satisfy the canonical 32-character lowercase hexadecimal `BotId` contract). Production code was not changed to accommodate the bad fixture.

### Public resource — genuine GREEN

- SHA: `7eff6f93d14c387518df15378248e9f26d567e99`
- CI: `34699283504`
- `php-quality`: PASS
- `js-quality`: PASS
- `package`: PASS
- `wordpress-smoke`: PASS

### WordPress route hook — genuine RED

- SHA: `a87c315f364b9319cbb707143fba9b0b02d8183a`
- CI: `34699800491`
- Test-only change specified that the plugin bootstrap must expose the public-chat REST hook.
- Composer validation: PASS
- PHPCS: PASS
- PHPStan: PASS
- PHPUnit: FAIL at the intended missing hook behavior.
- `js-quality`, `package`, and `wordpress-smoke`: PASS.

This is a genuine behavioral RED rather than an infrastructure or standards failure.

### WordPress route hook — genuine GREEN

- SHA: `20b169409669fcee5589a1b07bef1504cf3ad856`
- CI: `34699855524`
- `php-quality`: PASS
- `js-quality`: PASS
- `package`: PASS
- `wordpress-smoke`: PASS

### Trusted WordPress request adapter — genuine RED

- SHA: `dd31962618d36e96a9f9db88d38076941ffad9b8`
- CI: `34700588353`
- Test-only change: `tests/Unit/Frontend/PublicChatWordPressRequestAdapterTest.php`
- Composer validation: PASS
- PHPCS: PASS
- PHPStan: PASS
- PHPUnit: FAIL at the intended missing `PublicChatWordPressRequestAdapter` behavior.
- `js-quality`, `package`, and `wordpress-smoke`: PASS.

This is a genuine behavioral RED.

### Trusted WordPress request adapter — genuine GREEN

- SHA: `77dacd3cb6cd9a3585ecb86d8b55abb07075375c`
- CI: `34700668201`
- `php-quality`: PASS, including Composer validation, PHPCS, PHPStan, PHPUnit, and Composer audit.
- `js-quality`: PASS.
- `package`: PASS.
- `wordpress-smoke`: PASS, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and Playground REST smoke.

### Callback payload validation — NOT RED

- SHA: `0dc211403e9c231646902bd2a9a5595e04f3afb9`
- CI: `34703339674`
- The intended malformed-request callback specification was present, but PHPCS rejected the original in-file `WP_REST_Request` test double before PHPStan or PHPUnit could reach the intended behavior.
- This checkpoint is explicitly **NOT RED** and is preserved honestly.

### Callback payload validation — genuine RED

- SHA: `ff4e9890091dc7679b7c3c2f1f4ec503f4e1feb0`
- CI: `34703461965`
- The request double was moved into `tests/Fixtures/WP_REST_Request.php`, keeping one object and one namespace structure per file.
- Composer validation: PASS.
- PHPCS: PASS.
- PHPStan: PASS.
- PHPUnit executed 799 tests / 3,350 assertions and failed exactly one intended assertion: unknown caller-controlled `provider` input returned `chat_unavailable` instead of `invalid_request`.
- This is a genuine behavior-level RED.

### Callback payload validation — NOT GREEN

- SHA: `5fb2de7058fe9f3cdf01a7a24b57454493d9fde0`
- CI: `34703537099`
- PHPCS passed, but PHPStan rejected a redundant `is_array()` guard because the WordPress `WP_REST_Request::get_json_params()` stub is already typed as an array. PHPUnit did not execute.
- This checkpoint is explicitly **NOT GREEN**.

### Callback payload validation — genuine GREEN

- SHA: `d79f2064558e811a151a0ac4ca9fa42d1f49382c`
- CI: `34703607078`
- `php-quality`: PASS, including Composer validation, PHPCS, PHPStan, PHPUnit, and Composer audit.
- `js-quality`: PASS.
- `package`: PASS.
- `wordpress-smoke`: PASS, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and Playground REST smoke.

### WordPress abuse-order integration — genuine RED

- SHA: `cf50839dc05cdb76c21edde84b6e126b8e3b954a`
- CI: `34710407146`
- Test-only change: `scripts/test-wp-playground-rest.php`.
- `php-quality`: PASS.
- `js-quality`: PASS.
- `package`: PASS.
- WordPress activation, database, providers, knowledge, file-ingestion, and WooCommerce smoke steps all passed.
- The real WordPress REST smoke then failed exactly at the intended assertion: `Public chat REST callback did not enforce the abuse limit before runtime work.`
- This is a genuine behavior-level RED; the failure was not caused by PHPCS, PHPStan, PHPUnit infrastructure, packaging, or an unrelated WordPress smoke regression.

### WordPress abuse-order integration — genuine GREEN

- SHA: `35cbc43338726f4062554439564c3ef636228cca`
- CI: `34710569241`
- The callback now derives its trusted client scope with `wp_salt( 'auth' )`, composes `WpdbPublicChatRateLimitStore` / `PublicChatAbuseGuard`, and resolves persisted bot/retrieval binding authority through the existing repositories before any executor work.
- `php-quality`: PASS, including Composer validation, PHPCS, PHPStan, PHPUnit, and Composer audit.
- `js-quality`: PASS, including live-provider/vector gating and package assertion tests.
- `package`: PASS.
- `wordpress-smoke`: PASS, including the new real REST assertion that requests 1–20 reach the fail-closed unavailable path and request 21 is stopped as `rate_limited`.

## Scoped review

Fallback scoped review is used where independent reviewer transport is unavailable.

### Correctness

- route registration occurs through the normal plugin bootstrap;
- abuse control now executes in the real WordPress callback ahead of persisted runtime/provider/retrieval work;
- the WordPress request adapter reuses the canonical `PublicChatRequest` parser rather than adding another payload contract;
- client-scope derivation uses the existing `PublicChatClientScope` authority;
- malformed/override-bearing payloads are rejected through the canonical request contract before composition;
- persisted bot and retrieval-binding authority is reused rather than supplied by the caller;
- valid requests still fail closed until the established M10/M11 production executor composition is connected.

Unresolved Critical findings: 0.
Unresolved Important findings: 0 for the completed abuse-order subunit.

### Security/privacy

- anonymous routing does not introduce provider/model/credential/retrieval override arguments;
- trusted client identity is derived from server-side `REMOTE_ADDR` and does not trust caller-spoofable forwarded-address headers;
- raw IP, user-agent, prompt text, credentials, and provider secrets are not persisted by the request adapter;
- caller-provided runtime override keys are rejected by the closed request contract and callback;
- abuse control is proven to run before expensive downstream work;
- current fail-closed executor seam leaks no sensitive runtime detail.

Unresolved Critical findings: 0.
Unresolved Important findings: 0 for the completed abuse-order subunit.

### Performance

- cheap request validation and abuse control precede persisted runtime/provider/retrieval work;
- the rate-limit store remains bounded WordPress database work;
- no generation/retrieval call is made for denied requests.

Unresolved Important findings: 0 for the completed abuse-order subunit.

### Architecture / duplication

- request adaptation delegates to existing request/client-scope authorities rather than duplicating validation or identity logic;
- persisted bot and retrieval binding resolution reuse existing repositories;
- no semantic/lexical retrieval, fusion, reranking, grounding, prompt, memory, provider, embedding, vector-store, or citation pipeline is duplicated;
- `ProductionPublicChatExecutor` remains the intended bridge into the established M11 graph;
- the callback's current executor factory intentionally throws fail-closed until that existing production composition is wired; this is tracked unfinished work, not represented as a completed executor path.

Unresolved Important findings: 0 for the completed abuse-order subunit.

Independent reviewer/subagent transport is not available in this execution environment, so no independent-review claim is made for this checkpoint.

## Exact remaining Task 4E work

Continue under strict TDD with the thinnest production executor composition seam that:

1. consumes the already-persisted `PublicChatRuntime` rather than caller-selected runtime settings;
2. reuses the established M10/M11 semantic + lexical retrieval, fusion/rerank, grounding, prompt, memory, generation, and citation authorities exactly once;
3. supplies the persisted generation model and bot-scoped access context to `ProductionPublicChatExecutor` without accepting request-level overrides;
4. preserves stable non-sensitive `chat_unavailable` behavior on configuration/provider/retrieval failure;
5. introduces no second public RAG graph or duplicated scoring/retrieval/provider-selection logic.

After that executor delegation seam is exact-head GREEN, continue directly into Task 4F real WordPress integration/smoke coverage and final correctness/security/performance/architecture/privacy review before Task 5 starts.
