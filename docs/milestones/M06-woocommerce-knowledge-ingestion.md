# M06 — WooCommerce Knowledge Ingestion

Status: IN PROGRESS — design/spec and implementation plan auto-approved; Tasks 1–2 complete; Task 3 next.

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
- [ ] Task 3 — WooCommerce product source canonical mapping.
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

## Task 2 implementation
Task 2 introduced `NativeWooCommerceCatalogGateway` using public runtime WordPress/WooCommerce APIs only.

Verified behavior:
- WooCommerce absence is non-fatal and yields no IDs/product snapshots.
- Page/per-page inputs are validated and page size is hard-bounded at 250.
- `wc_get_products()` is called with publish status, bounded paging, `return => ids`, and deterministic ID ordering.
- Returned IDs are normalized, current-page products are hydrated and fail-closed through supported type, catalog visibility, and password checks, then unique numeric IDs are sorted ascending.
- Missing/deleted products resolve to `null`.
- Simple products map stable status/visibility/name/descriptions/SKU/canonical URL/categories/tags/descriptive attributes/modified marker.
- Variable products additionally map stable variation ID/SKU/options; variations are deterministically ordered by the immutable contract.
- Product modified instants are normalized to UTC/GMT with `getTimestamp()` + `gmdate('c', ...)` rather than preserving site timezone offsets.
- No current price, sale, stock, availability, cart/order/customer/discount, or arbitrary metadata APIs are read or exposed.

## Task 2 TDD evidence
### Availability / bounded enumeration RED
- Standards-clean behavioral RED: `37334fbc03c593be038e29c44c2d5a416c95a756`.
- CI: `33728200033`.
- PHPStan: no errors.
- PHPUnit: 239 tests / 1141 assertions / 5 failures, all expected absent native-gateway behaviors.
- Initial implementation: `1ae33a08093594cd9ec020a6663c47fc112bc9ce`.
- Static-analysis/runtime callable corrections: `dedf21324842a3994b2d5d74c22171a601bd8749` and `eb021d95a7773646877a20dc693a16ccfb866d10`.
- Enumeration GREEN: `eb021d95a7773646877a20dc693a16ccfb866d10`, CI `33731515604` — all four permanent jobs green.

### Simple product normalization RED
- Test-only lineage began at `fa55a02f3605e5c79ddedd72d00c37035c88a096`.
- Standards-clean RED head: `1e82721ff07b114796a24aeb0b901aab264adcf5`, CI `33732021036`.
- PHPStan: no errors.
- PHPUnit: 240 tests / 1153 assertions / 1 expected failure: public simple product still normalized to `null`.
- Minimum mapping implementation: `fc99fa6a551c947674f5297dc8e41abd7b63311f`.

### Variable product normalization RED
- Variable-product RED introduced at `82ff9ea6edffc2c219f49b878e98aba75023e69e` and kept analysis/lint clean through `7baa0cbf1c7c9c9e10dc9d6e76ba4f3950342d51` / `2c9c1be2a4ff2e6186aae6b63e7fb67831da8acd`.
- CI `33732847268` remained RED before the stable variation mapping.
- Minimum variation mapping: `22740eea50b75c48cf35f83a136b4bfbdeedcc26`.

### Independent fresh-session regression — modified marker was not actually GMT
A separate scheduled-development invocation reviewed the already-implemented mapping and added a timezone regression. It exposed an Important correctness defect: formatting the WooCommerce date object with `date('c')` preserved the site offset despite the contract field being `modifiedGmt`.

- Regression test: `a08f19a0ba30b929f08797f1d4beeb299774b09b`.
- Standards-clean RED head: `a4cc2d5c09332819fe65cef2f0c78854b8f8db55`.
- CI: `33733459102` — PHPStan clean; PHPUnit 242 tests / 1176 assertions / 1 failure; expected `2026-09-03T09:00:00+00:00`, actual `2026-09-03T14:00:00+05:00`.
- GREEN fix: `d14f657a59ba12f11231331551c093b4a84df8ba` — normalize the absolute modification instant via `getTimestamp()` and `gmdate('c', ...)`.
- CI: `33734311953` — all permanent jobs green; PHPStan clean; PHPUnit 242 / 1176; Composer audit clean.

### Final Task 2 acceptance coverage and verification
- Acceptance-coverage commit: `626adf8b45cce1dd6a02356147b20994b64f4074` adds explicit missing/deleted-product coverage and direct assertions for status, catalog visibility, descriptions, canonical URL and the rest of the allowlisted simple-product mapping. This is verification coverage for already-implemented behavior, not a claimed RED cycle.
- Exact-head CI: `33734709484` — **SUCCESS**.
- PHPStan: no errors.
- PHPUnit: **243 tests / 1182 assertions**, all passed.
- Composer audit: no security vulnerability advisories.
- Permanent jobs: php-quality, js-quality, package, wordpress-smoke all passed.
- Artifact: `9885271471`, 708841 bytes, digest `sha256:357b73583f7d9617231ebd4f233cfde8645d38fcb59e1e9ce786bd7818d05edf`.

## Task 2 security/privacy review
Result after the GMT regression fix: **0 Critical / 0 Important unresolved**.

Verified:
- no native WooCommerce class is required by the application-facing contract or plugin load path;
- optional runtime function checks fail safely;
- only public product methods, `get_term`, `get_post_field`, and `wc_get_product_terms` are used;
- no private WooCommerce data stores or arbitrary meta reads exist;
- unsupported types, hidden products, and password-protected products fail closed;
- live commerce/customer state is absent from snapshots.

## Task 2 performance review
Result: **0 Critical / 0 Important unresolved**.

Enumeration performs one bounded `wc_get_products(... return => ids)` query and hydrates at most the requested current page (hard maximum 250) to enforce type/visibility/password eligibility. The current-page hydration is an intentional bounded N+1 tradeoff for fail-closed public eligibility; no whole-catalog eager load is introduced. Task 3 retains paging rather than broadening this bound.

## Accessibility review
N/A — M06 Tasks 1–2 introduce no UI.

## Review findings summary
- Task 1 independent review: 0 Critical / 1 Important initially; fixed through two regression TDD cycles; final 0 / 0.
- Task 2 separate fresh-session review/regression: 0 Critical / 1 Important (non-GMT `modifiedGmt`); fixed through RED `a4cc2d5c...` -> GREEN `d14f657a...`; final bounded privacy/performance review 0 Critical / 0 Important unresolved.
- PR #8 currently has no unresolved submitted review threads.

## Known limitations
One canonical document per product in M06. Variations are structured product metadata, not standalone documents. Live commerce facts remain intentionally deferred to authorized runtime services/actions. Real enabled/disabled WooCommerce integration fixtures remain Task 6.

## Completion checklist
M06 is not complete until Tasks 3–7, whole-milestone review, exact-final-SHA permanent CI, merge, and fresh post-merge `main` CI all pass.

## Exact next unfinished action
Execute **Task 3 — WooCommerce product source canonical mapping** with strict TDD. Add a test-only RED for disabled gateway behavior, explicit product IDs, bounded whole-catalog paging, malformed configuration, stable `woocommerce_product:{id}` identity, public visibility/title/URL, deterministic readable content, and allowlisted metadata; then implement the minimum `WooCommerceProductSource`, verify exact-head GREEN, review deterministic ordering/privacy, and update durable evidence before Task 4.

## Next Milestone
M07 — Normalization, Chunking, Deduplication & Incremental Indexing.
