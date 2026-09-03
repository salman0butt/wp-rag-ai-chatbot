# M06 — WooCommerce Knowledge Ingestion

Status: IN PROGRESS — design/spec and implementation plan auto-approved; Tasks 1–5 complete; Task 6 implementation/exact-SHA CI green with independent fresh-session review pending.

## Goal
Normalize stable public WooCommerce catalog knowledge while clearly separating indexed descriptive facts from live transactional state.

## Dependencies
M04; M05 patterns; WooCommerce optional environment.

## Design / plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md`.
- Status: `AUTO-APPROVED — SCHEDULED MODE` after repository-mandated self-review.
- Task 4 clarification: generic WooCommerce modified-time churn is observational only and excluded from M06 source version/hash because it cannot distinguish stable descriptive changes from excluded live-state changes.

## Architecture
`NativeWooCommerceCatalogGateway` -> immutable allowlisted `WooCommerceProduct` snapshots -> `WooCommerceProductSource` -> canonical `DocumentRecord`.

WooCommerce remains optional. Stable descriptive product facts are indexable. Current price, sale state, stock/variation availability, cart/order state, discounts, customer-specific values, arbitrary metadata, and generic modified-marker-only churn are excluded from indexed documents and source versioning.

Whole-catalog traversal uses bounded deterministic published-candidate pages. Eligibility remains fail-closed in `product()`, and duplicate candidate cardinality is preserved until source-level duplicate suppression.

## Acceptance Criteria
- Simple and variable products normalize correctly.
- Exact SKU/category/tag/attribute/variation metadata is preserved deterministically.
- Public visibility/status policy fails closed.
- Descriptive catalog updates change source version/hash as appropriate.
- Price/stock-only changes, including generic modified-marker churn, do not change M06 source version/hash.
- Removed/unpublished products disappear from source output.
- Customer/order/private/arbitrary metadata is never indexed by default.
- Disabled WooCommerce does not break plugin activation/bootstrap and yields no product documents.
- Whole-catalog enumeration is bounded, deterministic, and reviewed for N+1 behavior.

## Tasks
- [x] Task 1 — WooCommerce catalog contracts and immutable snapshots.
- [x] Task 2 — Optional-safe native WooCommerce catalog gateway.
- [x] Task 3 — WooCommerce product source canonical mapping.
- [x] Task 4 — Stable source version and live-state exclusion regressions.
- [x] Task 5 — Knowledge bootstrap registration and disabled-WooCommerce safety.
- [ ] Task 6 — Real WordPress/WooCommerce smoke coverage. **Implementation + exact-code-head CI green; independent fresh-session review pending.**
- [ ] Task 7 — Integration, compatibility/security/performance review, documentation, merge, and post-merge verification.

## Task 1 durable evidence — COMPLETE
- Primary behavioral RED: `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`, CI `33723839314`.
- Independent review found 0 Critical / 1 Important normalized-attribute collision issue; product/variation regressions were fixed through RED/GREEN.
- Final reviewed GREEN: `17358f11c2348e002786b82ffb2d534fee2e5ae1`, CI `33727368805` — PHPStan clean; PHPUnit 234 / 1136; Composer audit clean; all permanent jobs green.
- Artifact `9882495514`, digest `sha256:d544d7ef3c124a9495149f50e645e2643f7f89208645e40db69a93701d6ef5d2`.
- Final review: **0 Critical / 0 Important unresolved**.

## Task 2 durable evidence — COMPLETE
- Availability/enumeration RED: `37334fbc03c593be038e29c44c2d5a416c95a756`, CI `33728200033`.
- Stable simple/variable product mapping and optional runtime gateway were implemented through successive RED/GREEN cycles.
- Fresh-session GMT regression: RED `a4cc2d5c09332819fe65cef2f0c78854b8f8db55`, GREEN `d14f657a59ba12f11231331551c093b4a84df8ba`, CI `33734311953`.
- Final acceptance head `626adf8b45cce1dd6a02356147b20994b64f4074`, CI `33734709484` — PHPStan clean; PHPUnit 243 / 1182; all permanent jobs green.
- Artifact `9885271471`, digest `sha256:357b73583f7d9617231ebd4f233cfde8645d38fcb59e1e9ce786bd7818d05edf`.
- Final review: **0 Critical / 0 Important unresolved**.

## Task 3 durable evidence — COMPLETE
- Primary behavioral RED: `22a6935c32abb6083ac574430b647431ca724032`, CI `33736321322`.
- Fail-closed config regression: RED `c9a9c7c32453c8226024e3948d0beb0f72dc875e`, corrected GREEN culminated at `2fbad738dc8b96ec89795805666d82b8a398e520`, CI `33737351181`.
- Independent review `5100102218` found 0 Critical / 1 Important catalog-page exhaustion risk.
- Pagination/cardinality RED `028e5247acf4ced12f4e347a71bdc4e5f5ab8fc1`, CI `33747195823`; minimal GREEN `2931ecf3ea9cf5f946e75e0228a628e4430a28db`, CI `33747391688` — PHPUnit 253 / 1219 and all permanent jobs green.
- Artifact `9890174635`, digest `sha256:ab851a0f6b7f3c7a573efd2ce099061b755eab883bbfd0d8dc1d8784a95ee6b6`.
- Final fresh-session review `5101080712`: **0 Critical / 0 Important unresolved**.

## Task 4 durable evidence — COMPLETE
- Descriptive-change RED `f145303afee722c9c333408c2947059ac1b83246`, CI `33749130132`.
- Generic-modified-marker exclusion RED `73cf87f085682dc1ee843cc0548915ee7b83b9c3`, CI `33749715650`.
- Minimal stable-version correction `595c9e7c483e42914c901e08afec9b8db935d9d1`, CI `33749868280` — PHPUnit 256 / 1244 and all permanent jobs green.
- Artifact `9891098806`, digest `sha256:8cb875edfe65f2a7e10f70d40d37d7d17efd5b394efdce04579e6a6429581a74`.
- Independent review `5101591315` found 0 Critical / 1 Important stale-plan contradiction; plan wording fixed at `fd1a68cdb25437a8c20bd223f6e56d571b8c1c0a`, CI `33752938100`.
- Final independent re-review `5101636297`: **0 Critical / 0 Important unresolved**.

## Task 5 durable evidence — COMPLETE
Task 5 registers `woocommerce_product` in the native knowledge registry without invoking WooCommerce APIs during bootstrap.

- Genuine behavioral RED: `f3db0af57bb108b80872c5ec65852d393d9036d1`, CI `33753332250` — PHPStan clean; PHPUnit 256 / 1243 / 1 expected failure because `woocommerce_product` was absent.
- Minimal GREEN: `50c3ea2292a9bc5d768416ab3c9793f6e59b1ab9`.
- Exact-code-head GREEN CI `33753477407` — PHPStan clean; PHPUnit 256 / 1245; Composer audit clean; all permanent jobs green.
- Artifact `9892483643`, 711954 bytes, digest `sha256:a93ae167ca7db67ef43007ef7b754633708567cfc6c72bc2788a6c1e689a8f4c`.
- Same-session review `5101700445`: 0 Critical / 0 Important, explicitly not independent.
- Independent fresh-session review `5102077891`: **0 Critical / 0 Important unresolved**. Verified optional-WooCommerce bootstrap safety, native registry stability, extension ordering/collision semantics, preservation of existing sources, and Task 6 scope.

## Task 6 durable evidence — independent review pending
Task 6 adds permanent real WordPress/WooCommerce smoke coverage without adding WooCommerce as a production dependency.

### Wiring RED
- Test-only wiring commit: `3a90bd32c8e5aa51b8ea9afded6b8e790214ca49`.
- CI `33757505975`: php-quality, js-quality, and package passed; existing WordPress smoke steps passed; `wordpress-smoke` failed only at the new `npm run test:wp:woocommerce-knowledge` step because `scripts/test-wp-woocommerce-knowledge.sh` intentionally did not yet exist. This is the planned integration-wiring RED.

### GREEN implementation
- Implementation: `c0746fbe7f526746af10c3f3367259b59940b3ad`.
- Smoke environment pins WooCommerce `11.0.1`; WooCommerce is installed/activated only inside the CI WordPress environment.
- Real public API fixtures cover a published simple product, a published variable product plus variation, SKU, category/tag, descriptive attributes, descriptions, current price/stock, and private metadata.
- Assertions prove canonical simple/variable normalization, deterministic repeated reads, variation identity, descriptive update version/hash changes, price/stock-only version/hash stability, private/live-state exclusion, unpublish removal, and cleanup.
- The shell then deactivates WooCommerce and reruns the existing real WordPress knowledge smoke, proving plugin/bootstrap/non-commerce knowledge remains healthy with WooCommerce disabled.
- Exact-code-head GREEN CI `33757923399`: **php-quality, js-quality, package, wordpress-smoke all passed**, including the new enabled/disabled WooCommerce smoke.
- Artifact `9894229746`, 711986 bytes, digest `sha256:442aa7089e7577bc690a58b6769d419e39194df969ef8fa80b9c1d0eb003bf36`.
- Same-session review `5102199357`: **0 Critical / 0 Important unresolved in implementation/test wiring**, explicitly **not independent**.
- Required independent fresh-session Task 6 review: **PENDING**. Task 7 must not start until this reports 0 Critical / 0 Important unresolved and any material findings are fixed through regression TDD.

## Security/privacy/performance review status
- Tasks 1–5: complete with 0 Critical / 0 Important unresolved.
- Task 6 same-session review: 0 Critical / 0 Important; independent gate pending.
- Stable allowlist excludes current price, stock, discounts, orders, customer data, arbitrary product/post metadata, and generic modified-marker-only churn.
- Whole-catalog work remains bounded page-at-a-time; Task 6 adds no production query path.

## Accessibility review
N/A — M06 Tasks 1–6 introduce no UI.

## Known limitations
One canonical document per product in M06. Variations are structured product metadata, not standalone documents. Live commerce actions remain deferred to later authorized runtime milestones. M07 owns stale-index reconciliation/incremental indexing; M09 owns background execution; M18 owns live commerce actions.

## Completion checklist
M06 is not complete until Task 6 independent review, Task 7 whole-milestone review, exact-final-SHA permanent CI, merge, and fresh post-merge `main` CI all pass.

## Exact next unfinished action
Perform a **genuinely independent fresh-session Task 6 review** from predecessor `68ad503fcb9d5d1f23ea3ba623aed046f7e4fbd0` through the final Task 6 code/docs head. Review WooCommerce version/install isolation, public-API fixture realism, simple/variable/variation normalization, stable-vs-live hash assertions, private-data exclusion, unpublish behavior, cleanup/idempotence, disabled-WooCommerce fallback, permanent CI wiring, and Task 7 scope. Fix every Critical/Important behavioral finding through strict regression TDD, require exact-head GREEN CI, and record final 0 Critical / 0 Important unresolved before beginning Task 7.

## Next Milestone
M07 — Normalization, Chunking, Deduplication & Incremental Indexing.
