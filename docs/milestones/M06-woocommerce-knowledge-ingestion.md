# M06 — WooCommerce Knowledge Ingestion

Status: INTEGRATION READY — Tasks 1–6 complete and independently reviewed; whole-milestone review complete; Task 7 CI-installer correction is exact-SHA green with fresh-session independent review pending before merge.

## Goal
Normalize stable public WooCommerce catalog knowledge while clearly separating indexed descriptive facts from live transactional state.

## Dependencies
M04; M05 patterns; WooCommerce optional environment.

## Design / plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md`.
- Both are `AUTO-APPROVED — SCHEDULED MODE` after repository-mandated self-review.
- Task 4 clarification: generic WooCommerce modified-time churn is observational only and excluded from M06 source version/hash because it cannot distinguish descriptive changes from excluded live-state changes.

## Architecture
`NativeWooCommerceCatalogGateway` -> immutable allowlisted `WooCommerceProduct` snapshots -> `WooCommerceProductSource` -> canonical `DocumentRecord`.

WooCommerce remains optional. Stable descriptive product facts are indexable. Current price, sale state, stock/variation availability, cart/order state, discounts, customer-specific values, arbitrary metadata, and generic modified-marker-only churn are excluded from indexed documents and source versioning.

Whole-catalog traversal uses bounded deterministic published-candidate pages. Eligibility remains fail-closed in `product()`, and duplicate candidate cardinality is preserved until source-level duplicate suppression.

## Acceptance criteria — VERIFIED
- Simple and variable products normalize correctly.
- Exact SKU/category/tag/attribute/variation metadata is preserved deterministically.
- Public visibility/status/password policy fails closed.
- Descriptive catalog updates change source version/hash.
- Price/stock-only changes and generic modified-marker churn do not change M06 source version/hash.
- Removed/unpublished products disappear from source output.
- Customer/order/private/arbitrary metadata is never indexed by default.
- Disabled WooCommerce does not break activation/bootstrap and yields no product documents.
- Whole-catalog enumeration is bounded, deterministic, and reviewed for N+1 behavior.

## Tasks
- [x] Task 1 — WooCommerce catalog contracts and immutable snapshots.
- [x] Task 2 — Optional-safe native WooCommerce catalog gateway.
- [x] Task 3 — WooCommerce product source canonical mapping.
- [x] Task 4 — Stable source version and live-state exclusion regressions.
- [x] Task 5 — Knowledge bootstrap registration and disabled-WooCommerce safety.
- [x] Task 6 — Real WordPress/WooCommerce smoke coverage.
- [ ] Task 7 — Fresh-session review of CI-installer correction, final exact-SHA CI, PR finishing, merge, post-merge verification, and final durable closeout.

## Durable TDD / review evidence

### Task 1 — COMPLETE
- Primary behavioral RED `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`, CI `33723839314`.
- Independent review found an Important normalized-attribute collision issue; product/variation regressions were fixed through RED/GREEN.
- Final reviewed GREEN `17358f11c2348e002786b82ffb2d534fee2e5ae1`, CI `33727368805` — PHPStan clean; PHPUnit 234 / 1136; Composer audit clean; all permanent jobs green.
- Artifact `9882495514`, digest `sha256:d544d7ef3c124a9495149f50e645e2643f7f89208645e40db69a93701d6ef5d2`.
- Final review: **0 Critical / 0 Important unresolved**.

### Task 2 — COMPLETE
- Availability/enumeration RED `37334fbc03c593be038e29c44c2d5a416c95a756`, CI `33728200033`.
- Fresh-session GMT regression RED `a4cc2d5c09332819fe65cef2f0c78854b8f8db55`; GREEN `d14f657a59ba12f11231331551c093b4a84df8ba`, CI `33734311953`.
- Final acceptance head `626adf8b45cce1dd6a02356147b20994b64f4074`, CI `33734709484` — PHPStan clean; PHPUnit 243 / 1182; all permanent jobs green.
- Artifact `9885271471`, digest `sha256:357b73583f7d9617231ebd4f233cfde8645d38fcb59e1e9ce786bd7818d05edf`.
- Final review: **0 Critical / 0 Important unresolved**.

### Task 3 — COMPLETE
- Primary behavioral RED `22a6935c32abb6083ac574430b647431ca724032`, CI `33736321322`.
- Fail-closed config RED `c9a9c7c32453c8226024e3948d0beb0f72dc875e`.
- Independent review found an Important premature catalog-page exhaustion risk.
- Pagination/cardinality RED `028e5247acf4ced12f4e347a71bdc4e5f5ab8fc1`, CI `33747195823`; GREEN `2931ecf3ea9cf5f946e75e0228a628e4430a28db`, CI `33747391688` — PHPUnit 253 / 1219; all permanent jobs green.
- Artifact `9890174635`, digest `sha256:ab851a0f6b7f3c7a573efd2ce099061b755eab883bbfd0d8dc1d8784a95ee6b6`.
- Final fresh-session review: **0 Critical / 0 Important unresolved**.

### Task 4 — COMPLETE
- Descriptive-change RED `f145303afee722c9c333408c2947059ac1b83246`, CI `33749130132`.
- Generic-modified-marker exclusion RED `73cf87f085682dc1ee843cc0548915ee7b83b9c3`, CI `33749715650`.
- GREEN `595c9e7c483e42914c901e08afec9b8db935d9d1`, CI `33749868280` — PHPUnit 256 / 1244; all permanent jobs green.
- Artifact `9891098806`, digest `sha256:8cb875edfe65f2a7e10f70d40d37d7d17efd5b394efdce04579e6a6429581a74`.
- Independent review found an Important stale-plan contradiction; plan fixed at `fd1a68cdb25437a8c20bd223f6e56d571b8c1c0a`, CI `33752938100`.
- Final re-review: **0 Critical / 0 Important unresolved**.

### Task 5 — COMPLETE
- Behavioral RED `f3db0af57bb108b80872c5ec65852d393d9036d1`, CI `33753332250` — PHPUnit 256 / 1243 / 1 expected failure because `woocommerce_product` was absent.
- GREEN `50c3ea2292a9bc5d768416ab3c9793f6e59b1ab9`.
- Exact-code-head CI `33753477407` — PHPStan clean; PHPUnit 256 / 1245; Composer audit clean; all permanent jobs green.
- Artifact `9892483643`, digest `sha256:a93ae167ca7db67ef43007ef7b754633708567cfc6c72bc2788a6c1e689a8f4c`.
- Independent fresh-session review: **0 Critical / 0 Important unresolved**.

### Task 6 — COMPLETE
- Wiring RED `3a90bd32c8e5aa51b8ea9afded6b8e790214ca49`, CI `33757505975`: all prior permanent/smoke gates passed and the new WooCommerce smoke failed only because `scripts/test-wp-woocommerce-knowledge.sh` intentionally did not exist.
- GREEN implementation `c0746fbe7f526746af10c3f3367259b59940b3ad` adds real enabled/disabled WooCommerce smoke with WooCommerce `11.0.1` installed only inside wp-env CI.
- Exact-code-head CI `33757929216`: php-quality, js-quality, package, and wordpress-smoke all green, including `npm run test:wp:woocommerce-knowledge`.
- Artifact `9894225906`, digest `sha256:5856481f43cd39755ad087efd66bf28bdf8a7dfec42043b3956826bbad7cb0a3`.
- Independent fresh-session review `5102216678`: **0 Critical / 0 Important unresolved**.

## Whole-M06 review — COMPLETE
Independent integration/security/privacy/performance/compatibility review `5102236196`: **0 Critical / 0 Important unresolved**.

Verified:
- WooCommerce remains an optional runtime integration with no production Composer/npm WooCommerce dependency.
- Only supported public, published, non-password-protected product snapshots become documents.
- Whole-catalog traversal is bounded (<=250 candidates/page) and does not eagerly load the complete catalog.
- Stable metadata is allowlisted; live transactional and private/customer/order/arbitrary metadata remains excluded.
- Generic modified-marker churn cannot alter stable source version/hash; descriptive changes do.
- Bootstrap remains safe with WooCommerce absent.
- Native registry and extension collision semantics remain fail-closed.
- Real WordPress/WooCommerce smoke covers simple/variable products, variations, deterministic reads, stable-vs-live change semantics, unpublish removal, cleanup, and WooCommerce-disabled fallback.
- M07 owns reconciliation/chunking/indexing; M09 owns background execution; M18 owns live commerce actions.

## Task 7 CI installer regression
Final documentation head `33883de97d8943def09e5f1a14b2f1115833b02e` exposed a reproducible external installer-path failure in CI run `33763497982`:
- php-quality, js-quality, package, and all pre-WooCommerce WordPress smoke steps passed;
- `wordpress-smoke` failed only when the smoke invoked WP-CLI slug resolution for WooCommerce: `Error: No plugins installed.`;
- rerunning the failed WordPress job reproduced the same failure at the same boundary, proving it was not a one-off runner failure;
- the prior exact-code-head Task 6 run had passed the same slug-based installer path, and no product code changed between those heads.

The minimal correction `b5b944430764da669afb8ceb68b970008be9a3a6` changes only the test installer path: it installs the exact pinned archive `https://downloads.wordpress.org/plugin/woocommerce.11.0.1.zip` and then fails closed unless `wp plugin get woocommerce --field=version` reports exactly `11.0.1`.

GREEN evidence:
- exact-head CI `33764205102`: php-quality, js-quality, package, and wordpress-smoke all passed;
- WooCommerce smoke passed with the direct pinned archive and version assertion;
- artifact `9896789959`, digest `sha256:2a9db8580182f825c28830b9207d75504186caa768b832596ece946c7e23cad5`.
- same-session review `5102906954`: **0 Critical / 0 Important unresolved**, explicitly not independent.

Because this CI behavior changed after independent whole-M06 review `5102236196`, a genuinely independent fresh-session review of `33883de... -> b5b94443...` is required before merge.

## Accessibility review
N/A — M06 introduces no UI.

## Known limitations
One canonical document per product in M06. Variations are structured product metadata, not standalone documents. Live commerce values/actions remain deferred to later authorized runtime milestones.

## Completion gate
M06 may be merged only after:
1. the CI-installer correction has an independent fresh-session review with 0 Critical / 0 Important unresolved;
2. the final branch head has exact-SHA permanent CI green;
3. PR #8 is conflict-free with no unresolved review threads/findings;
4. merge occurs at the exact verified PR head;
5. fresh post-merge `main` CI is green;
6. final post-merge evidence is recorded durably.

## Exact next unfinished action
Independently review `33883de97d8943def09e5f1a14b2f1115833b02e -> b5b944430764da669afb8ceb68b970008be9a3a6`, focused on deterministic WooCommerce test dependency acquisition, exact version verification, optional-production-dependency boundaries, and CI security/supply-chain implications. If the independent review closes at 0 Critical / 0 Important, require exact-SHA CI on the resulting final docs head, then finish and merge PR #8 at that exact SHA, verify fresh post-merge `main` CI, and record final M06 integration evidence before handing off to M07.

## Next milestone
M07 — Normalization, Chunking, Deduplication & Incremental Indexing.
