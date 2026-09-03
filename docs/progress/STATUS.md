# Global Status

- Completed milestones on `main`: **M00-M06**.
- Latest integrated milestone: **M06 — WooCommerce Knowledge Ingestion**.
- Current milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — IN PROGRESS (Tasks 1-3 complete; Task 4 next)**.
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
- Tasks 4–7 remain unfinished. **Task 4 is now the first legitimate unfinished behavior task.**

### M07 Task 1 evidence
- RED `3e34a6d1125592a256463c3e75ee9406fa3e5e3a`; CI `33775003445`: PHPStan clean; PHPUnit 263 tests / 1252 assertions / 7 intended missing-class failures.
- GREEN `de41fd281d95f2a367df163ea66d8713357b8a14`; CI `33775193798`: all permanent jobs green; PHPUnit 263 / 1252; Composer audit clean.
- Independent fresh-session review `5104488263`: **0 Critical / 0 Important unresolved**.

### M07 Task 2 evidence
- RED `220bffa181cb4a32490f6fa35b3be6904ae790d6`; CI `33780572899`: PHPStan clean; PHPUnit 272 tests / 1261 assertions / 9 intended missing-contract failures.
- GREEN `f802bed14cc1887c219b4ac1058236ded114224c`; CI `33780799386`: all permanent jobs green; PHPUnit 272 / 1276; Composer audit clean.
- Artifact `9903483875`, digest `sha256:f06d0f169bf7fa74718f976a3d63f05b3285347e2a45c24245c74c5a6138d388`.
- Independent fresh-session review `5105069991`: **0 Critical / 0 Important unresolved**.

### M07 Task 3 TDD evidence
- The first test attempt was **not accepted as RED** because PHPCS stopped before PHPUnit. All prematurely added Task 3 production files were removed before the corrected RED so retained implementation followed genuine RED/GREEN order.
- Valid corrected RED test-only head: `7dc00a29dfa4db8a7f7f627cdd6fa9c1c587b442` — `test: make M07 Task 3 RED fixtures quality-clean`.
- RED CI `33793246971`: PHPCS clean; PHPStan clean; PHPUnit **277 tests / 1281 assertions / 5 intended failures**, solely because `ChunkRecord` / `StructureAwareChunker` did not exist.
- GREEN production lineage:
  - `6cb199122e788d574e2014d7e8d00b1166694ea1` — immutable `ChunkRecord`.
  - `6e8ba2a82248519d4fc4fbe0aa1ca36d9f52d622` — `ChunkingException`.
  - `068f16a54b75d52835a6d7ab334ab00d1b4ea083` — `StructureAwareChunker`.
  - `e1dba40c194a4f7204cbaec7042ca4896b42b755` — PHPCS-only alignment correction.
  - `ea025c5038857a1c43ff549fe0b5980fa79243b2` — statically explicit paragraph parser after PHPStan identified the by-reference closure dataflow problem.
- Final Task 3-only code head: `3b223cc22c41e35bbc3599f717606232ea976587`.
- Same-session review `5105805077`: **0 Critical / 0 Important unresolved**, explicitly **not independent**.
- A same-session overlap exploration was fully reverted because overlap belongs to Task 4; no Task 4 behavior is counted as started.
- Final pre-review documentation head `d227e804971e6a5cc40e5fdba8174e0d6a463614`; exact-head CI `33794954967`: **all four permanent jobs green**, including `wordpress-smoke`.
- Independent fresh-session review `5105859046`: **0 Critical / 0 Important unresolved**. It reviewed readonly chunk records, heading/paragraph/sentence/lexical/code-point boundary behavior, deterministic sequence/keys/hashes, copied metadata/language/visibility/URL/version/content lineage, token bounds, untrusted-content handling, and M08/M09/provider/network/persistence scope boundaries.

### M07 Task 3 behavior
`ChunkRecord` is immutable and carries deterministic chunk/document/source lineage, citation metadata, heading path, structural parent, sequence, token count, chunking identity, and nullable embedding compatibility identity. `StructureAwareChunker` remains pure PHP and WordPress-independent. It handles empty/tiny documents; recognizes only existing ATX `#`–`######` headings; treats blank-line blocks as paragraphs; falls back through sentence, Unicode lexical-unit, and code-point-safe boundaries; assigns stable zero-based sequence after boundaries settle; and derives parent/chunk/content hashes through canonical `DocumentHasher`. It performs no provider, network, persistence, embedding, vector, queue, hook, REST, or WordPress execution behavior.

## Active quality gate
Task 3 is **complete**. Task 4 may now begin under the approved plan.

Task 4 must preserve strict TDD:
1. add test-only RED proving overlap is at most configured tokens;
2. prove overlap applies only between adjacent chunks with the same structural parent;
3. prove overlap never crosses sections/documents;
4. prove every overlapped chunk retains room for new content and terminates;
5. verify RED on its exact SHA before implementing overlap behavior;
6. implement the smallest trailing-unit overlap change, then exact-head GREEN/full CI and independent review.

## Exact next unfinished action
Begin **M07 Task 4 — Deliberate bounded overlap** with a fresh genuine test-only RED commit in `tests/Unit/Indexing/Chunking/StructureAwareChunkerTest.php`. Do not modify `StructureAwareChunker` until the test-only SHA reaches the intended PHPUnit failures after clean style/static-analysis gates. Then implement only configured trailing lexical-unit overlap within a single structural parent and keep total chunk token count within `maxTokens`.

## Previous milestone closeout
- M05 merge `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- M05 post-merge CI `33721014064` — all permanent jobs green.
- M05 durable finalization on `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
