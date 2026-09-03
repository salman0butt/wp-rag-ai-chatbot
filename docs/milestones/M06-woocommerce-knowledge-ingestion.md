# M06 — WooCommerce Knowledge Ingestion

Status: **COMPLETE — merged to `main` via PR #8 at `356f419ea6df23e68d89a13ee322ca50585ed74b`; fresh post-merge CI `33769814956` passed all permanent jobs.**

## Goal
Normalize stable public WooCommerce catalog knowledge while clearly separating indexed descriptive facts from live transactional state.

## Dependencies
M04; M05 patterns; WooCommerce optional environment.

## Design / plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md`.
- Both: `AUTO-APPROVED — SCHEDULED MODE` after repository-mandated self-review.
- Task 4 clarification: generic WooCommerce modified-time churn is observational only and excluded from M06 source version/hash because it cannot distinguish descriptive changes from excluded live-state changes.

## Final architecture
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

## Tasks — COMPLETE
- [x] Task 1 — WooCommerce catalog contracts and immutable snapshots.
- [x] Task 2 — Optional-safe native WooCommerce catalog gateway.
- [x] Task 3 — WooCommerce product source canonical mapping.
- [x] Task 4 — Stable source version and live-state exclusion regressions.
- [x] Task 5 — Knowledge bootstrap registration and disabled-WooCommerce safety.
- [x] Task 6 — Real WordPress/WooCommerce smoke coverage.
- [x] Task 7 — Integration/review/documentation/merge/post-merge verification.

## Durable TDD / review evidence

### Task 1
- Primary RED `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`, CI `33723839314`.
- Independent review found an Important normalized-attribute collision issue; product/variation regressions were fixed through RED/GREEN.
- Final reviewed GREEN `17358f11c2348e002786b82ffb2d534fee2e5ae1`, CI `33727368805`.
- Final review: **0 Critical / 0 Important unresolved**.

### Task 2
- Availability/enumeration RED `37334fbc03c593be038e29c44c2d5a416c95a756`, CI `33728200033`.
- GMT regression RED `a4cc2d5c09332819fe65cef2f0c78854b8f8db55`; GREEN `d14f657a59ba12f11231331551c093b4a84df8ba`, CI `33734311953`.
- Final acceptance `626adf8b45cce1dd6a02356147b20994b64f4074`, CI `33734709484`.
- Final review: **0 Critical / 0 Important unresolved**.

### Task 3
- Primary RED `22a6935c32abb6083ac574430b647431ca724032`, CI `33736321322`.
- Fail-closed config RED `c9a9c7c32453c8226024e3948d0beb0f72dc875e`.
- Independent review found an Important premature catalog-page exhaustion risk.
- Pagination/cardinality RED `028e5247acf4ced12f4e347a71bdc4e5f5ab8fc1`, CI `33747195823`; GREEN `2931ecf3ea9cf5f946e75e0228a628e4430a28db`, CI `33747391688`.
- Final fresh-session review: **0 Critical / 0 Important unresolved**.

### Task 4
- Descriptive-change RED `f145303afee722c9c333408c2947059ac1b83246`, CI `33749130132`.
- Modified-marker exclusion RED `73cf87f085682dc1ee843cc0548915ee7b83b9c3`, CI `33749715650`.
- GREEN `595c9e7c483e42914c901e08afec9b8db935d9d1`, CI `33749868280`.
- Independent review found an Important stale-plan contradiction; fixed at `fd1a68cdb25437a8c20bd223f6e56d571b8c1c0a`, CI `33752938100`.
- Final re-review: **0 Critical / 0 Important unresolved**.

### Task 5
- Behavioral RED `f3db0af57bb108b80872c5ec65852d393d9036d1`, CI `33753332250` — expected failure because `woocommerce_product` was absent.
- GREEN `50c3ea2292a9bc5d768416ab3c9793f6e59b1ab9`; exact-code-head CI `33753477407` all permanent jobs green.
- Independent fresh-session review: **0 Critical / 0 Important unresolved**.

### Task 6
- Wiring RED `3a90bd32c8e5aa51b8ea9afded6b8e790214ca49`, CI `33757505975`: prior smoke gates passed and the new WooCommerce command failed only because its script intentionally did not yet exist.
- GREEN `c0746fbe7f526746af10c3f3367259b59940b3ad` added real enabled/disabled WooCommerce smoke with WooCommerce `11.0.1` only in wp-env CI.
- Exact-code-head CI `33757929216`: all permanent jobs green.
- Independent Task 6 review `5102216678`: **0 Critical / 0 Important unresolved**.

## Whole-M06 review
Independent integration/security/privacy/performance/compatibility review `5102236196`: **0 Critical / 0 Important unresolved**.

Verified optional-plugin safety, fail-closed eligibility, bounded catalog traversal, stable/live-state separation, strict metadata allowlist/privacy boundary, deterministic hashing, bootstrap/extension collision safety, real WooCommerce smoke, and milestone scope boundaries. M07 owns reconciliation/chunking/indexing; M09 owns background execution; M18 owns live commerce actions.

## Task 7 CI installer regression and resolution
Final documentation candidate `33883de97d8943def09e5f1a14b2f1115833b02e` exposed a reproducible external installer-path failure in CI `33763497982`: the prior gates passed, while WP-CLI slug resolution returned `Error: No plugins installed.`. A failed-job rerun reproduced the same failure.

Minimal correction `b5b944430764da669afb8ceb68b970008be9a3a6` changed only test dependency acquisition to the exact official archive `https://downloads.wordpress.org/plugin/woocommerce.11.0.1.zip` and added a fail-closed assertion that the installed version is exactly `11.0.1`.

- Correction CI `33764205102`: all permanent jobs green.
- Same-session review `5102906954`: 0 Critical / 0 Important unresolved, explicitly not independent.
- Durable pre-merge head `8f77b0060daed58627acd4a9c4fff3e92781964e`, CI `33764736888`: all permanent jobs green.
- Independent fresh-session correction review `5103413133`: **0 Critical / 0 Important unresolved**.
- Review-record final branch head `9f8864e8a3c4c3bc66eb2cf708ba82e86ab493c9`.
- Exact-final-head CI `33769471726`: **php-quality, js-quality, package, wordpress-smoke all passed**.
- Pre-merge artifact `9898987145`, digest `sha256:7327446b8ef212aec82d673d90fc551f08b8411eddb45d61d8ef7d78efcce30f`.
- Non-blocking observation: the exact-version WordPress.org archive is not content-checksum-pinned; this does not add production privilege/exposure and remains within the repository's current CI trust model.

## Merge / post-merge verification — COMPLETE
- PR #8 was marked ready only after the final independent review, exact-head green CI, mergeability check, and confirmation of zero blocking review threads.
- PR #8 squash-merged with expected-head protection at final branch SHA `9f8864e8a3c4c3bc66eb2cf708ba82e86ab493c9`.
- Integrated `main` commit: `356f419ea6df23e68d89a13ee322ca50585ed74b`.
- Fresh push-triggered post-merge CI: `33769814956` — **php-quality, js-quality, package, wordpress-smoke all passed**, including activation, database, providers, knowledge, file-ingestion, WooCommerce enabled knowledge smoke, WooCommerce-disabled fallback, and environment shutdown.
- Post-merge artifact `9899121219`, 711954 bytes, digest `sha256:c47e9bac342174603fafa4d99bcebf1b77383a1b06916f70e5bf7bbe744f3cc3`.
- Unresolved Critical findings: **0**.
- Unresolved Important findings: **0**.
- Accessibility: N/A — M06 introduces no UI.

## Known limitations
One canonical document per product in M06. Variations are structured product metadata, not standalone documents. Live commerce values/actions remain deferred to later authorized runtime milestones.

## Completion checklist
- [x] Design/spec/plan auto-approved and reconciled.
- [x] Strict task-level RED/GREEN evidence captured.
- [x] Security/privacy/performance/compatibility reviews complete.
- [x] Independent reviews complete with 0 Critical / 0 Important unresolved.
- [x] Exact-final-feature-head CI green.
- [x] PR merged using exact expected head.
- [x] Fresh post-merge `main` CI green.
- [x] Final integration evidence recorded durably.

## Exact next unfinished action
Begin **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing** from `docs/milestones/M07-chunking-dedup-indexing.md`: recover current architecture/source/document contracts, perform the scheduled-mode design alternatives/self-review/auto-approval procedure, persist the M07 design spec and executable TDD plan, then begin Task 1 only after those artifacts are internally consistent.

## Next milestone
M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing.
