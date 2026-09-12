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

The trusted WordPress request adapter is now verified:

- `PublicChatWordPressRequestAdapter::request()` delegates payload validation to the closed `PublicChatRequest` contract;
- `PublicChatWordPressRequestAdapter::client_scope()` derives the anonymous scope only from trusted server-side `REMOTE_ADDR` and the server-owned secret supplied by composition;
- spoofable `HTTP_X_FORWARDED_FOR` input is not consulted by this adapter;
- caller-provided runtime override keys remain rejected by `PublicChatRequest`.

`PublicChatRestBootstrap::run_chat()` intentionally still fails closed with `chat_unavailable`. Concrete WordPress persistence/runtime composition and delegation through `PublicChatRestResource` remain unfinished. Therefore Task 4E is not complete.

This boundary is intentionally not a second RAG implementation. Production retrieval, grounding, prompt construction, memory, generation, citation validation, and persistence remain owned by the existing M11 graph reached through `ProductionPublicChatExecutor`.

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

## Scoped review

Fallback scoped review is used where independent reviewer transport is unavailable.

### Correctness

- route registration occurs through the normal plugin bootstrap;
- abuse control remains inside `PublicChatRestResource`, ahead of persisted runtime/provider/retrieval work;
- the WordPress request adapter reuses the canonical `PublicChatRequest` parser rather than adding another payload contract;
- client-scope derivation uses the existing `PublicChatClientScope` authority;
- current `run_chat()` fails closed rather than pretending production composition is already available.

Unresolved Critical findings: 0.
Unresolved Important findings: 0.

### Security/privacy

- anonymous routing does not introduce provider/model/credential/retrieval override arguments;
- trusted client identity is derived from server-side `REMOTE_ADDR` and does not trust caller-spoofable forwarded-address headers;
- raw IP, user-agent, prompt text, credentials, and provider secrets are not persisted by the request adapter;
- caller-provided runtime override keys remain rejected by the closed request contract;
- current fail-closed callback leaks no sensitive runtime detail.

Unresolved Critical findings: 0.
Unresolved Important findings: 0.

### Performance

- route registration and the request adapter add no expensive retrieval/provider work;
- the intended callback composition must preserve the existing cheap abuse guard before runtime/database/provider work.

Unresolved Important findings: 0.

### Architecture / duplication

- request adaptation delegates to existing request/client-scope authorities rather than duplicating validation or identity logic;
- no semantic/lexical retrieval, fusion, reranking, grounding, prompt, memory, provider, embedding, vector-store, or citation pipeline is duplicated;
- `ProductionPublicChatExecutor` remains the bridge into the established M11 graph.

Unresolved Important findings: 0.

## Exact remaining Task 4E work

Continue under strict TDD with the thinnest WordPress callback/composition seam that:

1. obtains the `WP_REST_Request` JSON payload and parses it only through `PublicChatWordPressRequestAdapter` / the closed `PublicChatRequest` contract;
2. derives the client scope through `PublicChatWordPressRequestAdapter` from trusted server metadata plus a server-owned WordPress secret;
3. uses `WpdbPublicChatRateLimitStore` / `PublicChatAbuseGuard` before expensive runtime/provider/retrieval work;
4. resolves persisted bot/retrieval authority and delegates once through `PublicChatRestResource` / `ProductionPublicChatExecutor`;
5. returns stable non-sensitive malformed/unavailable/rate-limited responses;
6. never accepts caller-controlled credentials, provider/model, embedding, vector-store, grounding, output-token, source/collection, or retrieval-limit authority.

After the callback is exact-head GREEN, continue directly into Task 4F real WordPress integration/smoke coverage and final correctness/security/performance/architecture/privacy review before Task 5 starts.
