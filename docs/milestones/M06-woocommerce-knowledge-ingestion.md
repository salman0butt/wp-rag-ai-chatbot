# M06 — WooCommerce Knowledge Ingestion

Status: IN PROGRESS — design/spec and implementation plan auto-approved; Tasks 1–4 complete; Task 5 implementation/exact-SHA CI green with independent review pending.

## Goal
Normalize stable public WooCommerce catalog knowledge while clearly separating indexed descriptive facts from live transactional state.

## Dependencies
M04; M05 patterns; WooCommerce optional environment.

## Design / plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md`.
- Status: `AUTO-APPROVED — SCHEDULED MODE` after repository-mandated self-review.
- Task 4 auto-approved clarification: the generic WooCommerce modified marker is retained in the gateway snapshot for observability but is not a canonical source-version input, because it cannot distinguish descriptive changes from excluded live-state changes.
- Task 4 executable plan wording aligned at `fd1a68cdb25437a8c20bd223f6e56d571b8c1c0a` after independent review.

## Architecture
`NativeWooCommerceCatalogGateway` -> immutable allowlisted `WooCommerceProduct` snapshots -> `WooCommerceProductSource` -> canonical `DocumentRecord`.

WooCommerce remains optional. Native WooCommerce APIs stay behind the gateway. Stable descriptive product facts are indexable; current price, sale state, stock/variation availability, cart/order state, discounts, customer-specific values, arbitrary metadata, and generic modified-marker-only churn are excluded from indexed documents and source versioning.

Whole-catalog traversal uses bounded deterministic pages of published candidate IDs. Candidate page cardinality is preserved until `WooCommerceProductSource` decides whether the raw catalog page is exhausted; fail-closed type/visibility/password eligibility is enforced when each candidate is loaded through `product()`. Duplicate candidate IDs are intentionally preserved by the gateway so they cannot shorten a full page, while source-level `$seen` suppression prevents duplicate documents.

## Acceptance Criteria
- Simple and variable products normalize correctly.
- Exact SKU/category/tag/attribute/variation metadata is preserved deterministically.
- Public visibility/status policy fails closed.
- Descriptive catalog updates change source version/hash as appropriate.
- Price/stock-only changes, including accompanying generic modified-marker churn, do not change M06 source version/hash.
- Removed/unpublished products disappear from source output.
- Customer/order/private/arbitrary metadata is never indexed by default.
- Disabled WooCommerce does not break plugin activation/bootstrap and yields no product documents.
- Whole-catalog enumeration is bounded, deterministic, and reviewed for N+1 behavior.

## Tasks
- [x] Task 1 — WooCommerce catalog contracts and immutable snapshots.
- [x] Task 2 — Optional-safe native WooCommerce catalog gateway.
- [x] Task 3 — WooCommerce product source canonical mapping.
- [x] Task 4 — Stable source version and live-state exclusion regressions.
- [ ] Task 5 — Knowledge bootstrap registration and disabled-WooCommerce safety. **Implementation + exact-code-head CI green; independent review pending.**
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
- no whole-catalog eager load was introduced.

## Task 4 durable evidence — COMPLETE
Task 4 replaces the legacy `modifiedGmt:id` source version with a deterministic SHA-256 over canonical stable catalog facts and hardens the live-state boundary so generic modification-time churn cannot masquerade as descriptive knowledge change.

### Stable descriptive change TDD
- Initial test-only commit: `ef70acd32d9bfca35aa0e4e287fdf39c2f1597ea`, CI `33749041729`; PHPCS stopped execution before PHPUnit, so this is not behavioral RED evidence.
- Executable behavioral RED: `f145303afee722c9c333408c2947059ac1b83246`, CI `33749130132` — PHPStan clean; PHPUnit **255 tests / 1227 assertions / 1 expected failure** because changing SKU/description/category/attribute/variation facts with a fixed timestamp left the legacy source version unchanged.
- Initial stable snapshot hash implementation: `7a0d6a5681e8b3baa4187de277984b2f07569173`.
- CI `33749325652` reached only a PHPCS assignment-alignment warning; formatting-only correction: `f62d4963a18c0687bf0ebac5a668cdb094c327d4`.

### Generic modified-marker exclusion regression
Same-session acceptance review found the original design wording internally inconsistent: it required both generic modified-marker participation and zero version/hash changes for price/stock-only updates. `NativeWooCommerceCatalogGateway` receives only generic `get_date_modified()` and no cause-specific marker, so the timestamp cannot safely identify stable knowledge changes.

- Genuine behavioral RED: `73cf87f085682dc1ee843cc0548915ee7b83b9c3`, CI `33749715650`.
- PHPStan: clean.
- PHPUnit: **256 tests / 1243 assertions / 1 expected failure**, exactly `WooCommerceProductSourceVersionTest::test_modified_time_alone_does_not_affect_source_version_or_content_hash`.
- Minimal correction: `595c9e7c483e42914c901e08afec9b8db935d9d1` removes only generic `modifiedGmt` from the canonical version input. Canonical URL, deterministic content, and strict allowlisted metadata remain version inputs.
- Exact-code-head GREEN CI: `33749868280` — PHPStan clean; PHPUnit **256 / 1244**, all passed; Composer audit clean; php-quality, js-quality, package, and wordpress-smoke all passed.
- Artifact: `9891098806`, 711932 bytes, digest `sha256:8cb875edfe65f2a7e10f70d40d37d7d17efd5b394efdce04579e6a6429581a74`.
- Design clarification commit: `2c4f75d1f088d29652cca147ebe66f8812e58e8a`, auto-approved under scheduled-mode procedure.
- Same-session review submission `5101332920`: **0 Critical / 0 Important unresolved in code/test**, explicitly **not independent**.
- Independent fresh-session review `5101591315`: **0 Critical / 1 Important** — executable Task 4 Step 3 still allowed generic modified timestamp participation, contradicting the corrected spec/code and risking reintroduction of live-state churn into source versioning.
- Documentation correction: `fd1a68cdb25437a8c20bd223f6e56d571b8c1c0a` aligns Task 4 Step 3 with the auto-approved clarification.
- Exact-head CI `33752938100`: php-quality, js-quality, package, and wordpress-smoke all passed.
- Final independent re-review `5101636297`: **0 Critical / 0 Important unresolved**.

## Task 4 review result
Result: **0 Critical / 0 Important unresolved**.

Verified:
- stable descriptive changes alter source version and final content hash;
- price/stock values are absent from immutable source snapshots and never consulted by `WooCommerceProductSource`;
- generic modified-marker-only churn cannot change source version/hash;
- version input is canonical and deterministic through `DocumentHasher`;
- final content hash still includes canonical identity/content/metadata/source version;
- no new private/customer/order/arbitrary metadata path was introduced;
- no Task 5 bootstrap behavior was pulled into Task 4;
- executable plan, corrected design, tests, and production behavior now agree.

## Task 5 durable evidence — independent review pending
Task 5 adds the stable native `woocommerce_product` registry entry while keeping WooCommerce optional at plugin/bootstrap time.

- Genuine behavioral RED: `f3db0af57bb108b80872c5ec65852d393d9036d1`, CI `33753332250`.
- PHPStan: clean.
- PHPUnit: **256 tests / 1243 assertions / 1 expected failure** — `KnowledgeBootstrapTest::test_register_builds_default_registry_and_applies_extension_filter` expected `woocommerce_product` in the native registry, but it was absent.
- Minimal GREEN implementation: `50c3ea2292a9bc5d768416ab3c9793f6e59b1ab9` registers `new WooCommerceProductSource( new NativeWooCommerceCatalogGateway() )` without invoking WooCommerce APIs during bootstrap.
- Exact-code-head GREEN CI: `33753477407` — PHPStan clean; PHPUnit **256 tests / 1245 assertions**, all passed; Composer audit clean; php-quality, js-quality, package, and wordpress-smoke all passed.
- Artifact: `9892483643`, 711954 bytes, digest `sha256:a93ae167ca7db67ef43007ef7b754633708567cfc6c72bc2788a6c1e689a8f4c`.
- Same-session review `5101700445`: **0 Critical / 0 Important unresolved in code/test**, explicitly **not independent**.
- Required independent Task 5 review: **PENDING**. Task 5 remains unchecked and Task 6 remains blocked until that review reports 0 Critical / 0 Important unresolved and any findings are fixed.

## Accessibility review
N/A — M06 Tasks 1–5 introduce no UI.

## Review findings summary
- Task 1: 0 Critical / 1 Important initially; fixed through regression TDD; final 0 / 0.
- Task 2: 0 Critical / 1 Important GMT regression; fixed through RED/GREEN; final 0 / 0.
- Task 3: 0 Critical / 1 Important pagination exhaustion finding; corrected, hardened through genuine cardinality RED/GREEN; final review `5101080712` is 0 / 0.
- Task 4: independent review `5101591315` found 0 Critical / 1 Important stale-plan contradiction; fixed at `fd1a68c...`; final review `5101636297` is 0 / 0.
- Task 5: same-session review 0 / 0; **independent review pending**, so Task 5 is not complete.
- PR #8 has no known unresolved blocking inline review threads; the missing independent Task 5 review is the active quality gate.

## Known limitations
One canonical document per product in M06. Variations are structured product metadata, not standalone documents. Live commerce facts remain intentionally deferred to authorized runtime services/actions. Real enabled/disabled WooCommerce integration fixtures remain Task 6.

## Completion checklist
M06 is not complete until Task 5 independent review, Tasks 6–7, whole-milestone review, exact-final-SHA permanent CI, merge, and fresh post-merge `main` CI all pass.

## Exact next unfinished action
Perform a **genuinely independent fresh-session Task 5 review** against predecessor `fd1a68cdb25437a8c20bd223f6e56d571b8c1c0a` through the final Task 5 code/docs head. Review optional-WooCommerce bootstrap safety, native registry type stability, extension hook ordering/collision behavior, preservation of existing native sources, and Task 6 scope. Fix any Critical/Important finding through strict regression TDD when behavioral, require exact-head GREEN CI, and record the final 0 Critical / 0 Important review. Only then mark Task 5 complete and begin Task 6.

## Next Milestone
M07 — Normalization, Chunking, Deduplication & Incremental Indexing.
