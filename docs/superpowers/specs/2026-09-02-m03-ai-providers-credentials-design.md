# M03 — AI Providers, Credentials & Compatibility Design

Status: APPROVED IN CHAT; WRITTEN SPEC FOR REVIEW

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
All adapter failures map into stable internal categories such as:
- configuration,
- authentication,
- authorization,
- rate_limit,
- timeout,
- transport,
- malformed_response,
- unsupported_capability,
- upstream_server,
- unknown.

Raw upstream error bodies are never propagated directly to public consumers.

## Provider registry
A small registry maps stable provider IDs to available adapters and capability metadata. Initial IDs:
- `openai_direct`
- `openrouter_direct`
- `wordpress_ai_client`

The registry must support availability checks without constructing paid requests. It must not select a provider by hard-coded model-name rules.

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
Send the resolved server-side OpenRouter API key in the outbound authorization header. Optional safe identifying headers may be supported later through configuration, but they are not required for M03 acceptance.

## WordPress 7 AI Client adapter

### Availability
WordPress 7 is optional. The adapter performs runtime feature detection of the public WordPress AI Client API before use. On WordPress 6.9 or installations without the required Core AI capability, it reports `unavailable` cleanly and never fatals.

### Credential ownership
When using the WordPress AI Client/Connectors path, WordPress Core owns provider configuration and credentials. The plugin does not copy those Core-managed secrets into its own credential store.

### Public API boundary
Only documented public Core AI APIs may be used. Private `_wp_*` APIs are forbidden.

### Application boundary
The plugin still owns feature authorization, prompts, and policy. The WordPress AI Client adapter is an infrastructure implementation, not an arbitrary prompt endpoint for browsers.

## Credentials

### Credential sources and precedence
Direct adapters resolve their API key in this order:

1. Environment variable.
2. PHP constant.
3. Encrypted non-autoloaded WordPress option.

Initial names:
- OpenAI environment/constant: `OPENAI_API_KEY`.
- OpenRouter environment/constant: `OPENROUTER_API_KEY`.

A higher-precedence configured source wins. The resolved secret is represented by a secret value object that does not reveal its plaintext from string conversion or debug serialization.

### Database storage
Direct-provider credentials stored in WordPress options must be encrypted at rest.

Preferred crypto backend:
1. Sodium authenticated encryption when available.
2. AES-256-GCM via OpenSSL when Sodium is unavailable.

Key material is derived from WordPress installation salts through a deterministic KDF context specific to this plugin and credential purpose. The encryption format is versioned so future rotation/migration can be implemented safely.

If neither Sodium nor a secure OpenSSL AES-GCM implementation is available, writing a database-stored provider credential fails closed. The administrator must then use an environment variable or PHP constant.

Credential options use `autoload = no`.

### Read/write boundary
There is no public method that returns credential plaintext for REST serialization. Internal adapters may resolve plaintext only at request execution time.

Credential replacement overwrites the encrypted payload. Empty input removes the plugin-managed credential rather than storing an encrypted empty secret.

## Secret redaction
A central redaction utility receives known secret values and sanitizes diagnostic strings/context before they enter normalized errors or logs.

At minimum it must redact:
- exact configured API keys,
- bearer authorization values,
- obvious key-bearing request headers,
- excessively long raw upstream bodies.

Redaction happens before exception messages are exposed outside the adapter boundary.

## HTTP policy

### Timeouts
Provider generation and model discovery use explicit finite connect/request timeouts. Exact values are implementation constants/configuration owned by the HTTP transport and covered by tests; no request may inherit an unbounded timeout.

### Retries
Generation POSTs are not automatically retried after ambiguous transport/upstream failures because retries could duplicate paid generations.

Safe idempotent model-discovery GET requests may use a small bounded retry count for transient network/5xx failures.

429 responses are normalized as rate-limit errors; M03 does not build a job-level retry scheduler.

### Redirects and endpoints
Adapters call only their fixed HTTPS hosts. Redirect behavior must remain bounded and must not permit arbitrary host pivoting for credential-bearing requests.

## Model cache
Model discovery uses a WordPress transient-backed cache keyed by provider and adapter schema version.

Default TTL: 15 minutes.

Rules:
- cache only successful normalized model lists,
- never cache secrets,
- never cache authentication failures as successful empty lists,
- provide an internal invalidation method for later admin/UI use,
- malformed/upstream failures return normalized errors without poisoning a previously valid cached result.

## Usage normalization
Normalize only usage fields explicitly returned by the provider. Missing fields remain unknown/null rather than fabricated as zero.

M03 may track:
- input/prompt tokens,
- output/completion tokens,
- total tokens,
- provider-specific safe usage metadata.

Cost estimation is not authoritative in M03 unless the provider explicitly returns a reliable cost value. Future analytics may calculate configured estimates separately.

## Error handling
Adapters validate response status and JSON shape before normalization.

Rules:
- 401/403 become authentication/authorization failures.
- 429 becomes rate_limit.
- timeout becomes timeout.
- DNS/connection/TLS/WordPress HTTP failure becomes transport.
- invalid/missing expected JSON fields become malformed_response.
- 5xx becomes upstream_server.
- secrets and authorization headers are redacted before any exception escapes the adapter.

Adapters must preserve safe provider request IDs where useful for support diagnostics.

## WordPress integration
M03 introduces no public provider REST endpoint and no provider admin UI.

A small configuration service may expose server-side state such as:
- provider available,
- provider configured,
- credential source type,
- model cache state,
without exposing secret values.

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
- secret redaction,
- model discovery normalization,
- model-cache hit/miss/invalidation,
- retry behavior for discovery only,
- no automatic generation retry,
- credential precedence,
- encrypted option round-trip,
- credential deletion,
- fail-closed crypto absence,
- non-autoloaded option storage,
- WordPress AI Client availability detection/degradation.

### Real WordPress integration
Extend the permanent WordPress smoke layer to verify configuration persistence and feature detection in a real WordPress environment without making external provider calls.

### Optional live smoke
Provide explicit opt-in live smoke commands. They run only when the required environment credential is present and an explicit live-test flag is set. They are excluded from normal CI.

Live smoke must use low-cost bounded requests and never print the credential.

## Security requirements
- No provider credential in browser responses, HTML, JavaScript bundles, localStorage, public REST payloads, normal logs, or thrown user-facing messages.
- No arbitrary base URLs in M03.
- No browser-to-provider API calls.
- No automatic paid generation retries.
- Credential database storage is authenticated encryption or unavailable.
- Plugin-managed credential options are non-autoloaded.
- Core-managed WordPress AI Client credentials are not duplicated into plugin storage.
- All external response content is untrusted data and validated before normalization.

## Performance requirements
- Model discovery cache TTL defaults to 15 minutes.
- Provider discovery is bounded and never performed on every normal request when a valid cache exists.
- Request timeouts are explicit and finite.
- Model lists are bounded by the upstream response and normalized without application-scale option blobs.

## Compatibility requirements
- WordPress 6.9+ remains supported through direct adapters.
- PHP 8.2+ remains the floor.
- WordPress 7 AI Client usage is optional and feature-detected.
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
