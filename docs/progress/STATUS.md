# Global Status

- Completed milestones on `main`: **M00-M05**.
- Latest integrated milestone: **M05 — File/Document Ingestion**.
- Current milestone: **M06 — WooCommerce Knowledge Ingestion — IN PROGRESS (Tasks 1–5 complete; Task 6 implementation/exact-SHA CI green, independent fresh-session review pending)**.
- Current recovered `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
- Latest verified `main` CI from recovered state: `33721644922` — all permanent jobs passed.

## M06 durable state
- Feature branch: `feat/m06-woocommerce-knowledge-ingestion`.
- Draft PR: #8 — `feat: build M06 WooCommerce knowledge ingestion`.
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Task 1 — WooCommerce catalog contracts and immutable snapshots: **COMPLETE**.
- Task 2 — Optional-safe native WooCommerce catalog gateway: **COMPLETE**.
- Task 3 — WooCommerce product source canonical mapping: **COMPLETE**.
- Task 4 — Stable source version and live-state exclusion regressions: **COMPLETE**.
- Task 5 — Knowledge bootstrap registration and disabled-WooCommerce safety: **COMPLETE**.
- Task 6 — Real WordPress/WooCommerce smoke coverage: **IMPLEMENTED + EXACT-CODE-HEAD GREEN; INDEPENDENT FRESH-SESSION REVIEW PENDING**.
- Task 7 remains blocked until Task 6 independent review closes at 0 Critical / 0 Important unresolved.

### Architecture and boundaries
- Optional-safe WooCommerce catalog gateway -> immutable allowlisted stable product snapshots -> `WooCommerceProductSource` -> canonical `DocumentRecord`.
- Stable facts: status/visibility/title/descriptions/SKU/product URL/categories/tags/descriptive attributes/stable variation ID/SKU/options.
- Excluded from canonical knowledge/versioning: current price, sale state, stock/availability, cart/order state, discounts, customer-specific values, arbitrary product/post metadata, and generic modified-marker-only churn.
- Whole-catalog enumeration remains bounded and deterministic; Task 6 adds test-only integration work, not a new production query path.

### Tasks 1–4 evidence summary
- Task 1 primary RED `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`; final reviewed GREEN `17358f11c2348e002786b82ffb2d534fee2e5ae1`, CI `33727368805`; final review 0 Critical / 0 Important.
- Task 2 primary RED `37334fbc03c593be038e29c44c2d5a416c95a756`; GMT regression RED/GREEN `a4cc2d5c09332819fe65cef2f0c78854b8f8db55` -> `d14f657a59ba12f11231331551c093b4a84df8ba`; final acceptance `626adf8b45cce1dd6a02356147b20994b64f4074`, CI `33734709484`; final review 0 / 0.
- Task 3 primary RED `22a6935c32abb6083ac574430b647431ca724032`; pagination/cardinality RED `028e5247acf4ced12f4e347a71bdc4e5f5ab8fc1`; GREEN `2931ecf3ea9cf5f946e75e0228a628e4430a28db`, CI `33747391688`; final fresh review `5101080712` 0 / 0.
- Task 4 descriptive-change RED `f145303afee722c9c333408c2947059ac1b83246`; modified-marker exclusion RED `73cf87f085682dc1ee843cc0548915ee7b83b9c3`; GREEN `595c9e7c483e42914c901e08afec9b8db935d9d1`, CI `33749868280`; independent review finding fixed in plan at `fd1a68cdb25437a8c20bd223f6e56d571b8c1c0a`; final re-review `5101636297` 0 / 0.

### Task 5 evidence — COMPLETE
- Genuine behavioral RED: `f3db0af57bb108b80872c5ec65852d393d9036d1`, CI `33753332250` — PHPStan clean; PHPUnit 256 / 1243 / 1 expected failure because `woocommerce_product` was absent from the native registry.
- Minimal implementation: `50c3ea2292a9bc5d768416ab3c9793f6e59b1ab9` registers `WooCommerceProductSource( NativeWooCommerceCatalogGateway )` without invoking WooCommerce APIs during bootstrap.
- Exact-code-head GREEN CI: `33753477407` — PHPStan clean; PHPUnit 256 / 1245; Composer audit clean; php-quality, js-quality, package, and wordpress-smoke all passed.
- Artifact: `9892483643`, 711954 bytes, digest `sha256:a93ae167ca7db67ef43007ef7b754633708567cfc6c72bc2788a6c1e689a8f4c`.
- Same-session review `5101700445`: 0 Critical / 0 Important, explicitly not independent.
- Independent fresh-session review `5102077891`: **0 Critical / 0 Important unresolved**. Task 5 is complete.

### Task 6 evidence — independent review pending
- Planned wiring RED commit: `3a90bd32c8e5aa51b8ea9afded6b8e790214ca49`.
- CI `33757505975`: php-quality, js-quality, and package passed; all existing WordPress smoke steps passed; the new `test:wp:woocommerce-knowledge` step failed because its shell script intentionally did not yet exist. This is the required integration-wiring RED.
- Implementation: `c0746fbe7f526746af10c3f3367259b59940b3ad` adds `scripts/test-wp-woocommerce-knowledge.sh` and `.php`.
- CI smoke pins WooCommerce `11.0.1` and installs it only in the test WordPress environment; it is not a production Composer/npm dependency.
- Enabled WooCommerce smoke creates real simple and variable products plus a variation through public APIs, includes stable descriptive data plus live price/stock/private metadata fixtures, and proves only allowlisted stable facts reach canonical documents.
- Smoke proves repeated reads are deterministic, descriptive changes alter version/hash, price/stock-only changes do not, unpublish removes output, and cleanup succeeds.
- WooCommerce is then deactivated and the existing real WordPress knowledge smoke reruns successfully, proving disabled-WooCommerce bootstrap/non-commerce safety.
- Exact-code-head GREEN CI `33757923399`: **php-quality, js-quality, package, wordpress-smoke all passed**.
- Artifact: `9894229746`, 711986 bytes, digest `sha256:442aa7089e7577bc690a58b6769d419e39194df969ef8fa80b9c1d0eb003bf36`.
- Same-session Task 6 review `5102199357`: **0 Critical / 0 Important unresolved in implementation/test wiring**, explicitly **not independent**.
- Required independent fresh-session Task 6 review: **PENDING**. This gate blocks Task 7.

## Current gates
- `main` remains healthy at `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e` based on recovered CI evidence.
- PR #8 remains draft because M06 Tasks 6–7 are unfinished.
- M06 Tasks 1–5 are complete with independent review and exact-head GREEN CI evidence.
- Task 6 has genuine wiring RED, implementation, exact-code-head GREEN CI, enabled/disabled WooCommerce smoke, artifact evidence, and same-session review, but cannot be marked complete until a genuinely independent fresh-session review reports **0 Critical / 0 Important unresolved**.
- No merge is permitted until Task 7 whole-milestone review, exact-final-SHA permanent CI, documentation reconciliation, PR gates, merge, and fresh post-merge `main` CI all pass.

## Exact next unfinished action
Perform a **genuinely independent fresh-session review of M06 Task 6** from predecessor `68ad503fcb9d5d1f23ea3ba623aed046f7e4fbd0` through the final Task 6 code/docs head. Focus on WooCommerce version/install isolation, public-API fixture realism, simple/variable/variation normalization, stable-vs-live version/hash assertions, private-data exclusion, unpublish behavior, cleanup/idempotence, disabled-WooCommerce fallback, permanent CI wiring, and Task 7 scope. Fix every Critical/Important behavioral finding through strict regression TDD, require exact-head GREEN CI, then mark Task 6 complete and begin Task 7.

## Previous milestone closeout
- M05 final feature head: `7b360ec11eeeafe5ccbd9b3036695e489b038178`.
- M05 exact-head CI: `33720708730` — all permanent jobs green.
- M05 merge: `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- M05 post-merge CI: `33721014064` — all permanent jobs green.
- M05 durable finalization on `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
