# M06 — WooCommerce Knowledge Ingestion

Status: IN PROGRESS — design/spec and implementation plan auto-approved; Task 1 next.

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
- [ ] Task 1 — WooCommerce catalog contracts and immutable snapshots.
- [ ] Task 2 — Optional-safe native WooCommerce catalog gateway.
- [ ] Task 3 — WooCommerce product source canonical mapping.
- [ ] Task 4 — Stable source version and live-state exclusion regressions.
- [ ] Task 5 — Knowledge bootstrap registration and disabled-WooCommerce safety.
- [ ] Task 6 — Real WordPress/WooCommerce smoke coverage.
- [ ] Task 7 — Integration, compatibility/security/performance review, documentation, merge, and post-merge verification.

## TDD Evidence
Pending implementation. Task 1 must begin with a genuine failing contract/invariant test on the feature branch before production classes are added.

## Integration Test Evidence
Pending Task 6. Real WooCommerce fixtures and both enabled/disabled plugin states are required.

## Security Review
Required: public product visibility; arbitrary-meta allowlist; customer/order/session/private-data exclusion; dynamic/live state separation; path-safe/sanitized errors.

## Accessibility Review where UI exists
N/A — M06 introduces no UI.

## Performance Review where relevant
Required: bounded deterministic product ID paging, current-page hydration only, and N+1 review. Default page size 100; hard maximum 250.

## Code Review Findings
Planning review: 0 Critical / 0 Important. Implementation reviews pending.

## Fresh Verification Commands
Implementation pending; authoritative dependency-backed execution remains GitHub Actions per ADR-018.

## Commits
Planning commit pending exact SHA at time of this document update.

## Known Limitations
One canonical document per product in M06. Variations are structured product metadata, not standalone documents. Live commerce facts are intentionally deferred to authorized runtime services/actions.

## Documentation Updated
M06 design/spec, executable implementation plan, milestone ledger, and global status.

## Completion Checklist
All Tasks 1–7, exact-head permanent CI, whole-milestone review with 0 unresolved Critical/Important findings, merge of exact tested SHA, and fresh post-merge `main` CI.

## Exact next unfinished action
Execute Task 1 with strict TDD: add failing tests for `WooCommerceCatalogGateway`, immutable `WooCommerceProduct`/`WooCommerceVariation` contracts, stable-only fields, identity/invariant validation, and deterministic ordering; capture genuine RED before production code.

## Next Milestone
M07 — Normalization, Chunking, Deduplication & Incremental Indexing.
