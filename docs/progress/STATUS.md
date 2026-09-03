# Global Status

- Completed milestones on `main`: **M00-M05**.
- Latest integrated milestone: **M05 — File/Document Ingestion**.
- Current milestone: **M06 — WooCommerce Knowledge Ingestion — IN PROGRESS (Task 1 complete; Task 2 next)**.
- M05 merge: `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- Current recovered `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
- Latest `main` CI: `33721644922` — all permanent jobs passed.

## M06 durable state
- Feature branch: `feat/m06-woocommerce-knowledge-ingestion`.
- Draft PR: #8 — `feat: build M06 WooCommerce knowledge ingestion`.
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Task 1 — WooCommerce catalog contracts and immutable snapshots: **COMPLETE**.
- Tasks 2–7 remain.
- Selected architecture: optional-safe WooCommerce catalog gateway -> immutable allowlisted stable product snapshots -> `WooCommerceProductSource` -> existing canonical `DocumentRecord`.
- Stable indexed facts: title/descriptions/SKU/product URL/categories/tags/descriptive attributes/stable variation ID/SKU/options.
- Explicitly live/non-indexed: current price, sale state, stock/availability, cart/order state, discounts, customer-specific values, and arbitrary product/post metadata.
- One document per product in M06; variations remain structured metadata.

### Task 1 evidence
- Primary behavioral RED SHA: `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`.
- RED CI: `33723839314` — PHPStan clean; PHPUnit 229 tests / 1109 assertions / 7 expected failures for absent M06 contracts.
- Initial production contract commit: `eb29bca8ee178c4542bf194821b5a4851c951c17`.
- Independent bounded review found **0 Critical / 1 Important**: normalized attribute-name collisions could silently overwrite stable product/variation catalog facts.
- Product-collision RED: `d647ebd31731a5c1cce0ea05d49733333ebda2c5`, CI `33727034899` — PHPStan clean; PHPUnit 233 / 1135 / 1 expected failure.
- Product-collision GREEN: `21447be4d4d498311b4a48630f1b227a76415237`, CI `33727169180` — PHPStan clean; PHPUnit 233 / 1135; Composer audit clean.
- Variation-collision RED: `f547bcb6b25b2f52a9cf9e32041d384f3479c4f0`, CI `33727270728` — PHPStan clean; PHPUnit 234 / 1136 / 1 expected failure.
- Final reviewed GREEN SHA: `17358f11c2348e002786b82ffb2d534fee2e5ae1`.
- Final CI: `33727368805` — php-quality, js-quality, package, wordpress-smoke all passed; PHPStan clean; PHPUnit 234 / 1136; Composer audit clean.
- Artifact: `9882495514`, 706232 bytes, digest `sha256:d544d7ef3c124a9495149f50e645e2643f7f89208645e40db69a93701d6ef5d2`.
- Final Task 1 review: **0 Critical / 0 Important unresolved**.
- Detailed evidence: `docs/milestones/M06-woocommerce-knowledge-ingestion.md`.

## M05 durable completion evidence
- Tasks 1–7 complete and integrated.
- Final feature head: `7b360ec11eeeafe5ccbd9b3036695e489b038178`.
- Exact-head CI: `33720708730` — all permanent jobs green.
- Merge commit: `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6`.
- Fresh post-merge CI: `33721014064` — all four permanent jobs green.
- Whole-milestone security/performance review: **0 Critical / 0 Important** unresolved findings.
- Detailed evidence: `docs/milestones/M05-file-document-ingestion.md` and `docs/progress/M05-CLOSEOUT.md`.

## Current gates
- `main` is healthy.
- PR #8 remains draft because M06 Tasks 2–7 are unfinished.
- M06 Task 1 is exact-head verified and has 0 unresolved Critical/Important review findings.
- WooCommerce remains optional; native adapter behavior has not been implemented yet.
- Real enabled/disabled WooCommerce integration smoke remains Task 6.

## Exact next unfinished action
Execute **M06 Task 2 — Optional-safe native WooCommerce catalog gateway** with strict TDD. Add test-only coverage for WooCommerce unavailable behavior (`isAvailable() === false`, empty ID enumeration, product lookup `null`), invalid page/per-page values, hard maximum 250, deterministic ascending product IDs, missing/deleted products, simple and variable product normalization, public visibility/password filtering, and explicit exclusion of price/stock/customer/private metadata. Capture genuine behavioral RED before creating `NativeWooCommerceCatalogGateway`, then implement the minimum public-API adapter, require focused/full GREEN, perform privacy/performance review, and update durable evidence before Task 3.

## Previous milestone closeout
- M04 merge: `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`.
- M04 post-merge CI: `33688297306`.
