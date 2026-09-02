# M03 — AI Providers, Credentials & Compatibility Design

Status: APPROVED BY USER; SELF-REVIEWED; AUTHORITATIVE FOR M03 IMPLEMENTATION

## Goal
Build a WordPress/PHP provider foundation that supports OpenAI Direct and OpenRouter Direct on WordPress 6.9+, adds an optional WordPress 7.0+ AI Client adapter, keeps credentials server-side, normalizes models/usage/errors, and is fully testable without consuming paid API credits in normal CI.

## Scope

### In scope
- Provider registry and narrow provider/capability contracts.
- OpenAI Direct generation and model discovery.
- OpenRouter Direct generation and model discovery.
- WordPress 7.0+ AI Client feature detection and adapter boundary.
- Server-side credential resolution, secure storage, redaction, and precedence.
- Normalized provider errors, usage, request metadata, and model metadata.
- Bounded HTTP timeout/retry policy.
- Model discovery cache with explicit invalidation support.
- Mock contract tests and WordPress integration tests.
- Opt-in live smoke commands that run only when explicit provider credentials are present.

### Out of scope
- Embedding generation/indexing and vector compatibility runtime (M08).
- Production RAG orchestration (M11).
- Provider administration UI (M12).
- Public chat streaming protocol and frontend streaming transport.
- Arbitrary provider base URLs or user-configurable HTTP endpoints.
- Browser-side provider calls or browser-visible credentials.

## Architectural approach
Use a hybrid provider architecture.

1. OpenAI Direct and OpenRouter Direct are first-class adapters available on WordPress 6.9+.
2. WordPress 7.0+ may additionally use the native AI Client/Connectors path when the required public Core APIs are available.
3. Domain/application code depends only on internal provider contracts and normalized value objects.
4. Vendor-specific JSON, headers, URL paths, and parsing stay inside the owning adapter.
5. Provider model IDs remain configuration/discovery data and are never hard-coded into domain logic.

This preserves the approved WordPress 6.9 baseline while taking advantage of WordPress 7 AI infrastructure without making it mandatory.

## Provider contract boundaries

### GenerationProvider
Represents text generation for M03. It accepts a normalized generation request and returns a normalized generation result.

The request contains:
- model ID,
- system/developer-style instruction text where supported by the adapter,
- user input,
- bounded generation parameters supported by the normalized contract,
- optional metadata used only internally.

The result contains:
- output text,
- provider ID,
- model ID actually used when available,
- normalized token/usage data when returned by the provider,
- provider request ID or equivalent safe diagnostic metadata when available,
- finish/status information normalized into stable internal values.

M03 does not expose tools, vision, audio, embeddings, or public streaming through this contract. Capability metadata can report future support without implementing those later milestone behaviors.

### ModelCatalogProvider
Returns normalized `ModelInfo` records.

Each model record can contain:
- provider ID,
- model ID,
- display name,
- known input/output modalities,
- known capabilities,
- context-window metadata when the upstream API supplies reliable data,
- provider-specific opaque metadata reserved for diagnostics/configuration.

Unknown capability information stays `unknown`; adapters must not infer capabilities from model-name patterns.

### ProviderHealth
Reports normalized states such as configured, unavailable, authentication_failed, rate_limited, upstream_error, and healthy. Health checks must not expose secret material.

### ProviderError
All adapter failures map into stable internal categories:
- `configuration`,
- `authentication`,
- `authorization`,
- `rate_limit`,
- `timeout`,
- `transport`,
- `malformed_response`,
- `unsupported_capability`,
- `upstream_server`,
- `unknown`.

Raw upstream error bodies are never propagated directly to public consumers.

## Provider registry
A small registry maps stable provider IDs to available adapters and capability metadata. Initial IDs:
- `openai_direct`
- `openrouter_direct`
- `wordpress_ai_client`

The registry supports availability/configuration checks without issuing paid requests. It must not select a provider by hard-coded model-name rules.

## OpenAI Direct adapter

### HTTP boundary
Use WordPress HTTP APIs behind an internal injectable transport abstraction so normal tests can provide deterministic fake responses.

Production URLs are adapter-owned constants under `https://api.openai.com/` and are not administrator-configurable in M03. This intentionally removes arbitrary base-URL SSRF exposure.

### Generation
Use the current OpenAI Responses API for normalized M03 text generation. Request construction and response parsing remain entirely inside the adapter.

### Model discovery
Use OpenAI's model-listing endpoint and normalize returned identifiers/metadata. Domain code must not assume a fixed model list.

### Authentication
Send the resolved server-side API key only in the outbound authorization header. Keys must never be copied into returned normalized results, exceptions intended for UI display, or normal logs.

## OpenRouter Direct adapter

### HTTP boundary
Use WordPress HTTP APIs behind the same internal transport abstraction. Production URLs are fixed under `https://openrouter.ai/` and are not user-configurable in M03.

### Generation
Use OpenRouter's chat-completions-compatible API for normalized M03 text generation. Provider-specific routing fields remain adapter-local.

### Model discovery
Use OpenRouter's model endpoint and normalize capabilities/metadata only when explicitly supplied by OpenRouter.

### Authentication
Send the resolved server-side OpenRouter API key in the outbound authorization header. Optional identifying headers are deferred; M03 does not require or persist them.

## WordPress 7 AI Client adapter

### Availability
WordPress 7 is optional. Availability requires the documented public AI Client entry point `wp_ai_client_prompt()` to exist at runtime. On WordPress 6.9 or installations without that API, the adapter reports `unavailable` and never fatals.

### Credential ownership
When using the WordPress AI Client/Connectors path, WordPress Core owns provider configuration and credentials. The plugin does not copy those Core-managed secrets into its own credential store.

### Public API boundary
Only documented public Core AI APIs may be used. Private `_wp_*` APIs are forbidden.

### Application boundary
The plugin still owns feature authorization, prompts, and policy. The WordPress AI Client adapter is an infrastructure implementation, not an arbitrary prompt endpoint for browsers.

## Credentials

### Credential sources and precedence
Direct adapters resolve their API key in this order:

1. Environment variable read through `getenv()`.
2. PHP constant read only when the constant exists and contains a string.
3. Encrypted non-autoloaded WordPress option.

Initial names:
- OpenAI environment/constant: `OPENAI_API_KEY`.
- OpenRouter environment/constant: `OPENROUTER_API_KEY`.

A source counts as configured only when its value, after outer whitespace trimming, is non-empty. Blank/whitespace-only environment or constant values are skipped rather than shadowing a lower-precedence valid credential.

Plugin-managed option names:
- `wp_rag_ai_openai_api_key`
- `wp_rag_ai_openrouter_api_key`

The resolved secret is represented by a secret value object that does not expose plaintext through string conversion, JSON serialization, debug output, or ordinary exception formatting.

### Database storage
Direct-provider credentials stored in WordPress options must be encrypted at rest.

#### Key derivation
The 32-byte encryption key is derived with `hash_hkdf('sha256', ...)`.

Input key material:
`wp_salt('auth') . "\0" . wp_salt('secure_auth')`

HKDF info:
`wp-rag-ai-chatbot:credential:v1:<provider-id>`

The provider ID is part of the KDF context so ciphertext from one provider cannot be transparently reused as another provider credential.

Changing WordPress salts can make an existing encrypted plugin credential undecryptable. That condition is normalized as a configuration error and requires the administrator to re-enter the plugin-managed credential; plaintext fallback is forbidden.

#### Preferred Sodium envelope
When `sodium_crypto_aead_xchacha20poly1305_ietf_encrypt()` and its decrypt counterpart exist:
- algorithm ID: `xchacha20poly1305`,
- random nonce: `SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES`,
- AAD: `wp-rag-ai-chatbot:<provider-id>:credential:v1`,
- ciphertext: authenticated XChaCha20-Poly1305 output.

#### OpenSSL fallback envelope
When Sodium is unavailable and OpenSSL supports `aes-256-gcm`:
- algorithm ID: `aes-256-gcm`,
- random nonce: 12 bytes,
- authentication tag length: 16 bytes,
- AAD: `wp-rag-ai-chatbot:<provider-id>:credential:v1`.

#### Serialized envelope
The option stores a versioned JSON object containing only:
- `v` = `1`,
- `alg`,
- base64 nonce,
- base64 ciphertext,
- base64 authentication tag only for AES-GCM.

Malformed envelopes, unknown versions/algorithms, authentication failures, and decryption failures are configuration errors; they never fall back to treating stored bytes as plaintext.

If neither supported authenticated-encryption backend exists, writing a database-stored provider credential fails closed. The administrator must use an environment variable or PHP constant.

Credential options are created/updated with autoload disabled.

### Read/write boundary
There is no public method that returns credential plaintext for REST serialization. Internal adapters may resolve plaintext only at request execution time.

Credential replacement overwrites the encrypted payload. Empty input removes the plugin-managed credential rather than storing an encrypted empty secret.

## Secret redaction
A central redaction utility receives known secret values and sanitizes diagnostic strings/context before they enter normalized errors or logs.

At minimum it must redact:
- exact configured API keys,
- `Authorization: Bearer ...` values case-insensitively,
- `api-key` and `x-api-key` style header values case-insensitively,
- raw upstream response bodies beyond 2,048 bytes.

The replacement marker is `[REDACTED]`. Truncated bodies append `[TRUNCATED]`.

Redaction happens before exception messages are exposed outside the adapter boundary.

## HTTP policy

### Timeouts
All provider requests explicitly set WordPress HTTP API timeouts:
- generation POST: 45 seconds total,
- model-discovery GET: 10 seconds total.

No provider request inherits an unbounded timeout.

### Redirects
Credential-bearing provider requests set `redirection => 0`. M03 does not follow provider redirects, preventing authorization headers from being forwarded to another host.

### Retries
Generation POSTs have exactly one attempt. They are never automatically retried after timeout, transport error, 429, or 5xx because retries can duplicate paid generations.

Model-discovery GETs allow at most two total attempts. One retry is permitted only after:
- WordPress transport failure,
- HTTP 502,
- HTTP 503,
- HTTP 504.

Model discovery does not automatically retry 401, 403, 404, 429, other 4xx, malformed successful payloads, or other 5xx responses.

M03 does not add sleep/backoff inside the synchronous request path; job-level retry/backoff belongs to later queue orchestration.

## Model cache
Model discovery uses a WordPress transient-backed cache keyed by provider ID and catalog schema version.

Initial keys:
- `wp_rag_ai_models_openai_direct_v1`
- `wp_rag_ai_models_openrouter_direct_v1`
- `wp_rag_ai_models_wordpress_ai_client_v1` only if the WordPress adapter exposes a catalog in the implemented public API surface.

Default TTL: 900 seconds (15 minutes).

Rules:
- cache only successful normalized model lists,
- never cache secrets,
- never convert authentication failures into successful empty lists,
- provide an internal invalidation method for later admin/UI use,
- a failed forced refresh must not overwrite an existing previously valid transient value,
- malformed/upstream failures return normalized errors rather than poisoning the cache.

## Usage normalization
Normalize only usage fields explicitly returned by the provider. Missing fields remain `null` rather than fabricated as zero.

M03 may track:
- input/prompt tokens,
- output/completion tokens,
- total tokens,
- provider-specific safe usage metadata.

If a provider explicitly returns a reliable request cost, it may be retained as opaque safe usage metadata. M03 does not calculate authoritative pricing from hard-coded price tables.

## Error handling
Adapters validate response status and JSON shape before normalization.

Rules:
- 401 becomes `authentication`.
- 403 becomes `authorization`.
- 429 becomes `rate_limit`.
- timeout becomes `timeout`.
- DNS/connection/TLS/WordPress HTTP failure becomes `transport` unless WordPress explicitly identifies it as timeout.
- invalid JSON or missing required successful-response fields becomes `malformed_response`.
- 500-599 becomes `upstream_server` unless already mapped to a more specific category.
- secrets and authorization headers are redacted before any exception escapes the adapter.

Adapters preserve safe provider request IDs where available, including OpenAI request identifiers returned in response headers. Diagnostic IDs must never contain credentials.

## WordPress integration
M03 introduces no public provider REST endpoint and no provider admin UI.

A server-side configuration service exposes only non-secret state:
- provider ID,
- available/unavailable,
- configured/unconfigured,
- credential source type (`environment`, `constant`, `option`, `core`, or `none`),
- model-cache freshness metadata where useful.

It never exposes secret plaintext, encrypted credential payloads, authorization headers, KDF material, salts, or arbitrary provider request capability.

The browser never receives provider keys, encrypted credential payloads, authorization headers, or arbitrary provider request capability.

## Testing strategy

### Normal CI
Normal CI consumes no paid provider credits and needs no real API keys.

Use deterministic HTTP fakes/fixtures to test:
- request URL/method/headers/body,
- OpenAI Responses normalization,
- OpenRouter chat-completions normalization,
- 401/403/429/5xx handling,
- timeouts/WordPress HTTP errors,
- malformed JSON and missing fields,
- request-ID capture,
- secret redaction and 2,048-byte body truncation,
- model discovery normalization,
- model-cache hit/miss/invalidation and failed-refresh preservation,
- exactly one discovery retry only for transport/502/503/504,
- exactly one generation attempt,
- credential precedence including blank-source skipping,
- Sodium envelope round-trip when available,
- AES-GCM fallback round-trip through an injectable crypto capability boundary,
- malformed/decryption-failure behavior,
- credential deletion,
- fail-closed crypto absence,
- non-autoloaded option storage,
- WordPress AI Client `wp_ai_client_prompt()` availability detection/degradation.

### Real WordPress integration
Extend the permanent WordPress smoke layer to verify plugin-managed credential option persistence/encryption metadata, autoload behavior, deletion, precedence against controlled constants/environment state where feasible, and WordPress AI Client feature detection without making external provider calls.

No real API key is written into repository fixtures or CI logs.

### Optional live smoke
Provide explicit opt-in live smoke commands. They run only when:
1. `WP_RAG_AI_LIVE_PROVIDER_TESTS=1`, and
2. the corresponding provider environment credential is non-empty.

They are excluded from normal CI.

Live smoke uses one bounded minimal generation plus model discovery per explicitly selected provider and never prints the credential or authorization headers.

## Security requirements
- No provider credential in browser responses, HTML, JavaScript bundles, localStorage, public REST payloads, normal logs, or thrown user-facing messages.
- No arbitrary base URLs in M03.
- No browser-to-provider API calls.
- No automatic paid generation retries.
- Provider redirects are disabled for credential-bearing requests.
- Credential database storage is authenticated encryption or unavailable.
- Plugin-managed credential options are non-autoloaded.
- Core-managed WordPress AI Client credentials are not duplicated into plugin storage.
- All external response content is untrusted data and validated before normalization.

## Performance requirements
- Model discovery cache TTL is 900 seconds.
- Provider discovery is not performed on every normal request while a valid cache exists.
- Generation timeout is 45 seconds; discovery timeout is 10 seconds.
- Discovery has at most two total attempts; generation has exactly one.
- Model lists are normalized into bounded in-memory result sets and one transient per provider/catalog version; no application-scale model history is stored.

## Compatibility requirements
- WordPress 6.9+ remains supported through direct adapters.
- PHP 8.2+ remains the floor.
- WordPress 7 AI Client usage is optional and feature-detected through the documented public API.
- Absence of WordPress AI Client APIs must not affect OpenAI/OpenRouter direct operation.

## Milestone exit criteria
M03 is complete only when:
- OpenAI/OpenRouter mock contract tests are RED→GREEN verified.
- Credential precedence/encryption/redaction tests are RED→GREEN verified.
- WordPress 7 adapter availability/degradation tests pass while WP 6.9 remains supported.
- Model discovery/cache tests pass.
- Normalized error/usage tests pass.
- Real WordPress configuration integration passes without external provider calls.
- Optional live smoke commands exist but are not part of normal CI.
- Security/performance review finds no unresolved Critical/Important issue.
- Exact candidate CI, package verification, documentation ledgers, and branch integration workflow are complete.

## Deferred decisions
- Exact streaming event normalization is owned by the streaming/chat milestone.
- Tool-calling execution is owned by later actions/RAG milestones.
- Embedding runtime and compatibility enforcement are owned by M08.
- Provider admin UX is owned by M12.
- Pricing/cost analytics is owned by analytics/evals milestones.
