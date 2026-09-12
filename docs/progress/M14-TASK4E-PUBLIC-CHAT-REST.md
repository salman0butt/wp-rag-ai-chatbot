# M14 Task 4E — Public Chat REST Boundary

Status: IN PROGRESS on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Scope completed in this checkpoint

The first Task 4E REST boundary is now verified:

- `PublicChatRestResource` accepts only an already-validated `PublicChatRequest` plus a server-derived opaque client scope;
- `PublicChatAbuseGuard` executes before persisted runtime resolution, executor composition, retrieval, or provider work;
- denied or failed abuse checks fail closed with the stable non-sensitive `rate_limited` code;
- allowed execution resolves persisted `PublicChatRuntime`, composes `ProductionPublicChatExecutor`, executes the existing M11 production graph exactly once, and projects only public answer/conversation/citation fields;
- downstream runtime/provider/retrieval failures collapse to the stable non-sensitive `chat_unavailable` code;
- no request-level credential, provider, model, embedding, vector-store, grounding, token-budget, source/collection, or retrieval-limit authority is introduced.

This resource is intentionally not a second RAG implementation. Production retrieval, grounding, prompt construction, memory, generation, citation validation, and persistence remain owned by the existing M11 graph reached through `ProductionPublicChatExecutor`.

## Strict TDD chronology

### Genuine RED

- SHA: `e578f447d07d75ab3bd3845066871304c06c83eb`
- CI: `34699031215`
- Test-only change: `tests/Unit/Frontend/PublicChatRestResourceTest.php`
- Composer validation: PASS
- PHPCS: PASS
- PHPStan: PASS
- PHPUnit: FAIL at the intended missing `PublicChatRestResource` behavior.

The test specifies that a denied abuse-control request must not consult persisted bot/retrieval repositories and must not invoke the production executor factory.

### NOT GREEN — standards gate

- SHA: `d8ad948f8a4955b66e97a88106759d04152b869e`
- CI: `34699110732`
- PHPCS failed before PHPStan/PHPUnit completed.

This checkpoint is explicitly **NOT GREEN**.

### NOT GREEN — invalid RED fixture exposed after implementation

- SHA: `ba2ed12646e5f110b153f52d98c39f85ebc90f60`
- CI: `34699180953`
- Composer validation: PASS
- PHPCS: PASS
- PHPStan: PASS
- PHPUnit: FAIL

Systematic debugging showed the test fixture used `public-bot`, while `BotId` requires exactly 32 lowercase hexadecimal characters. This was a test-fixture defect, not a production behavior defect. Production code was left unchanged while the fixture was repaired.

### Genuine GREEN

- SHA: `7eff6f93d14c387518df15378248e9f26d567e99`
- CI: `34699283504`
- `php-quality`: PASS, including Composer validation, PHPCS, PHPStan, PHPUnit, and Composer audit
- `js-quality`: PASS
- `package`: PASS
- `wordpress-smoke`: PASS, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and Playground REST smoke

## Scoped review

Fallback scoped review was used because independent reviewer transport was not available in this execution environment.

### Correctness

- abuse control is evaluated before persisted runtime lookup or executor composition;
- denied requests cannot trigger provider/retrieval work;
- allowed requests delegate through existing persisted runtime and production executor authorities;
- success output is projected from `PublicChatResponse` only.

Unresolved Critical findings: 0.
Unresolved Important findings: 0.

### Security/privacy

- client identity enters this layer only as an opaque server-derived scope;
- raw IP, user-agent, prompt text, credentials, and provider secrets are not persisted by this boundary;
- runtime authority remains persisted/server-owned;
- errors are stable and non-sensitive;
- arbitrary public runtime overrides remain impossible through `PublicChatRequest`.

Unresolved Critical findings: 0.
Unresolved Important findings: 0.

### Performance

- the cheap abuse guard runs before database runtime resolution and paid/expensive chat work;
- denied requests skip production graph composition completely.

Unresolved Important findings: 0.

### Architecture / duplication

- no semantic/lexical retrieval, fusion, reranking, grounding, prompt, memory, provider, embedding, vector-store, or citation pipeline was duplicated;
- `ProductionPublicChatExecutor` remains the bridge into the established M11 graph.

Unresolved Important findings: 0.

## Exact remaining Task 4E work

The WordPress-facing adapter/registration is still unfinished. Continue under strict TDD by adding the thinnest WordPress REST bootstrap/callback that:

1. registers a public POST route under the established `wp-rag-ai-chatbot/v1` namespace/version convention;
2. parses JSON only through `PublicChatRequest`;
3. derives the client scope only from trusted server metadata using `PublicChatClientScope` and a server-owned WordPress secret;
4. uses `WpdbPublicChatRateLimitStore` / `PublicChatAbuseGuard` before expensive runtime/provider/retrieval work;
5. resolves persisted bot/retrieval authority and delegates once through `PublicChatRestResource` / `ProductionPublicChatExecutor`;
6. returns stable non-sensitive malformed/unavailable/rate-limited responses;
7. never accepts caller-controlled credentials, provider/model, embedding, vector-store, grounding, output-token, source/collection, or retrieval-limit authority.

After that adapter is exact-head GREEN, continue directly into Task 4F WordPress integration/smoke coverage and final correctness/security/performance/architecture/privacy review.
