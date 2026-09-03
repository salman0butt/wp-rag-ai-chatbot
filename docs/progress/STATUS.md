# Global Status

- Completed milestones on `main`: **M00-M05**.
- Latest integrated milestone: **M05 — File/Document Ingestion**.
- Current milestone: **M06 — WooCommerce Knowledge Ingestion — IN PROGRESS (Tasks 1–2 complete; Task 3 next)**.
- M05 merge: `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- Current recovered `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
- Latest verified `main` CI: `33721644922` — all permanent jobs passed.

## M06 durable state
- Feature branch: `feat/m06-woocommerce-knowledge-ingestion`.
- Draft PR: #8 — `feat: build M06 WooCommerce knowledge ingestion`.
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Task 1 — WooCommerce catalog contracts and immutable snapshots: **COMPLETE**.
- Task 2 — Optional-safe native WooCommerce catalog gateway: **COMPLETE**.
- Tasks 3–7 remain.
- Architecture: optional-safe WooCommerce catalog gateway -> immutable allowlisted stable product snapshots -> `WooCommerceProductSource` -> canonical `DocumentRecord`.
- Stable catalog facts only: status/visibility/title/descriptions/SKU/product URL/categories/tags/descriptive attributes/stable variation ID/SKU/options/modified marker.
- Explicitly excluded: current price, sale state, stock/availability, cart/order state, discounts, customer-specific values, and arbitrary product/post metadata.

### Task 1 evidence
- Primary behavioral RED: `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`, CI `33723839314` — PHPStan clean; PHPUnit 229 / 1109 / 7 expected failures.
- Initial production implementation: `eb29bca8ee178c4542bf194821b5a4851c951c17`.
- Independent review found 0 Critical / 1 Important normalized-attribute collision issue; product and variation paths were fixed through separate RED/GREEN regressions.
- Final reviewed GREEN: `17358f11c2348e002786b82ffb2d534fee2e5ae1`, CI `33727368805` — all permanent jobs green; PHPUnit 234 / 1136; Composer audit clean.
- Artifact: `9882495514`, digest `sha256:d544d7ef3c124a9495149f50e645e2643f7f89208645e40db69a93701d6ef5d2`.
- Final Task 1 review: **0 Critical / 0 Important unresolved**.

### Task 2 evidence
- Availability/enumeration behavioral RED: `37334fbc03c593be038e29c44c2d5a416c95a756`, CI `33728200033` — PHPStan clean; PHPUnit 239 / 1141 / 5 expected failures.
- Initial bounded enumeration implementation: `1ae33a08093594cd9ec020a6663c47fc112bc9ce`; optional callable/static-analysis corrections culminated at `eb021d95a7773646877a20dc693a16ccfb866d10`, CI `33731515604` all permanent jobs green.
- Simple-product standards-clean RED: `1e82721ff07b114796a24aeb0b901aab264adcf5`, CI `33732021036` — PHPStan clean; PHPUnit 240 / 1153 / 1 expected failure; implementation `fc99fa6a551c947674f5297dc8e41abd7b63311f`.
- Variable-product RED lineage: `82ff9ea6edffc2c219f49b878e98aba75023e69e` -> `2c9c1be2a4ff2e6186aae6b63e7fb67831da8acd`, CI `33732847268`; stable variation implementation `22740eea50b75c48cf35f83a136b4bfbdeedcc26`.
- Separate fresh-session regression found 0 Critical / 1 Important: `modifiedGmt` preserved a site offset. RED `a4cc2d5c09332819fe65cef2f0c78854b8f8db55`, CI `33733459102` — PHPUnit 242 / 1176 / 1 failure; GREEN `d14f657a59ba12f11231331551c093b4a84df8ba`, CI `33734311953` all permanent jobs green.
- Final acceptance coverage: `626adf8b45cce1dd6a02356147b20994b64f4074`; CI `33734709484` — all permanent jobs green; PHPStan clean; PHPUnit **243 / 1182**; Composer audit clean.
- Final Task 2 artifact: `9885271471`, 708841 bytes, digest `sha256:357b73583f7d9617231ebd4f233cfde8645d38fcb59e1e9ce786bd7818d05edf`.
- Final Task 2 privacy/performance review: **0 Critical / 0 Important unresolved**. Product-page hydration is bounded to maximum 250 and intentionally enforces fail-closed type/visibility/password eligibility; no arbitrary metadata/private stores/live commerce state are read.
- Detailed evidence: `docs/milestones/M06-woocommerce-knowledge-ingestion.md`.

## Current gates
- `main` remains healthy at the recovered SHA.
- PR #8 remains draft because M06 Tasks 3–7 are unfinished.
- M06 Tasks 1–2 are implemented, reviewed, and backed by exact-head GREEN CI evidence at their final code/test SHA.
- WooCommerce remains optional; real enabled/disabled WooCommerce integration smoke is still Task 6.
- No merge is permitted until the entire milestone satisfies exact-final-SHA CI/review/documentation gates.

## Exact next unfinished action
Execute **M06 Task 3 — WooCommerce product source canonical mapping** with strict TDD. Add a test-only RED for disabled gateway behavior, explicit product IDs, bounded whole-catalog paging, malformed configuration, stable `woocommerce_product:{id}` identity, public visibility/title/URL, deterministic readable content, and allowlisted metadata; implement the minimum `WooCommerceProductSource`, require exact-head GREEN, perform deterministic-ordering/privacy review, and update durable evidence before Task 4.

## Previous milestone closeout
- M05 final feature head: `7b360ec11eeeafe5ccbd9b3036695e489b038178`.
- M05 exact-head CI: `33720708730` — all permanent jobs green.
- M05 merge: `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6`.
- M05 post-merge CI: `33721014064` — all permanent jobs green.
- M04 merge: `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`; post-merge CI `33688297306`.
