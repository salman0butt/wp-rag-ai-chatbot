# Global Status

- Completed milestones on `main`: **M00-M05**.
- Latest integrated milestone: **M05 — File/Document Ingestion**.
- Current milestone: **M06 — WooCommerce Knowledge Ingestion — IN PROGRESS (planning complete; Task 1 next)**.
- M05 merge: `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- Current recovered `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
- Latest `main` CI: `33721644922` — all permanent jobs passed.

## M06 durable state
- Feature branch: `feat/m06-woocommerce-knowledge-ingestion`.
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Tasks 1–7 are planned; no production M06 behavior has been implemented yet.
- Selected architecture: optional-safe WooCommerce catalog gateway -> immutable allowlisted stable product snapshots -> `WooCommerceProductSource` -> existing canonical `DocumentRecord`.
- Stable indexed facts: title/descriptions/SKU/product URL/categories/tags/descriptive attributes/stable variation ID/SKU/options.
- Explicitly live/non-indexed: current price, sale state, stock/availability, cart/order state, discounts, customer-specific values, and arbitrary product/post metadata.
- One document per product in M06; variations remain structured metadata.
- Planning self-review: **0 Critical / 0 Important** findings.

## M05 durable completion evidence
- Tasks 1–7 complete and integrated.
- Final feature head: `7b360ec11eeeafe5ccbd9b3036695e489b038178`.
- Exact-head CI: `33720708730` — all permanent jobs green.
- Merge commit: `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6`.
- Fresh post-merge CI: `33721014064` — all four permanent jobs green.
- Whole-milestone security/performance review: **0 Critical / 0 Important** unresolved findings.
- Detailed evidence: `docs/milestones/M05-file-document-ingestion.md` and `docs/progress/M05-CLOSEOUT.md`.

## Current gates
- `main` is healthy; no open PR existed at M06 branch creation.
- No unresolved Critical/Important review finding was recovered.
- M06 planning gate is complete; implementation must now use strict TDD.
- WooCommerce must remain optional and disabled-WooCommerce behavior must be verified permanently in M06 smoke coverage.

## Exact next unfinished action
Execute **M06 Task 1 — WooCommerce catalog contracts and immutable snapshots**. Add test-only coverage for `WooCommerceCatalogGateway`, `WooCommerceProduct`, and `WooCommerceVariation` covering positive identity, invariant rejection, deterministic category/tag/attribute/variation ordering, and the deliberate absence of price/stock/customer/live fields. Push the test-only SHA and capture genuine behavioral RED from GitHub Actions before creating production classes. Then implement the minimum contracts, require focused/full GREEN, perform an independent stable/live-data boundary review, and update durable evidence before Task 2.

## Previous milestone closeout
- M04 merge: `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`.
- M04 post-merge CI: `33688297306`.
