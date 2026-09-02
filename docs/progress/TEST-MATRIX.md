# Test Matrix

Status: ACTIVE — exact milestone evidence is recorded in each milestone ledger and permanent CI where applicable.

| Layer | Purpose | First Required | Evidence location |
|---|---|---|---|
| PHP unit | isolated PHP/plugin behavior | M01 | M01-M03 + CI `php-quality` |
| WordPress integration | hooks/plugin lifecycle/database/provider runtime | M01 | M01-M03 + CI `wordpress-smoke` |
| JS/TS unit | build-tooling/component behavior | M01 | M01 + CI `js-quality` |
| Typecheck/lint/build | frontend/toolchain production integrity | M01 foundation; M12+ UI | M01 + relevant UI milestone |
| Dependency audit | known Composer/npm advisories | M01 | CI PHP/JS quality jobs |
| Release ZIP guard | required runtime files; exclude dev/private files | M01 foundation; M24 release | M01-M03 + CI `package`/package assertion; M24 final audit |
| DB migrations | fresh install + upgrades + idempotency + locking/failure recovery | M02 | M02 + CI `php-quality`/`wordpress-smoke` |
| Repository/SQL | prepared queries, pagination, source ownership/isolation | M02 | M02 + CI `wordpress-smoke` |
| Uninstall/data policy | retain default, explicit deletion, failure retryability | M02 | M02 + CI `php-quality`/`wordpress-smoke` |
| Provider contract | normalized generation/errors/capabilities | M03 | M03 + CI `php-quality` |
| Credential security | precedence/encryption/non-autoload/redaction/fail-closed errors | M03 | M03 + CI `php-quality`/`wordpress-smoke` |
| Provider HTTP policy | fixed endpoints/timeouts/redirects/retry classification | M03 | M03 + CI `php-quality` |
| Provider cache | bounded catalog TTL, malformed eviction, refresh preservation | M03 | M03 + CI `php-quality` |
| Live provider smoke | opt-in credential-gated path | M03 | M03 live-gating regression; actual live calls opt-in only |
| Knowledge source contract | normalization and access metadata | M04 | M04-M06 |
| Extractor security | type/size/parser failure controls | M05 | M05/M22 |
| Chunking/indexing | boundaries/hash/dedup/incremental behavior | M07 | M07 |
| Vector store contract | upsert/delete/search/filter/health | M08 | M08 |
| Job queue | leases/retry/recovery/idempotency | M09 | M09 |
| Retrieval quality | lexical/semantic/hybrid/filter/rerank | M10 | M10/M21 |
| RAG grounding | strict no-answer/citations/memory | M11 | M11/M21 |
| Streaming | event ordering/errors/disconnects | M11 | M11 |
| Admin JS/TS | components/state/API behavior | M12+ | relevant milestone |
| Playwright E2E | admin/widget workflows | M12+ | relevant milestone |
| Accessibility | keyboard/screen reader/axe/manual | M14-M15 | M14/M15/M24 |
| WooCommerce | product/action/order authorization | M06/M18 | M06/M18/M24 |
| Actions security | schema/authz/risk/audit/tool injection | M19 | M19/M22 |
| Privacy | retention/export/erasure | M22 | M22/M24 |
| Abuse/cost | rate limiting/denial-of-wallet | M22 | M22 |
| Upgrade compatibility | long-lived schema/data compatibility | M24 | M24 |

## M01 Verified Baseline

- PHP: PHPUnit/WPCS/PHPStan on PHP 8.2.
- JavaScript/TypeScript: Node 22 engine/package lint, JS lint, strict typecheck, Jest, and production build.
- WordPress integration: WordPress 6.9/PHP 8.2 activation/deactivation/reactivation.
- Packaging: runtime allowlist plus development/private-path rejection.

## M02 Verified Coverage

Runtime candidate `4db24d95db0d572f28273734714c74a47ac8bb2e`, CI run `33603435032`:

- **Migration unit behavior:** version ordering, already-applied skips, current-schema fast path, lock contention, failure version preservation, lock release, and post-lock version refresh race regression.
- **Migration SQL:** V001 creates only sources; V002 creates only documents; required indexes are asserted; future tables/vector fields/foreign keys/non-portable JSON types are rejected by tests.
- **Real WordPress migration:** clean schema creation, simulated V1→V2 upgrade through normal plugin loading, repeat/idempotent migration, expected indexes, and absence of future milestone tables.
- **Repository integration:** 25-source pagination 10/10/5 with total 25; max requested page size clamped to 100; exact malicious-looking source/document keys; apostrophe/script-like/Unicode content; JSON metadata round-trip; source-scoped pagination; exact affected-row deletion.
- **SQL injection regression:** values such as `source-' OR 1=1 --` and `" OR 1=1 --` remain literal data and do not expand result scope.
- **Uninstall:** default retention, explicit opt-in deletion, option cleanup, clean reinstall, plus unit coverage proving failed destructive queries throw and preserve retry state.
- **Package:** `uninstall.php` and `DatabaseUninstaller.php` are required archive members while tests/docs/.github/env/Node/dependency manifests remain excluded.
- **Dependency/static gates:** Composer audit, WPCS, PHPStan, PHPUnit, npm critical-audit gate, JS lint/typecheck/tests/build all pass.

Artifact evidence: ID `9836065304`, digest `sha256:f77b32bf377b4f6fbb65cf1721a87b5e0408041ad5444816076227ab931aeab3`.

## M03 Verified Coverage

Integrated `main` commit `2ed420a9217422f856afaf64b68fdde78ea0b063`, post-merge CI run `33670406871`:

- **Provider value/contracts:** stable IDs, normalized generation/result/status/usage, provider health/error/descriptor contracts, registry lookup and duplicate protection.
- **Credential precedence/storage:** environment -> constant -> encrypted option; blank runtime values fall through; options are non-autoloaded; save/load/delete behavior and failure cases covered.
- **Cryptography:** XChaCha20-Poly1305 preferred, AES-256-GCM fallback, provider-bound HKDF/AAD, strict envelope/base64 validation, cross-provider rejection, no-backend failure, and tamper/fail-closed behavior.
- **Secret safety:** Secret string/JSON/debug/export/native-serialization surfaces do not expose plaintext; known secrets and credential headers redact; 2048-byte diagnostic limit; regression proves a secret crossing the truncation boundary exposes no plaintext prefix.
- **Provider diagnostic IDs:** OpenAI/OpenRouter request-ID candidates are accepted only when known-secret sanitization leaves them unchanged; secret-bearing header/top-level IDs are rejected.
- **HTTP policy:** WordPress transport carries exact timeout/redirect values, classifies clear timeouts, strips raw transport diagnostics; generation sends once; discovery retries once only for transport/502/503/504.
- **Model catalog cache:** 900-second TTL, valid hit/miss, malformed transient eviction, invalidate, successful refresh, failed-refresh preservation.
- **OpenAI/OpenRouter:** fixed endpoint generation/model-discovery contracts, normalized HTTP errors, usage/status/request IDs, malformed payload rejection, and explicit metadata-only model capability handling.
- **WordPress AI Client:** public API feature detection, safe WP6.9 absence, public builder/result normalization, WP_Error sanitization, malformed-result handling, and fail-closed unexpected Throwable regression.
- **Provider bootstrap/configuration:** all M03 providers composed before plugin loaded signal; descriptor serialization is secret-free/local-only; bootstrap performs no provider HTTP call.
- **Real WordPress integration:** fake direct credential is encrypted in `wp_options`, envelope validated, autoload disabled, callback-only plaintext observation, delete behavior, precedence/fallback, runtime WP-AI feature detection, and secret-free descriptors.
- **Live gating:** normal CI proves the live wrapper skips unless explicitly opted in and validates provider/key/model gates without making live provider calls.
- **Package completeness:** every `src/Providers/**/*.php` runtime file is required in the release ZIP; development scripts/tests/docs/private manifests remain rejected.
- **Review regressions:** five Important findings fixed before completion; no unresolved Critical/Important findings.
- **Dependency/static gates:** Composer audit clean; WPCS/PHPStan clean; PHPUnit `134 tests / 747 assertions`; npm critical gate, JS lint/typecheck/Jest/build, WordPress smoke, and package artifact all green.
- **Exact-head integration gate:** documentation-complete head `da620a89d420bf22a7dc146b2cab84113f376fcf`, run `33670130318`, passed all four permanent jobs before merge.
- **Post-merge gate:** `main` run `33670406871` passed `php-quality`, `js-quality`, `wordpress-smoke`, and `package`; WordPress activation, database, and provider smoke all completed successfully.

Post-merge artifact evidence: ID `9862272933`, 64,804 bytes, digest `sha256:e44bd8abbc96c1577c66ff42b4d3ba6507bb37b067f71e0b2c05d6d69ca4782b`.
