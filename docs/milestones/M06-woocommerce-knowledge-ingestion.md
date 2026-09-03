# M06 — WooCommerce Knowledge Ingestion

Status: IN PROGRESS — design/spec and implementation plan auto-approved; Tasks 1–3 complete; Task 4 next.

## Goal
Normalize stable public WooCommerce catalog knowledge while clearly separating indexed descriptive facts from live transactional state.

## Dependencies
M04; M05 patterns; WooCommerce optional environment.

## Design / plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md`.
- Status: `AUTO-APPROVED — SCHEDULED MODE` after repository-mandated self-review.

## Architecture
`NativeWooCommerceCatalogGateway` -> immutable allowlisted `WooCommerceProduct` snapshots -> `WooCommerceProductSource` -> canonical `DocumentRecord`.

WooCommerce remains optional. Native WooCommerce APIs stay behind the gateway. Stable descriptive product facts are indexable; current price, sale state, stock/variation availability, cart/order state, discounts, customer-specific values, and arbitrary metadata are excluded from indexed documents and source versioning.

Whole-catalog traversal uses bounded deterministic pages of published candidate IDs. Candidate page cardinality is preserved until `WooCommerceProductSource` decides whether the raw catalog page is exhausted; fail-closed type/visibility/password eligibility is enforced when each candidate is loaded through `product()`. Duplicate candidate IDs are intentionally preserved by the gateway so they cannot shorten a full page, while source-level `$seen` suppression prevents duplicate documents.

## Acceptance Criteria
- Simple and variable products normalize correctly.
- Exact SKU/category/tag/attribute/variation metadata is preserved deterministically.
- Public visibility/status policy fails closed.
- Descriptive catalog updates change source version/hash as appropriate.
- Price/stock-only changes do not change M06 source version/hash.
- Removed/unpublished products disappear from source output.
- Customer/order/private/arbitrary metadata is never indexed by default.
- Disabled WooCommerce does not break plugin activation/bootstrap and yields no product documents.
- Whole-catalog enumeration is bounded, deterministic, and reviewed for N+1 behavior.

## Tasks
- [x] Task 1 — WooCommerce catalog contracts and immutable snapshots.
- [x] Task 2 — Optional-safe native WooCommerce catalog gateway.
- [x] Task 3 — WooCommerce product source canonical mapping.
- [ ] Task 4 — Stable source version and live-state exclusion regressions.
- [ ] Task 5 — Knowledge bootstrap registration and disabled-WooCommerce safety.
- [ ] Task 6 — Real WordPress/WooCommerce smoke coverage.
- [ ] Task 7 — Integration, compatibility/security/performance review, documentation, merge, and post-merge verification.

## Task 1 durable evidence
Task 1 introduced the WooCommerce-independent gateway contract plus immutable `WooCommerceProduct` and `WooCommerceVariation` snapshots containing only stable public catalog facts.

- Primary behavioral RED: `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`, CI `33723839314` — PHPStan clean; PHPUnit 229 tests / 1109 assertions / 7 expected failures for absent contracts.
- Initial production implementation: `eb29bca8ee178c4542bf194821b5a4851c951c17`.
- Independent review found 0 Critical / 1 Important: normalized attribute-name collisions could silently overwrite stable facts.
- Product-collision RED: `d647ebd31731a5c1cce0ea05d49733333ebda2c5`, CI `33727034899`.
- Product-collision GREEN: `21447be4d4d498311b4a48630f1b227a76415237`, CI `33727169180`.
- Variation-collision RED: `f547bcb6b25b2f52a9cf9e32041d384f3479c4f0`, CI `33727270728`.
- Final reviewed GREEN: `17358f11c2348e002786b82ffb2d534fee2e5ae1`, CI `33727368805` — PHPStan clean; PHPUnit 234 / 1136; Composer audit clean; php-quality, js-quality, package, wordpress-smoke all green.
- Artifact: `9882495514`, 706232 bytes, digest `sha256:d544d7ef3c124a9495149f50e645e2643f7f89208645e40db69a93701d6ef5d2`.
- Final Task 1 review: **0 Critical / 0 Important unresolved**.

## Task 2 durable evidence
Task 2 introduced `NativeWooCommerceCatalogGateway` using public runtime WordPress/WooCommerce APIs only.

- Availability/enumeration behavioral RED: `37334fbc03c593be038e29c44c2d5a416c95a756`, CI `33728200033` — PHPStan clean; PHPUnit 239 / 1141 / 5 expected failures.
- Initial bounded enumeration implementation: `1ae33a08093594cd9ec020a6663c47fc112bc9ce`; optional callable/static-analysis corrections culminated at `eb021d95a7773646877a20dc693a16ccfb866d10`, CI `33731515604` all permanent jobs green.
- Simple-product RED: `1e82721ff07b114796a24aeb0b901aab264adcf5`, CI `33732021036` — PHPStan clean; PHPUnit 240 / 1153 / 1 expected failure; implementation `fc99fa6a551c947674f5297dc8e41abd7b63311f`.
- Variable-product RED lineage: `82ff9ea6edffc2c219f49b878e98aba75023e69e` -> `2c9c1be2a4ff2e6186aae6b63e7fb67831da8acd`, CI `33732847268`; stable variation implementation `22740eea50b75c48cf35f83a136b4bfbdeedcc26`.
- Fresh-session GMT regression: RED `a4cc2d5c09332819fe65cef2f0c78854b8f8db55`, CI `33733459102` — PHPUnit 242 / 1176 / 1 expected failure; GREEN `d14f657a59ba12f11231331551c093b4a84df8ba`, CI `33734311953` all permanent jobs green.
- Final acceptance coverage: `626adf8b45cce1dd6a02356147b20994b64f4074`; CI `33734709484` — all permanent jobs green; PHPStan clean; PHPUnit **243 / 1182**; Composer audit clean.
- Artifact: `9885271471`, 708841 bytes, digest `sha256:357b73583f7d9617231ebd4f233cfde8645d38fcb59e1e9ce786bd7818d05edf`.
- Final Task 2 privacy/performance review: **0 Critical / 0 Important unresolved**.

Current Task 2/3 boundary after pagination review: `productIds()` performs one bounded `wc_get_products(... return => ids)` query and returns deterministic published candidate IDs without eligibility hydration. `WooCommerceProductSource` then loads each candidate through `product()`, which fails closed on unsupported type, hidden visibility, password protection, missing data, or invalid stable snapshot inputs. This preserves bounded page-at-a-time traversal and avoids using post-filter cardinality as catalog EOF.

## Task 3 durable evidence
Task 3 introduced `WooCommerceProductSource`, supporting either explicit positive product IDs or bounded whole-catalog mode. It maps eligible immutable product snapshots to deterministic canonical `DocumentRecord` values with stable `woocommerce_product:{id}` identity, readable stable catalog content, public visibility, and a strict metadata allowlist.

### Primary source TDD
- Test support: `3fcd531624b4c6b23351a03a16c64d5c23b63462`.
- Standards-clean primary behavioral RED: `22a6935c32abb6083ac574430b647431ca724032`, CI `33736321322` — PHPStan clean; PHPUnit **250 tests / 1189 assertions / 7 failures**, all expected because `WooCommerceProductSource` did not yet exist.
- Initial implementation: `19dc95a8c078faec044e2ffd4bba7fb0f99872dd`.
- Static-analysis correction: `f778782cb4910ff87ce1370be20010b3d1bb705e`, CI `33736691689` — all permanent jobs green.

### Fail-closed configuration regression
A same-session review found unsupported config keys could be silently ignored.

- RED: `c9a9c7c32453c8226024e3948d0beb0f72dc875e`, CI `33736986657`.
- Corrected GREEN lineage culminated at `2fbad738dc8b96ec89795805666d82b8a398e520`.
- CI `33737351181` — php-quality, js-quality, package, and wordpress-smoke all passed; PHPStan clean; PHPUnit **251 / 1219**; Composer audit clean.
- Artifact `9886291161`, digest `sha256:78227cc531646e2eb311c7c951f0f42210a2b5ffcf25acc6add6aec64c34dab4`.

### Independent pagination review and correction
Fresh review `5100102218` at `2fbad738...` found **0 Critical / 1 Important**: `WooCommerceProductSource` treated a short page returned by `productIds()` as EOF, while the native gateway shortened pages through type/visibility/password filtering. A raw full page could therefore become short and prevent traversal of later eligible products.

The selected minimal architecture-preserving correction was to make the gateway return bounded published candidate pages and keep eligibility fail-closed in `product()`. Contract wording was aligned at `d2a828cf758db8403d3c226e395fd9bb5f86ab51`; CI `33746900882` passed all permanent jobs with PHPStan clean and PHPUnit **252 / 1218**.

A further cardinality edge was then routed through a genuine RED/GREEN cycle: gateway-level duplicate de-duplication could also shorten a full raw page before the source's existing `$seen` duplicate suppression.

- Genuine behavioral RED: `028e5247acf4ced12f4e347a71bdc4e5f5ab8fc1`, CI `33747195823`.
- Static analysis: PHPStan clean.
- PHPUnit: **253 / 1219 / 1 failure** — `NativeWooCommerceCatalogPaginationCardinalityTest::test_product_ids_preserves_duplicate_candidate_cardinality`; expected `[3,9,9]`, actual `[3,9]`.
- Minimal GREEN: `2931ecf3ea9cf5f946e75e0228a628e4430a28db` removes gateway-level duplicate collapse so candidate page cardinality remains truthful; duplicate document suppression remains in the source.
- Exact-head GREEN CI: `33747391688` — PHPStan clean; PHPUnit **253 tests / 1219 assertions**, all passed; Composer audit clean; php-quality, js-quality, package, and wordpress-smoke all passed.
- Artifact: `9890174635`, 711932 bytes, digest `sha256:ab851a0f6b7f3c7a573efd2ce099061b755eab883bbfd0d8dc1d8784a95ee6b6`.
- Final fresh-session review `5101080712`: **0 Critical / 0 Important unresolved**.

## Task 3 security/privacy/performance review
Result: **0 Critical / 0 Important unresolved**.

Verified:
- disabled WooCommerce remains non-fatal at the source boundary;
- explicit IDs are positive, deduplicated, and sorted before lookup;
- unsupported/ambiguous/unknown configuration fails closed;
- catalog page size is bounded to 250;
- candidate page cardinality survives eligibility filtering and duplicate candidates;
- source-level duplicate suppression prevents duplicate documents;
- only `product()`-approved public snapshots become documents;
- canonical content/metadata excludes price, stock, discounts, orders, customers, arbitrary meta, and other live/private state;
- no whole-catalog eager load was introduced;
- Task 4 remains responsible for stable source-version semantics beyond the current modified-marker behavior.

## Accessibility review
N/A — M06 Tasks 1–3 introduce no UI.

## Review findings summary
- Task 1: 0 Critical / 1 Important initially; fixed through regression TDD; final 0 / 0.
- Task 2: 0 Critical / 1 Important GMT regression; fixed through RED/GREEN; final 0 / 0.
- Task 3: 0 Critical / 1 Important pagination exhaustion finding; corrected, hardened through genuine cardinality RED/GREEN, final review `5101080712` is 0 / 0.
- PR #8 has no unresolved blocking inline review threads.

## Known limitations
One canonical document per product in M06. Variations are structured product metadata, not standalone documents. Live commerce facts remain intentionally deferred to authorized runtime services/actions. Real enabled/disabled WooCommerce integration fixtures remain Task 6.

## Completion checklist
M06 is not complete until Tasks 4–7, whole-milestone review, exact-final-SHA permanent CI, merge, and fresh post-merge `main` CI all pass.

## Exact next unfinished action
Execute **Task 4 — Stable source version and live-state exclusion regressions** with strict TDD. Add the planned version tests proving stable descriptive snapshot changes alter source version/hash while excluded live-only price/stock state is not consulted; implement the smallest canonical stable snapshot versioning behavior required by the design, verify exact-head GREEN, perform fresh review, and update durable evidence before Task 5.

## Next Milestone
M07 — Normalization, Chunking, Deduplication & Incremental Indexing.
