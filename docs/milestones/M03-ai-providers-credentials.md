# M03 — AI Providers, Credentials, OpenAI, OpenRouter & WP AI Client Compatibility

Status: COMPLETE — integrated into `main`; post-merge permanent CI verified green.

## Goal
Create provider/capability contracts and secure OpenAI/OpenRouter direct adapters plus optional WordPress 7 AI Client compatibility without introducing later RAG, embeddings, admin UI, tools, or streaming scope.

## Dependencies
M01-M02.

## Authoritative Plan
`docs/superpowers/plans/2026-09-02-m03-ai-providers-credentials-v2.md`

## Delivered Scope
- Stable provider contracts/value objects for generation, health, errors, usage, descriptors, and model catalogs.
- Direct OpenAI and OpenRouter adapters behind a provider registry; model IDs stay configuration/discovery data rather than domain constants.
- Server-only credential precedence: environment -> PHP constant -> plugin-managed encrypted WordPress option, with blank runtime values falling through.
- Authenticated credential storage using Sodium XChaCha20-Poly1305 when available and AES-256-GCM fallback, provider-bound HKDF/AAD, strict envelopes, non-autoloaded options, and fail-closed decryption.
- Secret value object and diagnostic redaction boundary; provider errors are normalized before leaving adapters.
- Fixed HTTPS provider endpoints, zero redirects, exact 45-second generation and 10-second discovery timeouts, no generation retry, and one bounded discovery retry only for transport failures or 502/503/504.
- Fifteen-minute normalized model-catalog transient cache with malformed-cache eviction and failed-refresh preservation.
- OpenAI Responses API generation and model discovery.
- OpenRouter chat-completions generation and model discovery.
- Optional WordPress 7 AI Client adapter using public APIs only, with graceful WordPress 6.9 degradation.
- Non-secret provider configuration descriptors and bootstrap/registry composition without startup network calls.
- Permanent real-WordPress provider integration smoke and opt-in live-provider smoke gating.
- Production ZIP guard requiring every runtime `src/Providers/**/*.php` file while excluding tests/docs/scripts/private/development files.

## Out of Scope Preserved
No embeddings/vector runtime (M08), production RAG orchestration/streaming (M11), provider admin UI (M12), pricing tables, arbitrary provider base URLs, public prompt endpoints, tools/actions, or later knowledge/indexing implementation was added.

## TDD / RED→GREEN Evidence
- Provider infrastructure RED `16d1282029806f74b9feeed3a3e0510a15b23046`, run `33626731444`: PHPStan clean; PHPUnit `129 tests / 683 assertions / 6 failures`, all expected missing registry/config/bootstrap behavior. GREEN culminated at `410cdf93de60f266943bbf0105e3c482f79a0e87`, run `33627365943`: PHPStan clean and `129 tests / 729 assertions` green.
- Real WordPress provider smoke/live-gating integration was permanently wired by `a42c94963c202f55510d815c5ea1c0aa59cf7243`; run `33627872789` passed all four jobs, including activation, M02 DB smoke, M03 provider smoke, and offline live-gating checks.
- Independent security review found boundary-crossing credential fragments could survive diagnostic truncation. RED `35e65d2855a46d7a9d4580fcaae3f175afd91902`, run `33635147048`: PHPStan clean; PHPUnit `130 tests / 731 assertions / 1 failure`, exposing `boundary[TRUNCATED]`. GREEN `00a5864d86379ed653889b405a28960991476bda` redacts before truncation and preserves complete redaction markers.
- Independent packaging review found `assert-package.sh` accepted an archive missing all provider runtime files. Run `33635292449` failed only the package-assertion regression with `Package assertion accepted an archive missing provider runtime files.` GREEN `1f716e91ef473aa29a1486e51f70e4c11cfb8209`, run `33635472457`, passed PHP, JS including package regression, real WordPress smoke, and package jobs.
- Independent Core-AI error-boundary review found arbitrary unexpected Throwable messages were republished although Core-managed credentials are opaque to the plugin. RED `fe28e7c134700fafcd01f7ad36e5fb152500eb20`, run `33636065968`: PHPStan clean; PHPUnit `131 tests / 737 assertions / 1 failure`, expected constant safe message vs actual `opaque-core-credential-should-never-escape`. GREEN `11c660db87bd10343aea9e8f4d93fa33fb53e2e2`, run `33636226873`: PHPStan clean and `131 tests / 738 assertions` green.
- Secret export/native-serialization review found `Secret` plaintext could be exposed through ordinary PHP export/serialization surfaces. RED `5e721174530e493ce8274eea2567a25446c7361c`, run `33638078588`: WPCS/PHPStan clean; PHPUnit `132 tests / 742 assertions / 1 failure`, with `var_export()` containing `sk-test-export-super-secret`. GREEN `e5ab99f54baf734597c78e6a3ff5b85a1d3d4e2f`, run `33638196004`, protected the plaintext with `SensitiveParameterValue` and passed all permanent jobs.
- Provider diagnostic request-ID review found provider-controlled request IDs could carry configured credential material. RED `4581b26297b3cc98b6adb0bf9f12b989a1dc8d47`, run `33639434957`: WPCS/PHPStan clean; PHPUnit `134 tests / 745 assertions / 2 failures`, one OpenAI and one OpenRouter. Partial GREEN `266b7b40de435a7d563ff5e2ffc1bff6744bb9a6` left only the OpenRouter case failing. Final GREEN `c8cddc7c8d4905d1436f95eeb8ef77c2f075c8af`, run `33639805500`, rejected secret-bearing IDs and passed all four permanent jobs with PHPUnit `134 tests / 747 assertions`.

## Real WordPress Integration Evidence
Permanent `wordpress-smoke` on WordPress 6.9/PHP 8.2 verifies:
- clean plugin activation plus the complete M02 migration/repository/uninstall smoke;
- provider bootstrap composition without provider HTTP calls;
- deterministic fake OpenAI credential encrypted in `wp_options` with no plaintext at rest;
- versioned approved authenticated-encryption envelope and strict base64 fields;
- credential option autoload disabled;
- plaintext only observed through the explicit `Secret::with_value()` callback boundary;
- deletion removes the managed credential option;
- environment precedence and blank runtime fallback where controllable;
- WordPress AI feature-detection state matches the runtime without generation;
- serialized provider descriptors contain no secret/ciphertext/KDF/header material.

Normal CI never enables the live-provider script. The live wrapper exits successfully unless `WP_RAG_AI_LIVE_PROVIDER_TESTS=1`; an explicit direct provider and corresponding environment credential are then required, discovery may run, and generation is allowed at most once only when an explicit live model variable is provided.

## Security Review
Final review covered credential leakage/storage/precedence, cryptographic envelope/backend selection, endpoint/redirect/timeout/retry policy, normalized errors and diagnostics, provider-controlled request IDs, cache failure behavior, WordPress AI compatibility, CI paid-call isolation, packaging, and milestone scope.

Findings fixed before completion:
1. **Important:** truncating a provider body before known-secret redaction could leak a credential prefix when the secret crossed the 2048-byte boundary. Fixed and regression-tested.
2. **Important:** package validation did not require M03 provider runtime files and did not forbid development `scripts/`. Fixed by requiring every runtime provider PHP source path and rejecting scripts.
3. **Important:** unexpected WordPress AI Client Throwables could republish opaque Core/provider text that the plugin cannot reliably redact. Fixed with a constant fail-closed error message; structured `WP_Error` handling remains sanitized.
4. **Important:** the `Secret` object's internal plaintext string could appear through PHP export/native serialization surfaces even though string/JSON/debug methods redacted. Fixed by storing plaintext in `SensitiveParameterValue`; regression covers `var_export()` and native `serialize()` behavior.
5. **Important:** OpenAI/OpenRouter provider-controlled request IDs could include known credential material and escape as diagnostic metadata. Fixed by accepting an ID only when secret sanitization leaves it unchanged; regression covers both adapters.

Unresolved Critical/Important findings: **none**.

Additional verified controls:
- Sodium XChaCha20-Poly1305 preferred; AES-256-GCM only when Sodium is unavailable.
- Provider-bound HKDF-SHA256 key derivation and AAD; strict version/algorithm/envelope shapes; malformed/unsupported decryption fails closed.
- Managed credential options are non-autoloaded and plaintext is not exposed through `Secret` string/JSON/debug/export/native-serialization surfaces.
- Direct provider URLs are compile-time fixed HTTPS constants; arbitrary base URLs are not accepted.
- Generation uses one request only; discovery retries once only for transport failure/502/503/504.
- 401/403/429/upstream/malformed responses remain errors rather than empty-success model catalogs.
- Failed model refresh cannot replace a valid cache entry because cache write occurs only after successful upstream normalization.
- Capability metadata is read only from explicit provider payload fields; model names are not parsed heuristically.
- WordPress AI adapter uses documented public entrypoints/builder/result methods and reports unavailable cleanly on the supported pre-WP7 baseline.

## Performance Review
- Provider bootstrap composes local objects only and performs no startup model discovery or paid generation.
- Direct generation/discovery requests are bounded by 45s/10s timeouts and zero redirects.
- Discovery retry budget is at most one additional request; generation never retries.
- Model catalogs use a fixed 900-second transient TTL and normalized cache payloads.
- Configuration descriptors are local-only and do not trigger discovery/generation.
- No polling workers, streaming runtime, vector computation, or other later high-cost paths were introduced.

## Final Verification and Integration
- Security-hardened runtime candidate: `c8cddc7c8d4905d1436f95eeb8ef77c2f075c8af`; run `33639805500` passed all four permanent jobs; PHPUnit `134 tests / 747 assertions`; Composer audit clean.
- Documentation-complete integration head: `da620a89d420bf22a7dc146b2cab84113f376fcf`; push run `33670130318` passed `php-quality`, `js-quality`, `wordpress-smoke`, and `package`.
- Exact-head artifact: `wp-rag-ai-chatbot`, ID `9862171632`, 64,820 bytes, digest `sha256:33c99a976e89c71b74c2c44c4eecfd60954dfeba9da30f3ca58758e1cc34a533`.
- PR #2 `fix: harden M03 provider secret boundaries` merged as `2ed420a9217422f856afaf64b68fdde78ea0b063`.
- Post-merge `main` run `33670406871` passed all four permanent jobs, including activation + M02 database runtime + M03 provider integration smoke.
- Post-merge artifact: `wp-rag-ai-chatbot`, ID `9862272933`, 64,804 bytes, digest `sha256:e44bd8abbc96c1577c66ff42b4d3ba6507bb37b067f71e0b2c05d6d69ca4782b`.
- npm blocking gate: zero critical advisories; existing non-critical development-tooling advisories remain tracked and are not shipped in the production ZIP.

## Files Changed
M03 changes are confined to provider contracts/adapters/credentials/security/cache/HTTP/bootstrap, provider-focused tests/test doubles, real-WordPress/live-gating scripts, package/CI guards, and M03 design/plan/evidence documentation. No M04+ product runtime was implemented.

## Known Limitations
- Live OpenAI/OpenRouter discovery/generation is intentionally credential-gated and is not executed in normal CI; no production credentials were supplied during M03 completion.
- WordPress AI Client is optional: WordPress 6.9 remains supported and reports the adapter unavailable when the WP7 public AI API is absent.
- Provider model/capability metadata may evolve upstream; M03 intentionally avoids hard-coded model-name heuristics and relies on discovery/explicit metadata.
- Existing non-critical WordPress JavaScript development-tooling advisories remain tracked and are excluded from the production ZIP.
- Native local git worktree/dependency execution remains unavailable in this chat runtime because external DNS is restricted; connected GitHub branch isolation + GitHub Actions remain the verified execution path under ADR-016/018.

## Documentation Updated
`docs/DECISIONS.md`, M03 milestone, global STATUS, TEST-MATRIX, SECURITY, KNOWN-ISSUES, and TECH-DEBT.

## Completion Checklist
- [x] Provider contracts, registry, descriptors, and bootstrap implemented.
- [x] OpenAI/OpenRouter direct generation and model discovery implemented.
- [x] Credential precedence, encrypted storage, Secret boundary, and redaction implemented.
- [x] Fixed HTTP safety/retry policy and model catalog caching implemented.
- [x] Optional public WordPress AI Client adapter implemented with WP6.9 degradation.
- [x] Permanent real WordPress provider integration and offline live-gating tests implemented.
- [x] Production ZIP provider-runtime completeness regression implemented.
- [x] Security/performance review complete.
- [x] All Critical/Important review findings fixed with focused RED→GREEN evidence.
- [x] Documentation-complete exact SHA passed all permanent CI jobs.
- [x] PR merged into `main` and post-merge permanent CI passed.

## Next Milestone
M04 — WordPress Knowledge Source Framework. A fresh scheduled run must recover current GitHub state and begin the first genuinely unfinished M04 task; do not redo M03 without evidence of a defect.
