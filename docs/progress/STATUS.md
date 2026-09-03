# Global Status

- Completed milestones on `main`: **M00-M05**.
- Latest integrated milestone: **M05 — File/Document Ingestion**.
- Current milestone: **M06 — WooCommerce Knowledge Ingestion — INTEGRATION READY (Tasks 1–6 complete; whole-M06 review complete; Task 7 CI-installer correction independently reviewed; final docs-head CI/merge/post-merge closeout pending)**.
- Current recovered `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
- Latest verified recovered `main` CI: `33721644922` — all permanent jobs passed.

## M06 durable state
- Feature branch: `feat/m06-woocommerce-knowledge-ingestion`.
- Draft PR: #8 — `feat: build M06 WooCommerce knowledge ingestion`.
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Tasks 1–6: **COMPLETE** with strict TDD evidence and independent reviews.
- Whole-M06 integration/security/privacy/performance/compatibility review: **COMPLETE — 0 Critical / 0 Important unresolved**.
- Task 7: **IN PROGRESS** — deterministic CI-installer correction is independently reviewed; final documentation-head exact-SHA CI, merge/post-merge verification, and durable closeout remain.

### Architecture and boundaries
- Optional-safe WooCommerce catalog gateway -> immutable allowlisted stable product snapshots -> `WooCommerceProductSource` -> canonical `DocumentRecord`.
- Stable facts: status/visibility/title/descriptions/SKU/product URL/categories/tags/descriptive attributes/stable variation ID/SKU/options.
- Excluded from canonical knowledge/versioning: current price, sale state, stock/availability, cart/order state, discounts, customer-specific values, arbitrary product/post metadata, and generic modified-marker-only churn.
- Whole-catalog enumeration is bounded, deterministic, page-at-a-time, and candidate cardinality is preserved before source-level duplicate suppression.
- M07 owns reconciliation/chunking/indexing; M09 owns background execution; M18 owns live commerce actions.

## M06 evidence summary

### Task 1
- RED `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`, CI `33723839314`.
- Final reviewed GREEN `17358f11c2348e002786b82ffb2d534fee2e5ae1`, CI `33727368805`.
- Final review: 0 Critical / 0 Important unresolved.

### Task 2
- RED `37334fbc03c593be038e29c44c2d5a416c95a756`, CI `33728200033`.
- GMT regression RED/GREEN `a4cc2d5c09332819fe65cef2f0c78854b8f8db55` -> `d14f657a59ba12f11231331551c093b4a84df8ba`.
- Final acceptance `626adf8b45cce1dd6a02356147b20994b64f4074`, CI `33734709484`.
- Final review: 0 / 0.

### Task 3
- Primary RED `22a6935c32abb6083ac574430b647431ca724032`, CI `33736321322`.
- Pagination/cardinality RED `028e5247acf4ced12f4e347a71bdc4e5f5ab8fc1`; GREEN `2931ecf3ea9cf5f946e75e0228a628e4430a28db`, CI `33747391688`.
- Final fresh-session review: 0 / 0.

### Task 4
- Descriptive-change RED `f145303afee722c9c333408c2947059ac1b83246`.
- Modified-marker exclusion RED `73cf87f085682dc1ee843cc0548915ee7b83b9c3`.
- GREEN `595c9e7c483e42914c901e08afec9b8db935d9d1`, CI `33749868280`.
- Stale-plan review finding corrected at `fd1a68cdb25437a8c20bd223f6e56d571b8c1c0a`, CI `33752938100`.
- Final re-review: 0 / 0.

### Task 5
- Behavioral RED `f3db0af57bb108b80872c5ec65852d393d9036d1`, CI `33753332250`.
- GREEN `50c3ea2292a9bc5d768416ab3c9793f6e59b1ab9`.
- Exact-code-head CI `33753477407` — all permanent jobs green.
- Artifact `9892483643`, digest `sha256:a93ae167ca7db67ef43007ef7b754633708567cfc6c72bc2788a6c1e689a8f4c`.
- Independent review: 0 / 0.

### Task 6
- Wiring RED `3a90bd32c8e5aa51b8ea9afded6b8e790214ca49`, CI `33757505975`.
- GREEN `c0746fbe7f526746af10c3f3367259b59940b3ad` adds real enabled/disabled WooCommerce smoke with WooCommerce `11.0.1` only in wp-env CI.
- Exact-code-head CI `33757929216`: php-quality, js-quality, package, wordpress-smoke all green.
- Artifact `9894225906`, digest `sha256:5856481f43cd39755ad087efd66bf28bdf8a7dfec42043b3956826bbad7cb0a3`.
- Independent Task 6 review `5102216678`: **0 Critical / 0 Important unresolved**.

### Whole-M06 review
- Independent review `5102236196`: **0 Critical / 0 Important unresolved**.
- No blocking inline review threads.
- Verified optional-plugin safety, fail-closed visibility/status/password policy, bounded catalog traversal, stable/live-state separation, privacy allowlist, deterministic hashing, bootstrap/extension collision safety, real WooCommerce smoke, and milestone scope boundaries.

## Task 7 integration / CI evidence
- Pre-reconciliation durable head `0fb45e5c64f4bf2b8d2453141674881bd173fcd6`: exact-SHA CI `33758719268` all permanent jobs green.
- Milestone ledger reconciliation `78ca28c35d059aece354eeb4359c5dafc27d933c`.
- Status reconciliation `33883de97d8943def09e5f1a14b2f1115833b02e`.
- CI `33763497982` on `33883de...` exposed a reproducible failure isolated to WooCommerce test dependency acquisition: `wp plugin install woocommerce ...` returned `Error: No plugins installed.` twice (initial attempt plus rerun), while php-quality/js-quality/package and all earlier WordPress smoke checks remained green.
- Production/plugin behavior did not change between the last known Task 6 green and this failure.
- Minimal correction `b5b944430764da669afb8ceb68b970008be9a3a6` changes only `scripts/test-wp-woocommerce-knowledge.sh`: install the exact pinned archive `https://downloads.wordpress.org/plugin/woocommerce.11.0.1.zip`, then assert the installed plugin reports version `11.0.1`.
- Correction exact-head GREEN CI `33764205102`: all four permanent jobs green, including the WooCommerce smoke.
- Artifact `9896789959`, digest `sha256:2a9db8580182f825c28830b9207d75504186caa768b832596ece946c7e23cad5`.
- Same-session review `5102906954`: 0 Critical / 0 Important unresolved, explicitly not independent.
- Durable documentation head `8f77b0060daed58627acd4a9c4fff3e92781964e`: exact-SHA CI `33764736888` all permanent jobs green; artifact `9897011343`, digest `sha256:c65b76f46be291a7d7b6cf3a980bc8469cf5a65f29ba9896c1a5d2354377219a`.
- Independent fresh-session correction review `5103413133`: **0 Critical / 0 Important unresolved**.
- Review notes: official WordPress.org HTTPS archive + exact installed-version assertion; no production dependency added; failures fail closed under `set -euo pipefail`. Archive content is not checksum-pinned, recorded as a non-blocking supply-chain observation under the current CI trust model.
- Milestone review-record commit: `998ab75c64d42d6f7d8029cc1ff0c11393c708ee`.
- This status update creates the final documentation head candidate; exact-SHA CI is required before merge.

## Current merge gate
PR #8 may be merged only when:
1. independent fresh-session review of the CI-installer correction reports 0 Critical / 0 Important unresolved — **SATISFIED by `5103413133`**;
2. exact-SHA CI for the final branch head passes all permanent jobs;
3. PR remains mergeable/conflict-free;
4. no unresolved Critical/Important review finding or blocking thread exists;
5. merge is performed using the exact verified head SHA;
6. fresh post-merge `main` CI passes;
7. final M06 merge/post-merge evidence is recorded durably on `main`.

## Exact next unfinished action
Wait for/check exact-SHA CI on this final documentation head. If all permanent jobs are green and PR #8 remains mergeable with no blocking threads/findings, mark PR #8 ready and merge using the exact verified head SHA. Then verify fresh post-merge `main` CI, record final M06 integration evidence on `main`, mark M06 complete, and hand off to M07.

## Previous milestone closeout
- M05 merge `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- M05 post-merge CI `33721014064` — all permanent jobs green.
- M05 durable finalization on `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
