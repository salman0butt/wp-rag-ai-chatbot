# Test Matrix

Status: ACTIVE — exact milestone evidence is recorded in each milestone ledger and permanent CI where applicable.

| Layer | Purpose | First Required | Evidence location |
|---|---|---|---|
| PHP unit | isolated PHP/plugin behavior | M01 | M01-M04 + CI `php-quality` |
| WordPress integration | hooks/plugin lifecycle/database/provider/knowledge runtime | M01 | M01-M04 + CI `wordpress-smoke` |
| JS/TS unit | build-tooling/component behavior | M01 | M01 + CI `js-quality` |
| Typecheck/lint/build | frontend/toolchain production integrity | M01 foundation; M12+ UI | M01 + relevant UI milestone |
| Dependency audit | known Composer/npm advisories | M01 | CI PHP/JS quality jobs |
| Release ZIP guard | required runtime files; exclude dev/private files | M01 foundation; M24 release | M01-M04 + CI `package`/package assertion; M24 final audit |
| DB migrations | fresh install + upgrades + idempotency + locking/failure recovery | M02 | M02 + CI `php-quality`/`wordpress-smoke` |
| Repository/SQL | prepared queries, pagination, source ownership/isolation | M02 | M02 + CI `wordpress-smoke` |
| Uninstall/data policy | retain default, explicit deletion, failure retryability | M02 | M02 + CI `php-quality`/`wordpress-smoke` |
| Provider contract | normalized generation/errors/capabilities | M03 | M03 + CI `php-quality` |
| Credential security | precedence/encryption/non-autoload/redaction/fail-closed errors | M03 | M03 + CI `php-quality`/`wordpress-smoke` |
| Provider HTTP policy | fixed endpoints/timeouts/redirects/retry classification | M03 | M03 + CI `php-quality` |
| Provider cache | bounded catalog TTL, malformed eviction, refresh preservation | M03 | M03 + CI `php-quality` |
| Live provider smoke | opt-in credential-gated path | M03 | M03 live-gating regression; actual live calls opt-in only |
| Knowledge source contract | normalization and access metadata | M04 | M04-M06 + CI `php-quality`/`wordpress-smoke` |
| Extractor security | type/size/parser failure controls | M05 | M05/M22 |
| Chunking/indexing | boundaries/hash/dedup/incremental behavior | M07 | M07 |
| Vector store contract | upsert/delete/search/filter/health | M08 | M08 + `docs/progress/M08-CLOSEOUT.md` |
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

- Migration unit behavior covers version ordering, skips, current-schema fast path, lock contention, failure preservation/release, and post-lock refresh race.
- Migration SQL and real WordPress migration assert the intended M02 schema only, fresh install, V1→V2 upgrade, idempotency, and indexes.
- Repository integration covers bounded pagination, exact malicious-looking keys, apostrophe/script-like/Unicode content, JSON metadata, source isolation, affected-row deletion, and SQL-injection-shaped literal values.
- Uninstall covers retain-by-default, explicit deletion, retryable failure, option cleanup, and clean reinstall.
- Package/dependency/static gates pass.

Artifact evidence: ID `9836065304`, digest `sha256:f77b32bf377b4f6fbb65cf1721a87b5e0408041ad5444816076227ab931aeab3`.

## M03 Verified Coverage

Integrated `main` commit `2ed420a9217422f856afaf64b68fdde78ea0b063`, post-merge CI run `33670406871`:

- Provider contracts, credential precedence/encrypted storage, authenticated crypto, secret redaction/export safety, fixed HTTP policy, cache behavior, OpenAI/OpenRouter adapters, optional WordPress AI Client, bootstrap/configuration, real WordPress credential smoke, live-call gating, and production-package completeness are covered.
- Five Important review findings were fixed before completion with focused regressions; unresolved Critical/Important findings: none.
- Composer audit, WPCS/PHPStan, PHPUnit `134 tests / 747 assertions`, npm critical gate, JS lint/typecheck/Jest/build, WordPress smoke, and package artifact all passed.
- Documentation-complete head `da620a89d420bf22a7dc146b2cab84113f376fcf`, run `33670130318`, passed all permanent jobs before merge; post-merge run `33670406871` passed all permanent jobs.

Post-merge artifact evidence: ID `9862272933`, 64,804 bytes, digest `sha256:e44bd8abbc96c1577c66ff42b4d3ba6507bb37b067f71e0b2c05d6d69ca4782b`.

## M04 Verified Coverage

Task 7 integration candidate `aa246186a218efa7208403c36fecd051c6c143ee`, CI run `33687296386`:

- **Source contract/registry:** stable source types, registration/lookup/order, duplicate/empty rejection, extension validation, and no partial registry publication.
- **Deterministic hashing:** recursively canonicalized associative keys, preserved list order, SHA-256 content hashes, and content/access metadata sensitivity.
- **Manual text:** persisted-source guard, deterministic one-document normalization, blank/visibility validation, stable key/version/hash, language and visibility metadata.
- **FAQ:** deterministic per-item keys/content/hash, full-list validation before first yield, malformed item/visibility/persistence rejection. The Important partial-yield defect has permanent RED/GREEN regression coverage.
- **WordPress gateway:** public post-type discovery, bounded 1..100 paging, stable ID order, publish-only default/private opt-in, password exclusion, canonical permalink, author/status/text/taxonomy mapping, no arbitrary post meta.
- **WordPress post source:** default post/page and configured public CPT selection, unsupported-type rejection, draft/pending/trash/password exclusion, explicit private opt-in, deterministic content/taxonomy metadata, stable `wp-post:{type}:{id}` key and `{modified_gmt}:{id}` version, multi-page consumption.
- **Adapter sanitization:** dedicated Task 5 regression proves native WordPress title/excerpt/body HTML is stripped before crossing the gateway boundary.
- **Knowledge bootstrap:** native manual/FAQ/WordPress source composition, plugins-loaded integration, valid extensions, invalid extension rejection, fail-closed composition.
- **Real WordPress knowledge integration:** permanent `npm run test:wp:knowledge` creates published page/post, private, draft, and password-protected fixtures and proves public normalization, private default exclusion/opt-in inclusion, draft/password exclusion, canonical permalink/text, stable key/hash, and cleanup.
- **Review:** one Important FAQ integrity defect and one adapter sanitization defect were fixed with regression evidence; final Task 8 review has no unresolved Critical/Important findings. Minor defensive-test isolation gaps and per-post taxonomy lookup remain non-blocking documented considerations.
- **Dependency/static/permanent gates:** exact `aa246186...` run `33687296386` passed `php-quality`, `js-quality`, `wordpress-smoke`, and `package`; WordPress activation/database/provider/knowledge smoke all passed.

Pre-integration artifact: ID `9868623773`, 75,709 bytes, digest `sha256:41f285035d298187635bfbfd9d9f8aff828003439384979503ba37e84b8b3fbf`.

## M08 Verified Coverage — Feature Complete Candidate

Exact Task 9 implementation head `3baef98b31d0d85cbde0c6cd130274645d489505`, CI `33929387362`:

- `php-quality`: PHPStan 0 errors; PHPUnit 407/407, 1,866 assertions; Composer audit clean.
- `js-quality`: lint/typecheck/Jest/build and permanent live-gating/package checks passed.
- `package`: production install/build/ZIP/assertion passed; artifact ID `9957988866`, digest `sha256:6eb92f7b5d3502975263c9a62fb3be01d4fb8390a16a87147ee36302fc02fd9b`.
- `wordpress-smoke`: activation, database, providers, knowledge, file ingestion, and WooCommerce knowledge all passed.
- Task 9 genuine RED `c356c65b9bad72346e139e1a1b7c76cfb6403b80` / CI `33928846507`: exactly three intended missing-integration errors after lint/static passed.
- Task 9 review RED `de93fcc0045ece00cf5decea052953b2b33a2a11` / CI `33929269542`: exactly two intended failures for embedding provider/profile mismatch and unbounded delete work.
- Whole-M08 review `5118637420`: Critical 0 / Important 0 unresolved; no unresolved inline review threads at review time.
- Synthetic exact-artifact Task 9 benchmark: 1,000 upserts, 8 dimensions, batch 100, fake in-process provider/store; six measured runs 7.131–7.675 ms, median 7.399 ms; 10 provider batches, 1,000 writes. No network/database/vector-engine throughput claim is made.
- Detailed security/performance/closeout evidence: `docs/progress/M08-CLOSEOUT.md`.

The final documentation-head CI and post-merge `main` CI remain mandatory before M08 is marked complete on `main`.