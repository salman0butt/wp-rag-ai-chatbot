# M03 AI Providers, Credentials & Compatibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement secure, provider-agnostic text generation/model discovery for OpenAI Direct, OpenRouter Direct, and optional WordPress 7 AI Client compatibility without exposing credentials or consuming paid API credits in normal CI.

**Architecture:** Introduce a focused `Providers` module with normalized generation/model/error contracts. Direct adapters depend on server-only credential resolution plus an injectable WordPress HTTP transport; model discovery is wrapped by a transient cache. WordPress 7 AI Client is feature-detected and isolated behind the same generation contract, while WP 6.9 continues to use direct adapters.

**Tech Stack:** PHP 8.2+, WordPress 6.9+/optional 7.0 AI Client, WordPress HTTP API, Options/Transients API, Sodium/OpenSSL authenticated encryption, PHPUnit 10, Brain Monkey, WPCS, PHPStan, `wp-env`, WP-CLI, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-02-m03-ai-providers-credentials-design.md`

## Global Constraints

- WordPress/PHP is the mandatory server runtime; no mandatory Node/Python backend or provider SDK dependency.
- WordPress 6.9+ and PHP 8.2+ remain supported.
- WordPress 7 AI Client is optional and feature-detected through public APIs only.
- Provider credentials never reach browser responses, HTML, JS bundles, public REST, localStorage, normal logs, or user-facing exception text.
- Direct credential precedence is non-empty environment variable -> non-empty PHP constant -> encrypted plugin option.
- Provider option names are exactly `wp_rag_ai_openai_api_key` and `wp_rag_ai_openrouter_api_key`; option autoload must be disabled.
- Database-stored credentials use authenticated encryption only; no plaintext fallback.
- HKDF input/context and envelope format must match the approved design exactly.
- Direct provider endpoints are fixed adapter constants; M03 has no arbitrary base URL setting.
- Provider requests disable redirects.
- Generation timeout is 45 seconds and has exactly one attempt.
- Model discovery timeout is 10 seconds and has at most two total attempts, retrying only transport errors and HTTP 502/503/504.
- Model catalog cache TTL is 900 seconds; failed refresh must not overwrite a valid cached list.
- Unknown provider capabilities remain unknown; do not infer them from model-name patterns.
- Normal CI uses mocked/fake provider HTTP only and consumes no paid credits.
- Live smoke requires `WP_RAG_AI_LIVE_PROVIDER_TESTS=1` plus a non-empty environment credential.
- Embeddings runtime/indexing, production RAG, tools, public streaming, provider admin UI, pricing tables, and arbitrary endpoints remain out of M03.
- Multi-word PHP methods follow repository WPCS style and use snake_case.
- Do not merge to `main` without the finishing-a-development-branch integration decision.

## File Map

Create:
- `src/Providers/ProviderIds.php`
- `src/Providers/GenerationProvider.php`
- `src/Providers/ModelCatalogProvider.php`
- `src/Providers/GenerationRequest.php`
- `src/Providers/GenerationResult.php`
- `src/Providers/GenerationStatus.php`
- `src/Providers/Usage.php`
- `src/Providers/ModelInfo.php`
- `src/Providers/ProviderErrorCode.php`
- `src/Providers/ProviderException.php`
- `src/Providers/ProviderAvailability.php`
- `src/Providers/ProviderDescriptor.php`
- `src/Providers/ProviderRegistry.php`
- `src/Providers/Credentials/CredentialSource.php`
- `src/Providers/Credentials/Secret.php`
- `src/Providers/Credentials/ResolvedCredential.php`
- `src/Providers/Credentials/DirectProviderCredentialConfig.php`
- `src/Providers/Credentials/CredentialSourceReader.php`
- `src/Providers/Credentials/RuntimeCredentialSourceReader.php`
- `src/Providers/Credentials/CredentialStore.php`
- `src/Providers/Credentials/CredentialResolver.php`
- `src/Providers/Credentials/CryptoCapabilities.php`
- `src/Providers/Credentials/RuntimeCryptoCapabilities.php`
- `src/Providers/Credentials/CredentialCipher.php`
- `src/Providers/Credentials/AuthenticatedCredentialCipher.php`
- `src/Providers/Credentials/WordPressCredentialStore.php`
- `src/Providers/Security/SecretRedactor.php`
- `src/Providers/Http/HttpRequest.php`
- `src/Providers/Http/HttpResponse.php`
- `src/Providers/Http/HttpTransport.php`
- `src/Providers/Http/HttpTransportException.php`
- `src/Providers/Http/WordPressHttpTransport.php`
- `src/Providers/Http/ProviderHttpClient.php`
- `src/Providers/Cache/ModelCatalogCache.php`
- `src/Providers/Cache/WordPressTransientModelCatalogCache.php`
- `src/Providers/Cache/CachedModelCatalogProvider.php`
- `src/Providers/OpenAI/OpenAiProvider.php`
- `src/Providers/OpenRouter/OpenRouterProvider.php`
- `src/Providers/WordPressAi/WordPressAiClientProvider.php`
- `src/Providers/ProviderConfigurationService.php`
- `src/Providers/ProviderBootstrap.php`
- `tests/Support/Providers/FakeCredentialSourceReader.php`
- `tests/Support/Providers/FakeCredentialStore.php`
- `tests/Support/Providers/FakeHttpTransport.php`
- `tests/Support/Providers/FakeModelCatalogProvider.php`
- `tests/Unit/Providers/ProviderValueObjectsTest.php`
- `tests/Unit/Providers/Credentials/CredentialResolverTest.php`
- `tests/Unit/Providers/Credentials/AuthenticatedCredentialCipherTest.php`
- `tests/Unit/Providers/Credentials/WordPressCredentialStoreTest.php`
- `tests/Unit/Providers/Security/SecretRedactorTest.php`
- `tests/Unit/Providers/Http/ProviderHttpClientTest.php`
- `tests/Unit/Providers/Cache/CachedModelCatalogProviderTest.php`
- `tests/Unit/Providers/OpenAI/OpenAiProviderTest.php`
- `tests/Unit/Providers/OpenRouter/OpenRouterProviderTest.php`
- `tests/Unit/Providers/WordPressAi/WordPressAiClientProviderTest.php`
- `tests/Unit/Providers/ProviderRegistryTest.php`
- `scripts/test-wp-providers.php`
- `scripts/test-wp-providers.sh`
- `scripts/live-provider-smoke.php`
- `scripts/live-provider-smoke.sh`

Modify:
- `src/Core/Bootstrap.php`
- `tests/Unit/Core/BootstrapTest.php`
- `package.json`
- `.github/workflows/ci.yml`
- `scripts/assert-package.sh`
- `docs/DECISIONS.md`
- `docs/milestones/M03-ai-providers-credentials.md`
- `docs/progress/STATUS.md`
- `docs/progress/TEST-MATRIX.md`
- `docs/progress/SECURITY.md`
- `docs/progress/KNOWN-ISSUES.md`
- `docs/progress/TECH-DEBT.md`

---

### Task 1: Provider Contracts and Normalized Value Objects — RED -> GREEN

**Files:** provider contracts/value objects and `tests/Unit/Providers/ProviderValueObjectsTest.php`.

**Produces:** stable provider-neutral types consumed by all later M03 tasks.

**Interfaces:**

```php
interface GenerationProvider {
	public function provider_id(): string;
	public function available(): bool;
	public function generate( GenerationRequest $request ): GenerationResult;
}

interface ModelCatalogProvider {
	public function provider_id(): string;
	/** @return ModelInfo[] */
	public function models(): array;
}

enum GenerationStatus: string {
	case COMPLETED = 'completed';
	case INCOMPLETE = 'incomplete';
	case FAILED = 'failed';
	case UNKNOWN = 'unknown';
}

enum ProviderErrorCode: string {
	case CONFIGURATION = 'configuration';
	case AUTHENTICATION = 'authentication';
	case AUTHORIZATION = 'authorization';
	case RATE_LIMIT = 'rate_limit';
	case TIMEOUT = 'timeout';
	case TRANSPORT = 'transport';
	case MALFORMED_RESPONSE = 'malformed_response';
	case UNSUPPORTED_CAPABILITY = 'unsupported_capability';
	case UPSTREAM_SERVER = 'upstream_server';
	case UNKNOWN = 'unknown';
}

enum ProviderAvailability: string {
	case AVAILABLE = 'available';
	case UNAVAILABLE = 'unavailable';
	case UNCONFIGURED = 'unconfigured';
}
```

`GenerationRequest` constructor:

```php
public function __construct(
	public readonly string $model_id,
	public readonly string $input,
	public readonly ?string $instructions = null,
	public readonly ?int $max_output_tokens = null
)
```

Validation:
- trimmed `model_id` and `input` must be non-empty;
- `max_output_tokens`, when supplied, must be 1..32768;
- preserve original input/instruction whitespace internally except outer model-ID trim.

`Usage` constructor accepts nullable `input_tokens`, `output_tokens`, `total_tokens`, and `safe_metadata`; negative token values are rejected.

`GenerationResult` contains provider/model/output/status/usage/request ID. Empty output is allowed only when status is not `COMPLETED`.

`ModelInfo` contains provider ID, model ID, display name, `input_modalities`, `output_modalities`, `capabilities`, nullable `context_window`, and `provider_metadata`. Context window, when supplied, must be positive.

`ProviderException` extends `RuntimeException` and exposes only `ProviderErrorCode $error_code`, provider ID, nullable safe request ID, and a redacted message.

`ProviderIds` constants are exactly `openai_direct`, `openrouter_direct`, `wordpress_ai_client`.

- [ ] **Step 1: Write failing value-object tests** covering validation, nullable usage, unknown status, and ProviderException fields.
- [ ] **Step 2: Commit test-only RED** and require GitHub Actions `php-quality` to reach PHPUnit and fail only because the new classes do not exist.
- [ ] **Step 3: Implement the minimum contracts/value objects above.**
- [ ] **Step 4: Run/require `composer verify:php` GREEN on the exact head.**
- [ ] **Step 5: Commit** with `feat: add provider contracts and value objects`.

---

### Task 2: Credential Resolution, Secret Handling, Encryption, and Option Storage — RED -> GREEN

**Files:** `src/Providers/Credentials/*`, support fakes, credential tests.

**Interfaces:**

```php
enum CredentialSource: string {
	case ENVIRONMENT = 'environment';
	case CONSTANT = 'constant';
	case OPTION = 'option';
	case CORE = 'core';
	case NONE = 'none';
}

final class Secret implements \JsonSerializable {
	public function __construct( string $value );
	public function with_value( \Closure $consumer ): mixed;
	public function __toString(): string; // always [REDACTED]
	public function jsonSerialize(): string; // always [REDACTED]
	public function __debugInfo(): array; // no plaintext
}

final class ResolvedCredential {
	public function __construct(
		public readonly Secret $secret,
		public readonly CredentialSource $source
	) {}
}

interface CredentialSourceReader {
	public function environment( string $name ): ?string;
	public function constant( string $name ): ?string;
}

interface CredentialStore {
	public function load( string $provider_id ): ?Secret;
	public function save( string $provider_id, string $plaintext ): void;
	public function delete( string $provider_id ): void;
}

final class CredentialResolver {
	public function resolve( DirectProviderCredentialConfig $config ): ?ResolvedCredential;
}
```

`DirectProviderCredentialConfig::for_provider()` maps:
- OpenAI -> env/constant `OPENAI_API_KEY`, option `wp_rag_ai_openai_api_key`;
- OpenRouter -> env/constant `OPENROUTER_API_KEY`, option `wp_rag_ai_openrouter_api_key`;
- any other provider -> `InvalidArgumentException`.

`CredentialResolver` trims only source-selection whitespace. Non-empty env wins, then constant, then option. Blank env/constant must not shadow option.

Crypto contracts:

```php
interface CryptoCapabilities {
	public function sodium_available(): bool;
	public function aes_gcm_available(): bool;
}

interface CredentialCipher {
	public function encrypt( string $provider_id, string $plaintext ): string;
	public function decrypt( string $provider_id, string $envelope ): string;
}
```

`AuthenticatedCredentialCipher` must:
- derive 32 bytes with `hash_hkdf('sha256', wp_salt('auth') . "\0" . wp_salt('secure_auth'), 32, 'wp-rag-ai-chatbot:credential:v1:' . $provider_id, '')`;
- use AAD `wp-rag-ai-chatbot:<provider-id>:credential:v1`;
- prefer XChaCha20-Poly1305 when capability reports Sodium;
- otherwise use AES-256-GCM with 12-byte nonce and 16-byte tag;
- serialize strict JSON envelope `{v,alg,nonce,ciphertext[,tag]}` with base64 binary fields;
- reject malformed JSON, missing fields, unknown version/algorithm, wrong provider context, auth/decrypt failure, and absent secure backend with `ProviderException(CONFIGURATION)`;
- never treat stored bytes as plaintext.

`WordPressCredentialStore` rules:
- provider option name comes only from `DirectProviderCredentialConfig`;
- `save()` with trimmed empty plaintext calls delete instead;
- `add_option(..., '', false)`/`update_option(..., false)` semantics keep autoload disabled;
- `load()` decrypts and returns `Secret`;
- update failure followed by unequal readback throws configuration error;
- option plaintext is never returned from any config/status method.

- [ ] **Step 1: Write `CredentialResolverTest` RED** for precedence and blank skipping.
- [ ] **Step 2: Observe valid RED, implement `Secret`, config/source reader, resolver, and fake store.**
- [ ] **Step 3: Write cipher RED** for Sodium round-trip, explicit OpenSSL path, provider-context mismatch, malformed/tampered envelope, and no-backend failure.
- [ ] **Step 4: Implement `AuthenticatedCredentialCipher` minimally; require GREEN.**
- [ ] **Step 5: Write `WordPressCredentialStoreTest` RED** using Brain Monkey for add/update/delete/get option behavior and non-autoload semantics.
- [ ] **Step 6: Implement WordPress store; require GREEN.**
- [ ] **Step 7: Commit** with `feat: add secure provider credential storage`.

---

### Task 3: Secret Redaction and WordPress HTTP Policy — RED -> GREEN

**Files:** `src/Providers/Security/*`, `src/Providers/Http/*`, related unit tests/fake transport.

`SecretRedactor`:

```php
final class SecretRedactor {
	/** @param string[] $known_secrets */
	public function sanitize( string $text, array $known_secrets = array() ): string;
	public function sanitize_body( string $body, array $known_secrets = array() ): string;
}
```

Rules:
- replace exact known secret occurrences with `[REDACTED]`;
- redact case-insensitive `Authorization: Bearer <value>`, `api-key: <value>`, `x-api-key: <value>`;
- `sanitize_body()` first limits raw body to 2048 bytes and appends `[TRUNCATED]`, then redacts;
- no secret is placed in exception text.

HTTP types:

```php
final class HttpRequest {
	public function __construct(
		public readonly string $provider_id,
		public readonly string $method,
		public readonly string $url,
		public readonly array $headers,
		public readonly ?array $json_body,
		public readonly int $timeout,
		public readonly int $redirection = 0
	) {}
}

final class HttpResponse {
	public function __construct(
		public readonly int $status,
		public readonly array $headers,
		public readonly string $body
	) {}
}

interface HttpTransport {
	public function send( HttpRequest $request ): HttpResponse;
}
```

`WordPressHttpTransport::send()` calls `wp_remote_request()` with:
- method from request;
- `timeout` exactly request timeout;
- `redirection` exactly 0;
- headers passed unchanged;
- JSON-encoded body only when present;
- `Content-Type: application/json` supplied by adapter request construction.

It converts `WP_Error` into `HttpTransportException`, classifying timeout only when WordPress/cURL evidence identifies a timeout (including cURL error 28 or `timed out`); all other failures are transport.

`ProviderHttpClient`:

```php
public function generation( HttpRequest $request ): HttpResponse;
public function discovery( HttpRequest $request ): HttpResponse;
```

Rules:
- `generation()` sends exactly once;
- `discovery()` sends once and retries once only after `HttpTransportException` or response 502/503/504;
- no retry for 401/403/404/429, malformed payload decisions, 500/501/505+, or success;
- no sleep/backoff in M03.

- [ ] **Step 1: Write redactor RED**, including a key echoed in a 3000-byte upstream body.
- [ ] **Step 2: Implement redactor and require GREEN.**
- [ ] **Step 3: Write HTTP client/transport RED** proving timeouts, zero redirects, one generation attempt, allowed discovery retry, and forbidden retry cases.
- [ ] **Step 4: Implement transport/client and require GREEN.**
- [ ] **Step 5: Commit** with `feat: add provider HTTP safety boundary`.

---

### Task 4: Model Catalog Cache — RED -> GREEN

**Files:** `src/Providers/Cache/*`, `tests/Unit/Providers/Cache/CachedModelCatalogProviderTest.php`, fake catalog provider.

```php
interface ModelCatalogCache {
	/** @return ModelInfo[]|null */
	public function get( string $provider_id ): ?array;
	/** @param ModelInfo[] $models */
	public function put( string $provider_id, array $models ): void;
	public function invalidate( string $provider_id ): void;
}

final class CachedModelCatalogProvider implements ModelCatalogProvider {
	public function provider_id(): string;
	public function models(): array;
	public function refresh(): array;
	public function invalidate(): void;
}
```

`WordPressTransientModelCatalogCache` exact keys:
- `wp_rag_ai_models_openai_direct_v1`
- `wp_rag_ai_models_openrouter_direct_v1`
- WordPress AI key only if catalog support is actually implemented in Task 7.

Rules:
- TTL exactly 900 seconds;
- cache only arrays of successfully normalized `ModelInfo` serialized to plain arrays;
- invalid/malformed transient data is treated as cache miss and deleted;
- normal `models()` returns valid cache without calling upstream;
- miss calls upstream once and caches success;
- `refresh()` calls upstream even with cache present, replaces cache only on success;
- if refresh throws, preserve existing cached list and rethrow the normalized error;
- never cache credentials or ProviderException objects.

- [ ] **Step 1: Write cache/decorator RED** for hit/miss/TTL/invalidate/failed-refresh preservation.
- [ ] **Step 2: Implement transient cache and decorator; require GREEN.**
- [ ] **Step 3: Commit** with `feat: add provider model catalog cache`.

---

### Task 5: OpenAI Direct Adapter — RED -> GREEN

**Files:** `src/Providers/OpenAI/OpenAiProvider.php`, `tests/Unit/Providers/OpenAI/OpenAiProviderTest.php`.

`OpenAiProvider` implements both `GenerationProvider` and `ModelCatalogProvider`.

Constants:
- provider ID `openai_direct`;
- responses URL `https://api.openai.com/v1/responses`;
- models URL `https://api.openai.com/v1/models`.

Dependencies: `CredentialResolver`, OpenAI `DirectProviderCredentialConfig`, `ProviderHttpClient`, `SecretRedactor`.

Generation request must be POST with timeout 45, redirects 0, headers:
- `Authorization: Bearer <secret>`;
- `Content-Type: application/json`.

Body:

```php
array(
	'model' => $request->model_id,
	'input' => $request->input,
)
```

Add `instructions` only when non-null/non-empty. Add `max_output_tokens` only when non-null.

Fixture for successful normalization must cover:
- top-level `model` and `status`;
- nested `output[].content[]` entries with type `output_text`; concatenate output-text entries in order;
- usage `input_tokens`, `output_tokens`, `total_tokens`;
- `x-request-id` response header.

Status normalization:
- `completed` -> `GenerationStatus::COMPLETED`;
- `incomplete` -> `INCOMPLETE`;
- `failed` -> `FAILED`;
- any other non-empty status -> `UNKNOWN`.

Error mapping before parsing success body:
- 401 authentication;
- 403 authorization;
- 429 rate_limit;
- 5xx upstream_server;
- transport/timeout from HTTP client preserved;
- invalid JSON or successful JSON without output text -> malformed_response.

All error body text must pass `SecretRedactor`; request ID may be attached only from safe headers.

Model discovery GET:
- timeout 10, redirects 0, bearer auth;
- normalize each `data[]` object with non-empty string `id` to `ModelInfo`;
- `display_name = id` because OpenAI model-list response does not reliably provide a human display name;
- modalities/capabilities/context window remain unknown/empty unless explicitly present in the upstream object;
- preserve only safe scalar metadata such as `created`/`owned_by` when present;
- malformed top-level `data` -> malformed_response.

- [ ] **Step 1: Write complete mocked OpenAI RED** for request shape, one generation attempt, success normalization, malformed response, 401/429/500, timeout, redaction, model discovery, and discovery retry behavior inherited through client.
- [ ] **Step 2: Observe RED caused by missing adapter.**
- [ ] **Step 3: Implement minimum OpenAI adapter.**
- [ ] **Step 4: Require PHP GREEN and inspect test assertions count/log.**
- [ ] **Step 5: Commit** with `feat: add OpenAI direct provider`.

---

### Task 6: OpenRouter Direct Adapter — RED -> GREEN

**Files:** `src/Providers/OpenRouter/OpenRouterProvider.php`, `tests/Unit/Providers/OpenRouter/OpenRouterProviderTest.php`.

Constants:
- provider ID `openrouter_direct`;
- generation URL `https://openrouter.ai/api/v1/chat/completions`;
- models URL `https://openrouter.ai/api/v1/models`.

Generation POST headers/body use the same 45-second/no-redirect policy and bearer secret.

Messages body:
- include `{role: system, content: instructions}` only when instructions is non-null/non-empty;
- always include `{role: user, content: input}`;
- `model` is the configured request model;
- include `max_tokens` only when `max_output_tokens` is non-null.

Successful response normalization:
- `choices[0].message.content` must be a string;
- model from top-level `model` when present, otherwise request model;
- usage fields map `prompt_tokens` -> input, `completion_tokens` -> output, `total_tokens` -> total;
- finish reason `stop` -> COMPLETED, `length` -> INCOMPLETE, otherwise UNKNOWN;
- safe request ID from response `x-request-id` when present, otherwise top-level response `id` may be stored as provider metadata/request ID if scalar.

Error mapping/redaction matches Task 5.

Model discovery normalizes only explicit OpenRouter metadata:
- `id` required;
- `name` -> display name when non-empty, else ID;
- integer `context_length` -> context window;
- `architecture.input_modalities` and `architecture.output_modalities` arrays when present;
- `supported_parameters` array copied as explicit capability/parameter metadata; do not infer support from model name.

- [ ] **Step 1: Write mocked OpenRouter RED** covering request shape, response/usage/finish normalization, errors/redaction, and explicit model metadata.
- [ ] **Step 2: Observe missing-adapter RED.**
- [ ] **Step 3: Implement adapter and require GREEN.**
- [ ] **Step 4: Commit** with `feat: add OpenRouter direct provider`.

---

### Task 7: WordPress 7 AI Client Adapter, Registry, and Non-Secret Configuration Service — RED -> GREEN

**Files:** WordPress AI adapter, registry/config service, bootstrap wiring, corresponding unit tests.

`WordPressAiClientProvider` implements `GenerationProvider` only in M03. Do not implement a model catalog unless the public Core API exposes a stable catalog surface needed by this milestone.

Availability:
- `available()` returns `function_exists('wp_ai_client_prompt')`;
- on WP 6.9/missing function, `generate()` throws `ProviderException(UNSUPPORTED_CAPABILITY)` or configuration/unavailable category defined by tests without fataling.

When available:

```php
$builder = wp_ai_client_prompt( $request->input );
if ( null !== $request->instructions && '' !== trim( $request->instructions ) ) {
	$builder->using_system_instruction( $request->instructions );
}
if ( null !== $request->max_output_tokens ) {
	$builder->using_max_tokens( $request->max_output_tokens );
}
if ( '' !== trim( $request->model_id ) ) {
	$builder->using_model_preference( $request->model_id );
}
$result = $builder->generate_text_result();
```

If Core returns `WP_Error`, normalize it without exposing connector credentials. Do not call private `_wp_*` connector APIs and do not duplicate Core-managed credentials.

Because Core result DTO internals may evolve, isolate extraction in private adapter methods and unit-test against a small fake object exposing only the public properties/methods actually used. If reliable usage/model metadata is unavailable, return null usage/model metadata rather than guessing.

`ProviderDescriptor` contains provider ID, display name, availability, configured state, credential source, and capability IDs; no secret fields.

`ProviderRegistry` API:

```php
public function register( string $provider_id, GenerationProvider $provider, ?ModelCatalogProvider $catalog = null ): void;
public function generation( string $provider_id ): GenerationProvider;
public function catalog( string $provider_id ): ?ModelCatalogProvider;
/** @return string[] */
public function ids(): array;
```

Duplicate registration throws `InvalidArgumentException`; unknown lookup throws `OutOfBoundsException`.

`ProviderConfigurationService::describe( string $provider_id ): ProviderDescriptor`:
- OpenAI/OpenRouter configured state comes from `CredentialResolver` and reports source only (`environment|constant|option|none`);
- WordPress AI Client reports `core` when public API is available and Core can be considered configured through public support checks; otherwise available/unconfigured or unavailable without inspecting private connector secrets;
- never returns `Secret`, encrypted option payload, salts, headers, or arbitrary request data.

`ProviderBootstrap::register()` composes the three providers server-side but must not issue generation/model HTTP calls during `plugins_loaded`.

- [ ] **Step 1: Write WP AI Client RED** for absent function and fake public-builder success/error behavior.
- [ ] **Step 2: Implement adapter and require GREEN.**
- [ ] **Step 3: Write registry/config-service RED** proving no secrets in descriptors and no network calls during registry construction.
- [ ] **Step 4: Implement registry/config service/bootstrap and wire `ProviderBootstrap::register` from `Core\Bootstrap` on `plugins_loaded` after database setup.**
- [ ] **Step 5: Update `BootstrapTest` hook expectations and require GREEN.**
- [ ] **Step 6: Commit** with `feat: add WordPress AI compatibility and provider registry`.

---

### Task 8: Real WordPress Provider Integration and Opt-In Live Smoke — RED -> GREEN

**Files:** `scripts/test-wp-providers.php`, `.sh`, live smoke scripts, `package.json`, `.github/workflows/ci.yml`, `scripts/assert-package.sh`.

Permanent real-WordPress smoke must make zero external provider calls and verify:
1. plugin activation still succeeds;
2. saving a deterministic fake OpenAI credential through `WordPressCredentialStore` leaves no plaintext in `wp_options`;
3. stored envelope JSON has `v=1`, approved algorithm, base64 fields, and autoload disabled;
4. load round-trips the secret through `Secret::with_value()` only;
5. deletion removes option;
6. controlled environment variable precedence wins over option and blank environment falls through where feasible in process;
7. WordPress 6.9 safely reports WP AI Client unavailable;
8. on the CI WordPress version, feature detection matches `function_exists('wp_ai_client_prompt')` and does not call an external provider;
9. provider configuration descriptors contain only non-secret state.

Add npm script `test:wp:providers` and run it in `wordpress-smoke` after database smoke.

Optional live script behavior:
- exits 0 with `SKIP` unless `WP_RAG_AI_LIVE_PROVIDER_TESTS=1`;
- accepts provider selection `openai` or `openrouter` from CLI argument;
- requires corresponding environment key and never uses plugin option for live CI smoke;
- performs one model discovery and one minimal text generation using an explicitly supplied model environment variable (`WP_RAG_AI_LIVE_OPENAI_MODEL` or `WP_RAG_AI_LIVE_OPENROUTER_MODEL`); if model env var is absent, skip generation rather than hard-code a model ID;
- never prints secret or Authorization header;
- live script is not referenced by normal GitHub Actions.

Package guard must require all runtime `src/Providers/**` classes but reject tests/live credentials/config files. Scripts used only for CI/live development remain excluded from production ZIP unless the existing packaging policy explicitly includes scripts; the runtime must not depend on them.

- [ ] **Step 1: Add real-WP provider smoke first and commit RED** before wiring any missing production/bootstrap behavior it exposes.
- [ ] **Step 2: Observe RED at intended provider behavior only; fix test harness issues before production code if necessary.**
- [ ] **Step 3: Implement minimum integration/wiring until `test:wp:providers` passes.**
- [ ] **Step 4: Add opt-in live scripts and tests for their skip/gating logic without making live calls in CI.**
- [ ] **Step 5: Run full four-job exact-head CI and require all jobs GREEN.**
- [ ] **Step 6: Commit** with `test: add provider WordPress integration smoke`.

---

### Task 9: Independent Review, Security/Performance Audit, Durable Evidence, and Final Verification

**Review scope:** full `main...feat/m03-ai-providers-credentials` diff.

Use `superpowers:requesting-code-review`; because this runtime has no real subagent dispatch, apply ADR-017 fresh-context inline review and record that limitation honestly.

Review checklist:
- no plaintext keys in fixtures, docs, errors, debug output, options, bundles, or logs;
- no arbitrary provider URL/base URL;
- no redirect-following credential request;
- env/constant/option precedence correct for blank values;
- authenticated encryption envelope/KDF exactly matches spec;
- fail-closed crypto/decrypt errors;
- model cache cannot convert auth/upstream failures to successful empty catalogs;
- generation never retries;
- discovery retries only transport/502/503/504 and only once;
- HTTP timeouts exact 45/10;
- unknown model capabilities are not inferred from names;
- WP AI adapter uses only public functions/methods and degrades on WP 6.9;
- no paid/live provider calls in normal CI;
- no M08 embeddings runtime, M11 RAG, M12 admin UI, tools, streaming, pricing tables, or public provider REST scope creep;
- package contains required provider runtime but no dev tests/secrets.

For every Critical/Important finding:
1. write focused failing regression test;
2. observe valid RED;
3. implement smallest fix;
4. require GREEN;
5. re-review finding.

Then run fresh exact-candidate verification:
- `php-quality` including Composer audit/WPCS/PHPStan/PHPUnit;
- `js-quality` unchanged baseline;
- `wordpress-smoke` activation + M02 database + M03 provider config smoke;
- `package` strict archive + artifact upload.

Update durable ledgers:
- `docs/milestones/M03-ai-providers-credentials.md` -> COMPLETE only after runtime candidate green and review closed;
- `docs/progress/STATUS.md` -> M04 planning/integration-decision state;
- `docs/progress/TEST-MATRIX.md` -> provider contract/credential/cache/WP integration evidence;
- `docs/progress/SECURITY.md` -> credential encryption/redaction/HTTP/SSRF/retry evidence;
- `docs/progress/KNOWN-ISSUES.md` -> only real remaining limitations;
- `docs/progress/TECH-DEBT.md` -> only deferred non-blocking debt;
- `docs/DECISIONS.md` -> M03 ADRs for credential precedence/encryption, fixed endpoints/retry policy, and optional WP7 adapter if not already fully captured by ADR-003/004.

Create one documentation-complete commit, then run the same permanent CI on that exact SHA. Capture package artifact ID/digest. Only after all four final jobs are green use `superpowers:finishing-a-development-branch` and present the integration menu against `main`.

---

## Plan Self-Review Checklist

Before execution begins, verify:
- every approved design requirement maps to a task above;
- no placeholder/TBD language exists;
- all multi-word PHP methods are snake_case;
- `GenerationRequest`, `GenerationResult`, `Usage`, `ModelInfo`, credential, HTTP, cache, and registry signatures stay consistent between tasks;
- OpenAI uses Responses API and OpenRouter uses chat-completions only inside adapters;
- WordPress 7 integration uses only documented public AI Client APIs;
- no task introduces embedding runtime, RAG, public provider REST, admin UI, tools, or streaming;
- normal CI remains fully mocked/no-credit for provider network calls;
- every runtime behavior begins with observed RED before production implementation.
