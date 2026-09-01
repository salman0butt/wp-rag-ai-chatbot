# Test Matrix

Status: ACTIVE — exact milestone evidence is recorded in each milestone ledger and permanent CI where applicable.

| Layer | Purpose | First Required | Evidence location |
|---|---|---|---|
| PHP unit | isolated PHP/plugin behavior | M01 | M01 + CI `php-quality` |
| WordPress integration | hooks/REST/plugin lifecycle | M01 | M01 + CI `wordpress-smoke` |
| JS/TS unit | build-tooling/component behavior | M01 | M01 + CI `js-quality` |
| Typecheck/lint/build | frontend/toolchain production integrity | M01 foundation; M12+ UI | M01 + relevant UI milestone |
| Dependency audit | known Composer/npm advisories | M01 | M01 + CI PHP/JS quality jobs |
| Release ZIP guard | required runtime files; exclude dev/private files | M01 foundation; M24 release | M01 + CI `package`; M24 final audit |
| DB migrations | fresh install + upgrades + idempotency | M02 | M02/M24 |
| Repository/SQL | prepared queries, pagination, ownership | M02 | M02+ |
| Provider contract | normalized generation/errors/capabilities | M03 | M03 |
| Live provider smoke | opt-in credential-gated path | M03 | M03/M24 |
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
| Upgrade/uninstall | migration compatibility/data policy | M24 | M24 |

## M01 Verified Baseline

- PHP: PHPUnit 10.5.64 on PHP 8.2.33 — 4 tests / 12 assertions; WPCS pass; PHPStan pass.
- JavaScript/TypeScript: Node 22 — engine/package lint, JS lint, strict typecheck, 1 Jest test, and production build pass.
- WordPress integration: WordPress 6.9 / PHP 8.2 activation → bootstrap resolution → deactivation → reactivation passes.
- Packaging: production ZIP includes plugin entry point, `src/Core/Bootstrap.php`, and `vendor/autoload.php`; development/private paths are rejected.
