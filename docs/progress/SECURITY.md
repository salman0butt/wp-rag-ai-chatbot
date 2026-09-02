# Security Ledger

Security is cross-cutting; M22 is the comprehensive hardening audit, not the first security milestone.

## Threat boundaries to test throughout

- Anonymous visitor -> public REST/chat.
- Authenticated visitor/customer -> conversation/order/action APIs.
- Administrator -> admin REST/configuration.
- External URL/sitemap -> crawler/extractor.
- Uploaded document -> parser/indexer.
- Retrieved content -> model context.
- LLM tool request -> action execution.
- WordPress -> OpenAI/OpenRouter/WP AI Client.
- WordPress -> external vector store.
- External website -> cross-site embed.
- MCP client -> WordPress Ability/action.

## Mandatory controls

- Capability and ownership checks.
- REST permission callbacks.
- Nonces where appropriate for cookie-authenticated mutations.
- Strict validation, sanitization, output escaping.
- Prepared SQL.
- SSRF-safe URL fetching and redirect policy.
- File MIME/size/resource controls.
- Secret redaction and no credential exposure to clients/logs.
- Rate/cost/spam controls.
- Prompt/retrieval/tool-injection defenses.
- IDOR prevention for conversations/leads/orders.
- WooCommerce order/customer authorization.
- Explicit CORS/origin policy for external embeds.
- Action schema validation, risk classification, authorization, timeout, audit.

## M01 foundation security evidence

- Runtime surface intentionally introduced no REST endpoints, external HTTP calls, credentials, uploads, or user-input processing.
- Permanent GitHub Actions workflow uses read-only repository contents permission.
- Composer audit reported no known advisories on the verified foundation candidate.
- npm development tooling has tracked non-critical transitive advisories; Node dependencies are development/build only and are excluded from the production ZIP.
- Production packaging is allow-list based and rejects development/private paths.

## M02 database security evidence

Verified runtime candidate: `4db24d95db0d572f28273734714c74a47ac8bb2e`, CI run `33603435032`.

### SQL/value boundaries
- Source/document value-bearing reads use prepared `%s`/`%d` placeholders; plugin-owned table identifiers use `%i` from trusted `TableNames` only.
- Inserts, updates, and deletes use the narrow `$wpdb` adapter write APIs with explicit formats.
- Real WordPress tests store and retrieve SQL-injection-shaped literals including `source-' OR 1=1 --` and `" OR 1=1 --`; row counts and source scoping remain unchanged.
- Apostrophes, `<script>literal test data</script>`, and Unicode `مرحبا` are treated as stored data, not executable SQL or output policy.
- Repository pagination is bounded to at most 100 records per page, limiting accidental/untrusted unbounded list requests at the persistence layer.

### Migration boundary
- Migrations run under a MySQL named advisory lock.
- Lock contention does not execute DDL.
- The schema version is refreshed after successful lock acquisition, closing a race where a stale process could replay migrations completed by another process.
- Version persistence occurs after successful migration application; failed migrations retain the last successful version and release the lock in `finally`.
- The current-schema `plugins_loaded` path exits after the lightweight version read rather than acquiring a lock or executing DDL.

### Uninstall/destructive boundary
- Data retention is the default; destructive uninstall requires the explicit persisted true/one setting.
- `uninstall.php` is guarded by `WP_UNINSTALL_PLUGIN` and loads only the packaged Composer runtime.
- Plugin-owned tables are dropped in safe dependency order and only through prepared `%i` identifiers.
- A failed DROP throws `DatabaseException`; schema/policy options are not removed, preserving a retryable cleanup state.
- Version/delete-policy options are removed only after all plugin-owned table drops succeed.
- Real WordPress integration verifies default retention, opt-in deletion, option removal, and a clean reinstall.

### Scope/secrets
- M02 introduces no provider credentials, API keys, external model/vector calls, public REST endpoints, or new client-visible secrets.
- Package validation requires the uninstall runtime while continuing to exclude tests, docs, `.github`, environment files, Node modules, and dependency manifests/locks.

Artifact digest: `sha256:f77b32bf377b4f6fbb65cf1721a87b5e0408041ad5444816076227ab931aeab3`.

## M03 provider security evidence

Integrated `main` commit: `2ed420a9217422f856afaf64b68fdde78ea0b063`; post-merge CI run `33670406871` passed all four permanent jobs. Artifact `wp-rag-ai-chatbot` ID `9862272933`, digest `sha256:e44bd8abbc96c1577c66ff42b4d3ba6507bb37b067f71e0b2c05d6d69ca4782b`.

### Credential trust boundary
- Direct-provider credentials resolve in explicit precedence order: environment -> PHP constant -> plugin-managed option. Runtime values are trimmed and blank values fall through instead of masking safer lower-priority configuration.
- Plugin-managed credentials are stored only as authenticated ciphertext in non-autoloaded options.
- Sodium XChaCha20-Poly1305 is preferred; AES-256-GCM is the approved fallback when Sodium is unavailable.
- Key derivation uses HKDF-SHA256 over WordPress auth salts with provider-specific context; authenticated additional data is also provider-bound.
- Envelope version/algorithm/field shapes and base64 payloads are strictly validated. Unsupported algorithms, malformed/tampered envelopes, wrong-provider decryption, or missing approved crypto backends fail closed with generic configuration errors.
- `Secret` does not reveal plaintext through string conversion, JSON serialization, debug info, PHP export, or native serialization; plaintext is exposed only to an explicit callback consumer. The export/native-serialization regression is permanently covered.
- Real WordPress integration verifies fake credential plaintext is absent from the raw `wp_options` value and autoload is disabled.

### Provider network boundary
- OpenAI/OpenRouter endpoints are fixed HTTPS constants; configuration cannot supply arbitrary base URLs.
- All provider requests set `redirection=0`.
- Generation timeout is exactly 45 seconds and generation never retries, preventing accidental duplicate paid operations.
- Discovery timeout is exactly 10 seconds and has at most one retry, only after transport failure or 502/503/504.
- Authentication/authorization/rate-limit/other upstream statuses are normalized errors rather than empty-success catalogs.
- WordPress transport exceptions expose stable categories and constant messages rather than raw request headers/transport diagnostics.

### Diagnostic/error boundary
- Known direct-provider plaintext secrets and Authorization/api-key/x-api-key values are redacted before diagnostics leave adapters.
- Provider bodies are bounded to 2048 bytes for diagnostics.
- Review found truncation-before-redaction could expose a prefix when a secret crossed the byte boundary. RED `35e65d2855a46d7a9d4580fcaae3f175afd91902` / run `33635147048` proved the leak. Production now redacts the complete body before truncation and preserves a whole `[REDACTED]` marker at boundary cuts.
- Review found unexpected WordPress AI Client Throwables could contain opaque Core/provider details the plugin cannot know as redaction inputs. RED `fe28e7c134700fafcd01f7ad36e5fb152500eb20` / run `33636065968` proved the opaque message escaped. Unexpected Throwables now fail closed with `WordPress AI Client request failed.` Structured `WP_Error` data remains sanitized through the public error boundary.
- Review found provider-controlled OpenAI/OpenRouter request IDs could include configured plaintext credentials. RED `4581b26297b3cc98b6adb0bf9f12b989a1dc8d47` / run `33639434957` failed both adapters. OpenAI was fixed at `266b7b40de435a7d563ff5e2ffc1bff6744bb9a6`; final OpenRouter GREEN `c8cddc7c8d4905d1436f95eeb8ef77c2f075c8af` / run `33639805500` rejects any request-ID candidate changed by secret sanitization.
- Provider descriptors are non-secret DTOs and real WordPress smoke verifies serialized descriptors omit secrets, ciphertext, KDF data, and authentication headers.

### Secret object export boundary
- Review found the internal plaintext string in `Secret` could be visible through `var_export()` / native serialization despite safe string/JSON/debug methods.
- RED `5e721174530e493ce8274eea2567a25446c7361c` / run `33638078588` reached PHPUnit and failed because `var_export()` contained `sk-test-export-super-secret`.
- GREEN `e5ab99f54baf734597c78e6a3ff5b85a1d3d4e2f` / run `33638196004` stores plaintext inside PHP 8.2 `SensitiveParameterValue`; all permanent jobs passed. Tests assert plaintext is absent from `var_export()`, native serialization output when available, and thrown serialization messages.

### Model/catalog boundary
- Model capability metadata is populated only from explicit provider metadata; model IDs/names are not parsed as security/capability heuristics.
- Catalog cache stores normalized model data only, never credentials or successful representations of provider errors.
- Malformed cache data is evicted.
- Refresh writes only after successful upstream normalization, so a failed refresh preserves a valid prior cache.
- Cache TTL is fixed at 900 seconds to bound metadata drift without request-per-view behavior.

### WordPress AI compatibility boundary
- The optional adapter uses public WordPress AI APIs only and performs feature detection before generation.
- WordPress 6.9 remains a supported baseline: absence of the WP7 API yields an unavailable provider rather than a fatal error.
- Provider bootstrap/configuration introspection makes no paid generation/discovery call.
- Normal CI never enables the opt-in live-provider script, so no production credential or paid provider call is required for the permanent test suite.

### Production package boundary
- Review found package validation accepted archives with M03 runtime provider files removed. The guard now derives every `src/Providers/**/*.php` runtime source path and requires it in the ZIP.
- Development `scripts/`, tests, docs, `.github`, environment files, Node modules, and dependency manifests/locks remain forbidden in the production ZIP.
- The package-assertion regression is permanent in CI.

### Final review and integration result
Five Important M03 findings were fixed with focused regressions. Unresolved Critical/Important findings: **none**.

- Security-hardened runtime candidate `c8cddc7c8d4905d1436f95eeb8ef77c2f075c8af`, run `33639805500`: all permanent jobs green; PHPUnit `134 tests / 747 assertions`; Composer audit clean.
- Documentation-complete integration head `da620a89d420bf22a7dc146b2cab84113f376fcf`, run `33670130318`: all permanent jobs green.
- PR #2 merged as `2ed420a9217422f856afaf64b68fdde78ea0b063`; post-merge run `33670406871`: `php-quality`, `js-quality`, `wordpress-smoke`, and `package` all green.

## M04 knowledge-source security evidence

Pre-integration runtime/smoke candidate: `aa246186a218efa7208403c36fecd051c6c143ee`, CI `33687296386`; all four permanent jobs passed, including the new real-WordPress knowledge smoke. Artifact `wp-rag-ai-chatbot` ID `9868623773`, 75,709 bytes, digest `sha256:41f285035d298187635bfbfd9d9f8aff828003439384979503ba37e84b8b3fbf`.

### Content/access boundary
- M04 adds no public REST endpoint, upload parser, external crawler, provider call, model invocation, vector operation, or new credential handling.
- Native WordPress enumeration is limited to public post types. Default normalization accepts published content only; private content requires explicit `include_private=true` and remains marked `private`.
- Draft, pending, trash, and password-protected content is not emitted. The real WordPress smoke verifies draft/password exclusion and private default exclusion/explicit opt-in.
- WordPress title, excerpt, and content markup is converted to text with `wp_strip_all_tags()` at the native adapter boundary before canonical document normalization.
- Arbitrary post meta is never read. Only explicit post identity/status/author/permalink/text and selected taxonomy labels cross the gateway.
- Canonical URL comes from WordPress `get_permalink()` and is traceable to the source post ID.

### Determinism/integrity boundary
- `DocumentHasher` recursively canonicalizes associative-key order while preserving list order and uses SHA-256 over a throwing JSON encoding path.
- WordPress document hashes include key, external ID, title, canonical URL, normalized content, taxonomy/access metadata, source version, language, and visibility.
- FAQ items are fully validated before the first document is yielded, preventing partial ingestion when a later item is malformed. This was an Important review finding fixed through dedicated RED/GREEN regression evidence.
- `KnowledgeBootstrap` validates the extension filter return shape and every extension implementation before publishing the composed registry, so invalid extensions fail closed without exposing partial registry state.

### Real WordPress lifecycle evidence
- `npm run test:wp:knowledge` creates real published page/post, private, draft, and password-protected fixtures.
- It verifies published normalization, canonical URL, sanitized text, stable document key/hash across repeated reads, private opt-in behavior, draft/password exclusion, and fixture cleanup.
- Task 7 exposed no production defect; therefore no Task 7 production regression cycle was required by the approved plan.

### Final M04 review result
- Critical findings: none.
- Important findings fixed: FAQ partial-yield integrity and native WordPress markup crossing the adapter boundary.
- Unresolved Critical/Important findings: **none**.
- Minor non-blocking considerations: not every defensive invalid-config guard has a separate focused test, and taxonomy lookup remains per post. Runtime enumeration is bounded to 100 records per page, stable-ID ordered, `no_found_rows`, and performs no remote I/O; batching taxonomy reads is deferred until evidence justifies added complexity.
- M04 introduces no UI, so accessibility review is N/A.

## Open security design work

Rate/cost controls for public chat/provider invocation, admin credential mutation permissions/nonces/REST shape, crawler SSRF allow/deny policy, cross-site embed session/origin policy, prompt/retrieval/tool-injection defenses, action authorization, privacy/retention, and later abuse controls remain owned by M12/M15/M19/M22 and related milestones. M03 establishes the server-side provider/credential boundary; M04 establishes deterministic knowledge-source/access boundaries without exposing public prompt or remote-crawl surfaces.
