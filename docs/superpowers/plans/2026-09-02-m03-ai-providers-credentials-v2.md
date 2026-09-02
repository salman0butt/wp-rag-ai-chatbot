# M03 AI Providers, Credentials & Compatibility Implementation Plan — V2

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Supersedes:** `docs/superpowers/plans/2026-09-02-m03-ai-providers-credentials.md`. V2 fixes self-review gaps around secret exposure, provider health, and WordPress 7 result normalization and is authoritative for execution.

**Goal:** Implement secure provider-neutral text generation and model discovery for OpenAI Direct, OpenRouter Direct, and optional WordPress 7 AI Client compatibility without exposing credentials or consuming paid provider credits in normal CI.

**Architecture:** Add a focused `Providers` module containing normalized value objects/contracts, server-only credential resolution/encryption, a fixed-endpoint WordPress HTTP boundary, model-catalog caching, and three adapters. Direct adapters work on WordPress 6.9+; the WordPress AI Client adapter is feature-detected and uses only documented WordPress 7 public APIs. Provider-specific payloads never escape adapter boundaries.

**Tech Stack:** PHP 8.2+, WordPress 6.9+/optional 7.0 AI Client, WordPress HTTP API, Options/Transients API, Sodium/OpenSSL AEAD, PHPUnit 10, Brain Monkey, WPCS, PHPStan, wp-env/WP-CLI, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-02-m03-ai-providers-credentials-design.md`

## Global Constraints

- WordPress/PHP is the mandatory runtime; do not add a provider SDK dependency.
- WordPress 6.9+ and PHP 8.2+ remain the minimums.
- WordPress 7 AI Client is optional and must be feature-detected with public APIs only.
- Direct credentials remain server-only and resolve in this order: non-empty environment -> non-empty constant -> encrypted option.
- Option names are exactly `wp_rag_ai_openai_api_key` and `wp_rag_ai_openrouter_api_key`; autoload must be disabled.
- Stored credentials use authenticated encryption only; malformed/undecryptable ciphertext is a configuration error, never plaintext fallback.
- Fixed adapter endpoints only; no arbitrary/user-controlled provider base URL in M03.
- Credential-bearing requests use `redirection => 0`.
- Generation timeout = 45 seconds, exactly one attempt.
- Model discovery timeout = 10 seconds, at most two attempts; retry only transport failure or HTTP 502/503/504.
- Model cache TTL = 900 seconds; failed forced refresh preserves existing valid cache.
- Unknown capabilities remain unknown; never infer from model names.
- Normal CI never calls paid provider APIs and contains no real API key.
- Live smoke requires `WP_RAG_AI_LIVE_PROVIDER_TESTS=1` and provider environment key; no hard-coded live model ID.
- No embeddings runtime, RAG orchestration, public streaming, tools/actions, provider admin UI, pricing tables, or public arbitrary-prompt REST in M03.
- Multi-word PHP methods use snake_case to match repository WPCS.
- Every runtime behavior follows RED -> observed expected failure -> minimal GREEN -> review.
- Do not merge to `main` without the finishing-a-development-branch integration choice.

## File Map

Create `src/Providers/` with these responsibility groups:
- normalized contracts/value objects: `ProviderIds.php`, `GenerationProvider.php`, `ModelCatalogProvider.php`, `GenerationRequest.php`, `GenerationResult.php`, `GenerationStatus.php`, `Usage.php`, `ModelInfo.php`, `ProviderErrorCode.php`, `ProviderException.php`, `ProviderHealthStatus.php`, `ProviderHealth.php`, `ProviderDescriptor.php`, `ProviderRegistry.php`, `ProviderConfigurationService.php`, `ProviderBootstrap.php`;
- credentials: `Credentials/CredentialSource.php`, `Secret.php`, `ResolvedCredential.php`, `DirectProviderCredentialConfig.php`, `CredentialSourceReader.php`, `RuntimeCredentialSourceReader.php`, `CredentialStore.php`, `CredentialResolver.php`, `CryptoCapabilities.php`, `RuntimeCryptoCapabilities.php`, `CredentialCipher.php`, `AuthenticatedCredentialCipher.php`, `WordPressCredentialStore.php`;
- security/http/cache: `Security/SecretRedactor.php`, `Http/HttpRequest.php`, `Http/HttpResponse.php`, `Http/HttpTransport.php`, `Http/HttpTransportException.php`, `Http/WordPressHttpTransport.php`, `Http/ProviderHttpClient.php`, `Cache/ModelCatalogCache.php`, `Cache/WordPressTransientModelCatalogCache.php`, `Cache/CachedModelCatalogProvider.php`;
- adapters: `OpenAI/OpenAiProvider.php`, `OpenRouter/OpenRouterProvider.php`, `WordPressAi/WordPressAiClientProvider.php`.

Create test support under `tests/Support/Providers/` and unit tests under `tests/Unit/Providers/` mirroring each responsibility. Create `scripts/test-wp-providers.php`, `scripts/test-wp-providers.sh`, `scripts/live-provider-smoke.php`, `scripts/live-provider-smoke.sh`.

Modify `src/Core/Bootstrap.php`, `tests/Unit/Core/BootstrapTest.php`, `package.json`, `.github/workflows/ci.yml`, `scripts/assert-package.sh`, and M03 progress/security/decision ledgers.

---

### Task 1: Normalized Provider Contracts and Value Objects — RED -> GREEN

**Produces exact interfaces:**

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
```

Enums:

```php
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

enum ProviderHealthStatus: string {
	case UNAVAILABLE = 'unavailable';
	case UNCONFIGURED = 'unconfigured';
	case CONFIGURED = 'configured';
	case AUTHENTICATION_FAILED = 'authentication_failed';
	case RATE_LIMITED = 'rate_limited';
	case UPSTREAM_ERROR = 'upstream_error';
	case HEALTHY = 'healthy';
}
```

`GenerationRequest` exact constructor:

```php
public function __construct(
	public readonly string $model_id,
	public readonly string $input,
	public readonly ?string $instructions = null,
	public readonly ?int $max_output_tokens = null
)
```

Validation: trimmed model ID and input must be non-empty; max output tokens if supplied must be 1..32768.

`Usage`: nullable non-negative `input_tokens`, `output_tokens`, `total_tokens`, plus `safe_metadata`; never fabricate missing token values as zero.

`GenerationResult`: provider ID, actual/fallback model ID, output text, `GenerationStatus`, `Usage`, nullable safe request ID. `COMPLETED` requires non-empty output.

`ModelInfo`: provider ID/model ID required, display name, input/output modality arrays, capability strings, nullable positive context window, safe provider metadata.

`ProviderHealth`: provider ID + `ProviderHealthStatus` + nullable safe request ID/message; no secret field.

`ProviderException`: `RuntimeException` plus public readonly `ProviderErrorCode $error_code`, provider ID, nullable safe request ID. Message must already be sanitized by caller.

`ProviderIds` constants: `openai_direct`, `openrouter_direct`, `wordpress_ai_client`.

- [ ] Add `tests/Unit/Providers/ProviderValueObjectsTest.php` first; assert all validations/statuses and that null usage remains null.
- [ ] Commit test-only RED and require CI PHP to reach PHPUnit and fail only for missing M03 classes.
- [ ] Implement only these contracts/value objects.
- [ ] Require `composer verify:php` GREEN; commit `feat: add provider contracts and value objects`.

---

### Task 2: Credential Resolution and Non-Leaking Secret API — RED -> GREEN

**Exact credential types:**

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
	public function with_value( \Closure $consumer ): void;
	public function __toString(): string;      // [REDACTED]
	public function jsonSerialize(): string;   // [REDACTED]
	public function __debugInfo(): array;      // redacted/no plaintext
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
```

`Secret::with_value()` invokes the consumer with plaintext and always returns `void`; callers cannot obtain plaintext as the method return value. Tests assert string cast, `json_encode()`, `var_export()`/debug info never contain secret text.

`DirectProviderCredentialConfig::for_provider()` fixed mapping:
- OpenAI -> env/constant `OPENAI_API_KEY`, option `wp_rag_ai_openai_api_key`;
- OpenRouter -> env/constant `OPENROUTER_API_KEY`, option `wp_rag_ai_openrouter_api_key`;
- other ID -> `InvalidArgumentException`.

`CredentialResolver::resolve()` trims source-selection values only. Non-empty environment wins; blank environment falls through to constant; blank constant falls through to option; no source returns null.

- [ ] Write resolver/Secret RED tests first, including blank precedence and all common serialization/debug surfaces.
- [ ] Observe expected missing-class RED.
- [ ] Implement `Secret`, source/config/readers, fake reader/store, resolver; require GREEN.
- [ ] Commit `feat: add provider credential resolution`.

---

### Task 3: Authenticated Credential Encryption and WordPress Option Store — RED -> GREEN

**Crypto contracts:**

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

`AuthenticatedCredentialCipher` exact derivation:

```php
$key = hash_hkdf(
	'sha256',
	wp_salt( 'auth' ) . "\0" . wp_salt( 'secure_auth' ),
	32,
	'wp-rag-ai-chatbot:credential:v1:' . $provider_id,
	''
);
$aad = 'wp-rag-ai-chatbot:' . $provider_id . ':credential:v1';
```

Backends:
1. prefer XChaCha20-Poly1305 if Sodium capability is true; random nonce uses Sodium NPUBBYTES constant;
2. otherwise AES-256-GCM if supported; 12-byte nonce, 16-byte tag;
3. otherwise throw `ProviderException(CONFIGURATION)`.

Serialized JSON contains exactly `v=1`, `alg`, base64 `nonce`, base64 `ciphertext`, plus base64 `tag` for AES-GCM. Strictly reject malformed JSON/base64, unknown version/algorithm, provider-context mismatch, auth/decrypt failure, and algorithm unavailable at decrypt time. Never interpret envelope bytes as plaintext.

`WordPressCredentialStore`:
- option names only via fixed config mapping;
- `load()` decrypts then returns `Secret`;
- blank save deletes option;
- new option uses `add_option( $name, $envelope, '', false )`;
- replacement uses `update_option( $name, $envelope, false )`;
- if update returns false but readback equals desired envelope, accept WordPress no-change semantics; otherwise throw configuration error;
- delete uses `delete_option()`;
- real-WP integration later verifies wp_options autoload is disabled.

- [ ] Write cipher RED: Sodium round-trip, forced OpenSSL path, tamper, provider mismatch, malformed envelope, no backend.
- [ ] Implement cipher; require GREEN.
- [ ] Write Brain Monkey option-store RED for add/update/read/delete/failure behavior.
- [ ] Implement store; require GREEN.
- [ ] Commit `feat: add encrypted provider credential storage`.

---

### Task 4: Secret Redaction and Fixed WordPress HTTP Boundary — RED -> GREEN

`SecretRedactor` API:

```php
public function sanitize( string $text, array $known_secrets = array() ): string;
public function sanitize_body( string $body, array $known_secrets = array() ): string;
```

Rules: exact known secrets -> `[REDACTED]`; case-insensitive Authorization Bearer/api-key/x-api-key header values -> `[REDACTED]`; raw body is limited to 2048 bytes and appends `[TRUNCATED]` before/while sanitizing.

HTTP DTO/contracts:

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

`WordPressHttpTransport` uses `wp_remote_request()` and returns normalized status/headers/body. It must pass explicit timeout and `redirection=0`. It throws `HttpTransportException` with `ProviderErrorCode::TIMEOUT` only for clear timeout evidence (including cURL error 28/`timed out`), otherwise TRANSPORT. It never includes request Authorization header in error text.

`ProviderHttpClient`:

```php
public function generation( HttpRequest $request ): HttpResponse; // one send only
public function discovery( HttpRequest $request ): HttpResponse;  // max two sends
```

Discovery retry only on `HttpTransportException` or status 502/503/504. No retry for 401/403/404/429, 500/501/505+, success, or malformed payload decisions. No sleep/backoff.

- [ ] Write redactor RED including a secret echoed after byte 2048 and mixed-case header names.
- [ ] Implement redactor, GREEN.
- [ ] Write transport/client RED proving 45/10 timeouts are carried by request objects, redirects stay zero, generation sends once, discovery allowed/forbidden retries are exact.
- [ ] Implement transport/client, GREEN; commit `feat: add provider HTTP safety boundary`.

---

### Task 5: Model Catalog Cache — RED -> GREEN

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

Transient keys: `wp_rag_ai_models_openai_direct_v1`, `wp_rag_ai_models_openrouter_direct_v1`; only add WordPress AI key if Task 8 actually exposes a public catalog. TTL exactly 900.

Cache values are plain normalized arrays reconstructed into `ModelInfo`; malformed transient data is deleted and treated as miss. `models()` uses valid cache. `refresh()` calls upstream and replaces cache only on successful normalized result; an exception preserves old cache and rethrows. Credentials/errors are never cached as successful catalogs.

- [ ] RED tests for hit, miss, TTL, invalidate, malformed transient, successful refresh, failed-refresh preservation.
- [ ] Implement cache/decorator; GREEN; commit `feat: add model catalog cache`.

---

### Task 6: OpenAI Direct Adapter — RED -> GREEN

`OpenAiProvider` implements `GenerationProvider` + `ModelCatalogProvider`.

Constants:
- ID `openai_direct`;
- Responses `https://api.openai.com/v1/responses`;
- models `https://api.openai.com/v1/models`.

Dependencies: resolver/config, `ProviderHttpClient`, redactor.

Generation request: POST, timeout 45, redirects 0, bearer auth, JSON content type. Body begins with `model` and `input`; add `instructions` only when non-empty; add `max_output_tokens` only when non-null.

Successful fixture must normalize:
- top-level model/status;
- ordered concatenation of `output[].content[]` entries where `type=output_text` and `text` is string;
- usage `input_tokens`, `output_tokens`, `total_tokens` only when explicitly numeric;
- safe `x-request-id` header.

Status: `completed` -> COMPLETED; `incomplete` -> INCOMPLETE; `failed` -> FAILED; otherwise UNKNOWN.

Errors: 401 authentication, 403 authorization, 429 rate limit, 5xx upstream server; transport/timeout preserve specific code; invalid JSON or successful response without text -> malformed response. Upstream error body must be redacted/truncated before ProviderException construction.

Model GET: timeout 10, redirects 0, bearer auth. `data[]` item requires non-empty string ID. Display name defaults to ID. Do not infer capability/modalities/context. Preserve only safe explicit metadata such as scalar `created`/`owned_by`.

`health()` behavior is local/non-paid: unavailable never applies to direct adapter; no credential -> UNCONFIGURED; credential present without request -> CONFIGURED. Authentication/rate/upstream/healthy statuses may be derived from the most recent explicit operation result by higher-level caller later; M03 does not perform a paid health ping.

- [ ] Write mocked adapter RED for request shape, successful normalization, nullable usage, request ID, malformed success, 401/403/429/500, timeout/redaction, model normalization and discovery retry path.
- [ ] Observe missing-adapter RED.
- [ ] Implement minimal adapter, require GREEN; commit `feat: add OpenAI direct provider`.

---

### Task 7: OpenRouter Direct Adapter — RED -> GREEN

`OpenRouterProvider` implements generation + model catalog.

Constants:
- ID `openrouter_direct`;
- generation `https://openrouter.ai/api/v1/chat/completions`;
- models `https://openrouter.ai/api/v1/models`.

POST body: model; messages with optional system instruction then user message; optional `max_tokens`. Same 45s/zero redirect/bearer policy.

Normalize first choice `message.content`; model from response when non-empty else request model; usage prompt/completion/total -> normalized input/output/total; finish `stop` -> COMPLETED, `length` -> INCOMPLETE, otherwise UNKNOWN. Safe request ID: response header `x-request-id`, else scalar top-level `id`.

Error/redaction rules match OpenAI.

Model normalization uses only explicit fields: required `id`; display name from non-empty `name` else ID; positive integer `context_length`; arrays `architecture.input_modalities`, `architecture.output_modalities`, `supported_parameters`. Supported parameters may be retained as explicit capability/metadata strings but no model-name inference.

Local health: no credential -> UNCONFIGURED; credential present -> CONFIGURED; no paid ping.

- [ ] Write complete mocked RED.
- [ ] Implement adapter; exact-head PHP GREEN; commit `feat: add OpenRouter direct provider`.

---

### Task 8: WordPress 7 AI Client Adapter — RED -> GREEN

`WordPressAiClientProvider` implements `GenerationProvider`; no model catalog in M03 unless a stable public catalog API is proven necessary.

Availability requires both `function_exists( 'wp_ai_client_prompt' )` and, when available, `wp_supports_ai()` not returning false. On WP 6.9/missing function it returns `available() === false` and `generate()` throws `ProviderException(UNSUPPORTED_CAPABILITY)` without fatal.

Public builder calls only:

```php
$builder = wp_ai_client_prompt( $request->input );
if ( null !== $request->instructions && '' !== trim( $request->instructions ) ) {
	$builder->using_system_instruction( $request->instructions );
}
if ( null !== $request->max_output_tokens ) {
	$builder->using_max_tokens( $request->max_output_tokens );
}
$builder->using_model_preference( $request->model_id );
$result = $builder->generate_text_result();
```

If `$result` is `WP_Error`, map safe error data/message to normalized ProviderException with redaction and no connector-secret inspection.

For a successful documented `GenerativeAiResult`, use only public methods:
- output: `$result->toText()`;
- safe request/result ID: `$result->getId()`;
- normalized array metadata: `$data = $result->toArray()`;
- model ID from `$data['modelMetadata']['id']` when string, otherwise request model;
- usage from `$data['tokenUsage']['promptTokens']` and `completionTokens` only when numeric; total remains null unless an explicit total field exists;
- finish reason from `$data['candidates'][0]['finishReason']`; `stop` -> COMPLETED, `length`/`max_tokens` -> INCOMPLETE, otherwise UNKNOWN.

If `toText()` throws/no valid text, normalize malformed response. Never call private `_wp_*` connector APIs and never copy Core credentials to plugin options.

Provider health: missing API/wp_supports_ai false -> UNAVAILABLE; public AI support enabled -> CONFIGURED without claiming a provider is actually healthy; generation success can return result but M03 still avoids a separate paid health ping.

- [ ] RED tests with fake builder/result object exposing exactly `using_system_instruction`, `using_max_tokens`, `using_model_preference`, `generate_text_result`, `toText`, `getId`, `toArray`.
- [ ] Cover absent function/support false, WP_Error, successful result, unknown finish reason, missing metadata.
- [ ] Implement adapter and require GREEN; commit `feat: add WordPress AI Client compatibility`.

---

### Task 9: Provider Registry, Configuration Service, and Bootstrap — RED -> GREEN

`ProviderDescriptor` contains only provider ID, display name, `ProviderHealth`, credential source (`environment|constant|option|core|none`), and capability strings; no secret/ciphertext/header/KDF data.

Registry:

```php
public function register( string $provider_id, GenerationProvider $provider, ?ModelCatalogProvider $catalog = null ): void;
public function generation( string $provider_id ): GenerationProvider;
public function catalog( string $provider_id ): ?ModelCatalogProvider;
/** @return string[] */
public function ids(): array;
```

Duplicate registration -> `InvalidArgumentException`; unknown generation lookup -> `OutOfBoundsException`.

`ProviderConfigurationService::describe()`:
- direct providers resolve credential only to determine configured/source; Secret value is never surfaced;
- WordPress AI source is `core` when available, otherwise `none`;
- descriptor generation must not call model discovery or paid generation.

`ProviderBootstrap::register()` composes transport/client, redactor, credential components, OpenAI/OpenRouter providers wrapped with cached model catalogs, WP AI provider, registry/config service. It must issue zero outbound provider calls during `plugins_loaded`.

Wire bootstrap from `Core\Bootstrap` after existing database migration hook registration without altering M02 behavior.

- [ ] Registry/config/bootstrap tests RED first, including a fake transport that fails the test if called during composition.
- [ ] Implement and update `BootstrapTest` hook expectations.
- [ ] Require GREEN; commit `feat: register provider infrastructure`.

---

### Task 10: Real WordPress Provider Integration and Optional Live Smoke — RED -> GREEN

Create permanent `scripts/test-wp-providers.php/.sh`; normal test makes no external provider call and verifies:
1. plugin activation remains clean;
2. deterministic fake OpenAI credential saved via `WordPressCredentialStore` is not present plaintext in wp_options;
3. envelope has v=1, approved algorithm, base64 fields;
4. wp_options autoload is disabled;
5. decrypted round-trip can only be observed inside `Secret::with_value()` callback;
6. delete removes option;
7. env precedence and blank fall-through are verified where controllable in process;
8. feature detection result equals actual `function_exists('wp_ai_client_prompt')`/`wp_supports_ai()` state without generation;
9. provider descriptors serialize with no secret/ciphertext/KDF/header fields.

Add `test:wp:providers` package script and append it to permanent `wordpress-smoke` after M02 database smoke.

Optional live smoke:
- skip with exit 0 unless `WP_RAG_AI_LIVE_PROVIDER_TESTS=1`;
- CLI provider `openai|openrouter` required;
- corresponding environment key required;
- model discovery may run; generation runs only when `WP_RAG_AI_LIVE_OPENAI_MODEL` or `WP_RAG_AI_LIVE_OPENROUTER_MODEL` is non-empty;
- one generation max, never print secret/auth header;
- normal CI never invokes live script.

Package assertion requires all runtime Provider classes through the existing allowlist policy. Development smoke scripts remain excluded from production ZIP if current packaging excludes `scripts/`; runtime code must not depend on them.

- [ ] Add real-WP test/CI wiring first and commit RED if production wiring/behavior is missing.
- [ ] Fix only test-harness issues until RED is intended provider failure.
- [ ] Implement minimum production fix; require real-WP GREEN.
- [ ] Add live-script gating tests without live call.
- [ ] Require all four exact-head jobs GREEN; commit `test: add provider WordPress integration smoke`.

---

### Task 11: Review, Security/Performance Audit, Durable Ledgers, Final Verification

Use `superpowers:requesting-code-review`; ADR-017 permits fresh-context inline review because no real subagent dispatcher exists here.

Review full `main...feat/m03-ai-providers-credentials` for:
- secret leakage in any serialization/log/error/fixture/bundle;
- credential precedence including blank values;
- KDF/envelope/backend preference exactness and fail-closed decryption;
- non-autoloaded option storage;
- fixed HTTPS endpoints/no arbitrary base URL;
- zero redirects;
- exact 45s generation/10s discovery timeouts;
- generation never retries;
- discovery retries exactly transport/502/503/504 once;
- auth/rate/upstream errors never become empty successful model lists;
- failed refresh preserves valid cache;
- no capability inference by model name;
- WP AI adapter public APIs only and graceful WP 6.9 degradation;
- no paid provider calls in normal CI;
- no M08/M11/M12/tools/streaming/pricing/public-prompt scope creep;
- production ZIP contains runtime providers and excludes dev secrets/tests/scripts per packaging policy.

Every Critical/Important finding gets a focused RED regression test, minimal fix, GREEN, and re-review.

Fresh runtime candidate must pass all four jobs: PHP quality (including Composer audit/WPCS/PHPStan/PHPUnit), JS quality, WordPress smoke (activation + M02 + M03), package/artifact.

Update `docs/DECISIONS.md`, M03 milestone, STATUS, TEST-MATRIX, SECURITY, KNOWN-ISSUES, TECH-DEBT with exact RED/GREEN/run/artifact evidence. Create one documentation-complete commit and run permanent CI on that exact SHA. Capture artifact ID/digest. Only then invoke finishing-a-development-branch and present the integration menu against `main`.

## Self-Review Result

- Spec coverage: all approved provider, credential, HTTP, cache, WP7, integration, live-smoke, security/performance, review, and final-CI requirements map to Tasks 1-11.
- Placeholder scan: no TBD/TODO/"implement later" execution placeholders remain.
- Type consistency: `Secret::with_value()` is `void`; provider/model/credential/HTTP/cache signatures are consistent across consumers.
- Scope: no embeddings runtime, RAG, public streaming, tools/actions, admin UI, arbitrary provider endpoints, or pricing tables are introduced.
- WordPress 7 compatibility is pinned to documented public builder/result methods; private connector APIs are explicitly forbidden.
