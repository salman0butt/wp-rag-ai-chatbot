# Global Status

- Completed milestones on `main`: **M00-M06**.
- Latest integrated milestone: **M06 — WooCommerce Knowledge Ingestion**.
- Current milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — NOT STARTED**.
- M06 integration merge: `356f419ea6df23e68d89a13ee322ca50585ed74b` via PR #8.
- M06 post-merge CI: `33769814956` — all permanent jobs passed.
- M06 post-merge artifact: `9899121219`, digest `sha256:c47e9bac342174603fafa4d99bcebf1b77383a1b06916f70e5bf7bbe744f3cc3`.

## M06 final state — COMPLETE
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Tasks 1–6: complete with strict TDD and independent reviews.
- Whole-M06 review `5102236196`: **0 Critical / 0 Important unresolved**.
- Task 7 CI-installer independent review `5103413133`: **0 Critical / 0 Important unresolved**.
- Final feature head `9f8864e8a3c4c3bc66eb2cf708ba82e86ab493c9`, exact-head CI `33769471726`: all four permanent jobs green.
- Pre-merge artifact `9898987145`, digest `sha256:7327446b8ef212aec82d673d90fc551f08b8411eddb45d61d8ef7d78efcce30f`.
- PR #8 merged using expected-head protection.
- Integrated main SHA `356f419ea6df23e68d89a13ee322ca50585ed74b`.
- Fresh post-merge CI `33769814956`: php-quality, js-quality, package, wordpress-smoke all green.
- Post-merge artifact `9899121219`, digest `sha256:c47e9bac342174603fafa4d99bcebf1b77383a1b06916f70e5bf7bbe744f3cc3`.
- Unresolved Critical/Important findings: **none**.

### M06 verified product boundaries
- Optional-safe WooCommerce catalog gateway -> immutable allowlisted stable product snapshots -> `WooCommerceProductSource` -> canonical `DocumentRecord`.
- Stable facts include status/visibility/title/descriptions/SKU/product URL/categories/tags/descriptive attributes/stable variation identity/SKU/options.
- Current price, sale state, stock/availability, cart/order state, discounts, customer-specific values, arbitrary product/post metadata, and generic modified-marker-only churn are excluded from canonical indexed knowledge/versioning.
- Whole-catalog enumeration is bounded and deterministic, with eligibility failing closed per candidate.
- Real WordPress/WooCommerce smoke verifies simple/variable products, variation metadata, deterministic reads, descriptive-vs-live update behavior, unpublish removal, private/live exclusion, cleanup, and WooCommerce-disabled fallback.

### M06 key TDD lineage
- Task 1 RED `1a626c5d618ea0c4d3a7373eb66d8667d0fec23b`; final reviewed GREEN `17358f11c2348e002786b82ffb2d534fee2e5ae1`.
- Task 2 RED `37334fbc03c593be038e29c44c2d5a416c95a756`; GMT regression RED/GREEN `a4cc2d5c09332819fe65cef2f0c78854b8f8db55` -> `d14f657a59ba12f11231331551c093b4a84df8ba`.
- Task 3 RED `22a6935c32abb6083ac574430b647431ca724032`; pagination/cardinality RED/GREEN `028e5247acf4ced12f4e347a71bdc4e5f5ab8fc1` -> `2931ecf3ea9cf5f946e75e0228a628e4430a28db`.
- Task 4 REDs `f145303afee722c9c333408c2947059ac1b83246`, `73cf87f085682dc1ee843cc0548915ee7b83b9c3`; GREEN `595c9e7c483e42914c901e08afec9b8db935d9d1`.
- Task 5 RED/GREEN `f3db0af57bb108b80872c5ec65852d393d9036d1` -> `50c3ea2292a9bc5d768416ab3c9793f6e59b1ab9`.
- Task 6 wiring RED/GREEN `3a90bd32c8e5aa51b8ea9afded6b8e790214ca49` -> `c0746fbe7f526746af10c3f3367259b59940b3ad`.
- Task 7 installer regression CI `33763497982`; deterministic test-only correction `b5b944430764da669afb8ceb68b970008be9a3a6`.

## M07 durable handoff
- Milestone ledger: `docs/milestones/M07-chunking-dedup-indexing.md`.
- Status: **NOT STARTED**.
- Goal: deterministic normalized content/chunks with traceability, deduplication, hashes, and incremental reindex decisions.
- Dependencies: M04-M06 source/document contracts — now integrated and verified on `main`.
- In scope: structure-aware recursive chunking; heading/paragraph/sentence/token limits; configurable overlap; parent-child/sequence metadata; hashes; dedup; source/index versions; affected-chunk decisions.
- Out of scope: embeddings/vector upserts (M08), async execution engine (M09).

## Exact next unfinished action
Recover M07 against current `main`, inspect the integrated source/document contracts and neighboring milestone boundaries, then execute the scheduled-mode design workflow: develop viable architecture alternatives, select the smallest repository-consistent approach, self-review and auto-approve the design, persist the M07 design spec, write/self-review/auto-approve the executable strict-TDD plan, and only then begin M07 Task 1.

## Previous milestone closeout
- M05 merge `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- M05 post-merge CI `33721014064` — all permanent jobs green.
- M05 durable finalization on `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
