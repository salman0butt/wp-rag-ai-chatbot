# Global Status

- Completed milestones on `main`: **M00-M06**.
- Latest integrated milestone: **M06 — WooCommerce Knowledge Ingestion**.
- Current milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — IN PROGRESS (Tasks 1-3 complete; Task 4 GREEN pending independent review)**.
- Current verified `main`: `747733a92c23d411ccba2592d5cb8c7858b95a03`.
- Latest verified `main` CI: `33770388757` — all permanent jobs passed.
- M06 integration merge: `356f419ea6df23e68d89a13ee322ca50585ed74b` via PR #8.

## M06 final state — COMPLETE
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Tasks 1–6 complete with strict TDD and independent reviews.
- Whole-M06 review `5102236196`: **0 Critical / 0 Important unresolved**.
- Task 7 CI-installer independent review `5103413133`: **0 Critical / 0 Important unresolved**.
- Final M06 durable closeout `747733a92c23d411ccba2592d5cb8c7858b95a03`; CI `33770388757` green.

## M07 active state
- Feature branch: `feat/m07-chunking-dedup-indexing`.
- Draft PR: **#9 — `feat: build M07 chunking dedup incremental indexing`**.
- Milestone ledger: `docs/milestones/M07-chunking-dedup-indexing.md`.
- Design/spec: `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Architecture: canonical `DocumentRecord` -> deterministic normalization -> structure-aware bounded chunking -> stable chunk hashes/lineage -> compatibility-safe dedup -> pure incremental index plan.
- M08 owns embedding generation/vector stores and provider-exact compatibility; M09 owns queue/synchronization execution.
- Task 1 — Deterministic content normalization: **COMPLETE** after strict RED/GREEN, exact-head CI, and independent fresh-session review `5104488263` with 0 Critical / 0 Important unresolved.
- Task 2 — Token budget/configuration contracts: **COMPLETE** after strict RED/GREEN, exact-head CI, and independent fresh-session review `5105069991` with 0 Critical / 0 Important unresolved.
- Task 3 — Immutable chunk records and structure-aware splitting: **COMPLETE** after corrected genuine RED/GREEN, exact-head CI, and independent fresh-session review `5105859046` with 0 Critical / 0 Important unresolved.
- Task 4 — Deliberate bounded overlap: **IMPLEMENTED + GREEN; PENDING FRESH-SESSION INDEPENDENT REVIEW**.
- Tasks 5–7 remain unstarted. Do not begin Task 5 until Task 4 independent review has 0 unresolved Critical/Important findings.

### M07 Task 1 evidence
- RED `3e34a6d1125592a256463c3e75ee9406fa3e5e3a`; CI `33775003445`: PHPStan clean; PHPUnit 263 tests / 1252 assertions / 7 intended missing-class failures.
- GREEN `de41fd281d95f2a367df163ea66d8713357b8a14`; CI `33775193798`: all permanent jobs green; PHPUnit 263 / 1252; Composer audit clean.
- Independent fresh-session review `5104488263`: **0 Critical / 0 Important unresolved**.

### M07 Task 2 evidence
- RED `220bffa181cb4a32490f6fa35b3be6904ae790d6`; CI `33780572899`: PHPStan clean; PHPUnit 272 tests / 1261 assertions / 9 intended missing-contract failures.
- GREEN `f802bed14cc1887c219b4ac1058236ded114224c`; CI `33780799386`: all permanent jobs green; PHPUnit 272 / 1276; Composer audit clean.
- Artifact `9903483875`, digest `sha256:f06d0f169bf7fa74718f976a3d63f05b3285347e2a45c24245c74c5a6138d388`.
- Independent fresh-session review `5105069991`: **0 Critical / 0 Important unresolved**.

### M07 Task 3 evidence
- Valid corrected RED `7dc00a29dfa4db8a7f7f627cdd6fa9c1c587b442`; CI `33793246971`: PHPCS/PHPStan clean; PHPUnit **277 tests / 1281 assertions / 5 intended failures**, solely because Task 3 production classes did not exist.
- Final Task 3-only code head `3b223cc22c41e35bbc3599f717606232ea976587`.
- Pre-review documentation head `d227e804971e6a5cc40e5fdba8174e0d6a463614`; CI `33794954967`: all four permanent jobs green.
- Independent fresh-session review `5105859046`: **0 Critical / 0 Important unresolved**.

### M07 Task 4 TDD evidence
- Initial test-only attempts `ea9c68b850fb091dce6bf531bd068a5cdce2ea0a` and `8c44877fc1ac739a2bd0262fe69ec88eefd9397a` are **not counted as valid RED** because PHPCS stopped before PHPUnit on alignment warnings. No production Task 4 code existed at either attempt.
- Corrected genuine test-only RED: `49f423bcbdfd1458be31b4e376c55ef269e32a39` — `test: align M07 overlap RED fixtures`.
- RED CI `33796054973`: PHPCS clean; PHPStan **No errors**; PHPUnit **280 tests / 1334 assertions / 2 intended failures**. The failures were exactly the missing same-parent overlap assertion and the missing budget-reduced overlap assertion. The cross-parent no-overlap fixture passed.
- GREEN implementation: `aae5ab3861928bbcc2370d72a1a550c6c6eb2745` — `feat: add bounded structural chunk overlap`.
- GREEN CI `33796230348`: all permanent jobs passed (`php-quality`, `js-quality`, `package`, `wordpress-smoke`); PHPUnit **280/280 tests / 1337 assertions**; PHPStan clean; Composer audit clean.
- Same-session review `5105957190`: **0 Critical / 0 Important unresolved**, explicitly **not independent**.

### M07 Task 4 behavior
`StructureAwareChunker` now applies configured trailing Unicode lexical-unit overlap in one bounded post-split pass. Overlap is permitted only between adjacent base descriptors with identical heading paths, never crosses a section/document boundary, is capped by both `overlapTokens` and remaining `maxTokens` capacity, preserves all original new content, and uses the prior base descriptor rather than recursively feeding overlapped output forward. Hashes and final token counts are calculated after overlap settles. No provider, network, persistence, embedding, vector, queue, hook, REST, or WordPress execution behavior was added.

## Active quality gate
Task 4 implementation is GREEN but is **not complete** until a fresh-session independent review confirms **0 unresolved Critical / Important findings**. Do not begin Task 5 before that gate is satisfied.

The independent review must specifically inspect:
1. overlap bound vs `overlapTokens` and `maxTokens`;
2. same-parent-only behavior and section/document isolation;
3. preservation of original new content;
4. deterministic hashes/token counts after overlap;
5. termination and non-quadratic behavior on long descriptor sequences;
6. Unicode lexical-unit slicing and invalid UTF-8 fail-closed behavior;
7. absence of M08/M09/provider/network/persistence scope leakage.

## Exact next unfinished action
Perform a **fresh-session independent review of M07 Task 4** anchored to GREEN code `aae5ab3861928bbcc2370d72a1a550c6c6eb2745` and its verified CI `33796230348`. If the review has 0 unresolved Critical/Important findings, record Task 4 complete in durable docs; only then begin Task 5 — Compatibility-safe deduplication — with a genuine test-only RED.

## Previous milestone closeout
- M05 merge `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- M05 post-merge CI `33721014064` — all permanent jobs green.
- M05 durable finalization on `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
