# M06 — WooCommerce Knowledge Ingestion

Status: IN PROGRESS — design/spec and implementation plan auto-approved; Task 1 complete; Task 2 next.

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

## In Scope
- Simple/variable product normalization.
- Product title, descriptions, SKU, canonical URL, categories, tags, descriptive attributes, and stable variation ID/SKU/options.
- Publish/catalog visibility policy.
- Deterministic product source version/hash and change detection.
- Product deletion/unpublish disappearance from source output for later M07 reconciliation.
- Optional-safe bootstrap registration when WooCommerce is inactive.
- Real WordPress/WooCommerce smoke coverage.

## Out of Scope
- Live/current price, stock, sale state, variation availability, discounts, cart/order/customer state.
- Cart/order actions and live commerce UI (M18).
- Chunking/incremental index reconciliation implementation (M07).
- Embedding/vector runtime (M08).
- Background queue orchestration (M09).

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
- [ ] Task 2 — Optional-safe native WooCommerce catalog gateway.
- [ ] Task 3 — WooCommerce product source canonical mapping.
- [ ] Task 4 — Stable source version and live-state exclusion regressions.
- [ ] Task 5 — Knowledge bootstrap registration and disabled-WooCommerce safety.
- [ ] Task 6 — Real WordPress/WooCommerce smoke coverage.
- [ ] Task 7 — Integration, compatibility/security/performance review, documentation, merge, and post-merge verification.

## Task 1 implementation
Task 1 introduced:
- `WooCommerceCatalogGateway` as a WooCommerce-independent application contract with availability, single-product lookup, and bounded product-ID paging methods.
- Immutable `WooCommerceProduct` snapshots for stable public catalog facts only.
- Immutable `WooCommerceVariation` snapshots for stable variation identity/SKU/options only.
- Positive identity, supported product type, publish/public catalog visibility, required product identity text, deterministic label/attribute/variation ordering, duplicate variation-ID rejection, and normalized attribute-name collision rejection.
- Explicit absence of live/current price, sale, stock, purchasability, cart/order, discount, customer, and arbitrary metadata fields from the snapshot boundary.

## TDD Evidence
### Task 1 primary RED
- Exact test-only SHA: `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`.
- CI: `33723839314`.
- PHPStan reached execution with no errors.
- PHPUnit: 229 tests / 1109 assertions / 7 failures.
- All seven failures were the expected absent `WooCommerceCatalogGateway`, `WooCommerceProduct`, and `WooCommerceVariation` contracts. Earlier WPCS-only test iterations were not counted as behavioral RED.

### Task 1 implementation
- Initial production-contract commit: `eb29bca8ee178c4542bf194821b5a4851c951c17` (`feat: add M06 WooCommerce catalog contracts`).
- Subsequent Task 1 corrections covered readonly normalization, public record documentation, duplicate variation identity, and hidden catalog visibility before final review.

### Independent review regression 1 — product attribute collision
Review found that distinct raw attribute names such as `" Size"` and `"Size"` could collapse after trimming and silently overwrite a stable catalog fact.
- RED SHA: `d647ebd31731a5c1cce0ea05d49733333ebda2c5`.
- CI: `33727034899`.
- PHPStan: no errors.
- PHPUnit: 233 tests / 1135 assertions / 1 failure; expected `InvalidArgumentException` was not thrown.
- GREEN SHA: `21447be4d4d498311b4a48630f1b227a76415237`.
- CI: `33727169180`.
- PHPStan: no errors.
- PHPUnit: 233 tests / 1135 assertions, all passed.
- Composer audit: no security vulnerability advisories.

### Independent review regression 2 — variation attribute collision
The same normalized-key overwrite risk existed for variation option names.
- RED SHA: `f547bcb6b25b2f52a9cf9e32041d384f3479c4f0`.
- CI: `33727270728`.
- PHPStan: no errors.
- PHPUnit: 234 tests / 1136 assertions / 1 failure; expected `InvalidArgumentException` was not thrown.
- GREEN SHA: `17358f11c2348e002786b82ffb2d534fee2e5ae1`.
- CI: `33727368805`.
- PHPStan: no errors.
- PHPUnit: 234 tests / 1136 assertions, all passed.
- Composer audit: no security vulnerability advisories.
- Permanent jobs: php-quality, js-quality, package, and wordpress-smoke all passed.
- Package artifact: `9882495514`, 706232 bytes, digest `sha256:d544d7ef3c124a9495149f50e645e2643f7f89208645e40db69a93701d6ef5d2`.

## Integration Test Evidence
Task 1 final exact-head CI `33727368805` passed existing WordPress activation, database, provider, WordPress knowledge, and file-ingestion smoke coverage. Real WooCommerce integration fixtures remain Task 6.

## Security Review
Task 1 bounded stable/live-data review result after fixes: **0 Critical / 0 Important unresolved findings**.

Verified boundaries:
- only stable descriptive catalog facts exist on product/variation snapshots;
- live price/stock/sale/cart/order/customer/discount fields are absent;
- publish/public catalog visibility fails closed at the snapshot boundary;
- duplicate/normalized-colliding variation and attribute identity does not silently overwrite facts;
- no native WooCommerce class is referenced by the application-facing gateway contract.

## Accessibility Review where UI exists
N/A — M06 introduces no UI.

## Performance Review where relevant
Task 1 is in-memory immutable normalization only. Bounded deterministic product paging, current-page hydration, and N+1 review are Task 2 responsibilities. Default page size remains 100 with hard maximum 250 in the approved plan.

## Code Review Findings
- Planning review: 0 Critical / 0 Important.
- Task 1 independent bounded review initially found 0 Critical / 1 Important: normalized attribute-name collisions could silently lose stable product/variation facts.
- Both product and variation collision paths were fixed through separate RED -> GREEN regression cycles.
- Final Task 1 review: **0 Critical / 0 Important unresolved**.

## Fresh Verification Commands
Authoritative dependency-backed verification remains GitHub Actions per ADR-018. Final Task 1 exact-head run: `33727368805` on `17358f11c2348e002786b82ffb2d534fee2e5ae1`.

## Known Limitations
One canonical document per product in M06. Variations are structured product metadata, not standalone documents. Live commerce facts are intentionally deferred to authorized runtime services/actions.

## Documentation Updated
M06 design/spec, executable implementation plan, milestone ledger, global status, and PR #8 track the durable milestone state.

## Completion Checklist
All Tasks 1–7, exact-head permanent CI, whole-milestone review with 0 unresolved Critical/Important findings, merge of exact tested SHA, and fresh post-merge `main` CI.

## Exact next unfinished action
Execute **Task 2 — Optional-safe native WooCommerce catalog gateway** with strict TDD. Add test-only coverage for WooCommerce-unavailable behavior, invalid page/per-page values, hard maximum 250, deterministic ascending product IDs, missing/deleted products, simple/variable mapping, visibility/password exclusion, and explicit live/private-data omission. Capture genuine behavioral RED before creating `NativeWooCommerceCatalogGateway`, then implement the minimum public-API adapter, run focused/full GREEN, perform privacy/performance review, and update durable evidence before Task 3.

## Next Milestone
M07 — Normalization, Chunking, Deduplication & Incremental Indexing.
