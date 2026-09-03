# Global Status

- Completed milestones on `main`: **M00-M06**.
- Latest integrated milestone: **M06 — WooCommerce Knowledge Ingestion**.
- Current milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — IN PROGRESS (Task 1 code/CI green; independent review pending)**.
- Current verified `main`: `747733a92c23d411ccba2592d5cb8c7858b95a03`.
- Latest verified `main` CI: `33770388757` — all permanent jobs passed.
- M06 integration merge: `356f419ea6df23e68d89a13ee322ca50585ed74b` via PR #8.

## M06 final state — COMPLETE
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Tasks 1–6: complete with strict TDD and independent reviews.
- Whole-M06 review `5102236196`: **0 Critical / 0 Important unresolved**.
- Task 7 CI-installer independent review `5103413133`: **0 Critical / 0 Important unresolved**.
- Final feature head `9f8864e8a3c4c3bc66eb2cf708ba82e86ab493c9`, exact-head CI `33769471726`: all four permanent jobs green.
- PR #8 merged using expected-head protection at integrated main SHA `356f419ea6df23e68d89a13ee322ca50585ed74b`.
- Fresh post-merge CI `33769814956`: php-quality, js-quality, package, wordpress-smoke all green.
- Final M06 durable closeout `747733a92c23d411ccba2592d5cb8c7858b95a03`; CI `33770388757` green.
- Unresolved Critical/Important findings: **none**.

## M07 active state
- Feature branch: `feat/m07-chunking-dedup-indexing`.
- Draft PR: **#9 — `feat: build M07 chunking dedup incremental indexing`**.
- Milestone ledger: `docs/milestones/M07-chunking-dedup-indexing.md`.
- Design/spec: `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Selected architecture: canonical `DocumentRecord` -> meaning-preserving deterministic normalization -> structure-aware bounded chunking -> stable chunk hashes/lineage -> compatibility-safe dedup -> pure incremental index plan.
- M08 owns embedding generation/vector stores and provider-exact compatibility; M09 owns queue/synchronization execution.
- Task 1 — Deterministic content normalization: **IMPLEMENTED + EXACT-CODE-HEAD GREEN; INDEPENDENT FRESH-SESSION REVIEW PENDING**.
- Tasks 2–7 remain and must not start until Task 1 independent review closes at 0 Critical / 0 Important unresolved.

### M07 Task 1 TDD evidence
- RED test commit: `3e34a6d1125592a256463c3e75ee9406fa3e5e3a`.
- RED CI `33775003445`: PHPStan clean; PHPUnit **263 tests / 1252 assertions / 7 expected failures**, each exactly `ContentNormalizer class does not exist yet.`
- Minimal GREEN implementation: `de41fd281d95f2a367df163ea66d8713357b8a14`.
- Exact-code-head GREEN CI `33775193798`: **php-quality, js-quality, package, wordpress-smoke all passed**.
- GREEN PHP verification: PHPStan clean; PHPUnit **263 / 1252**, all passed; Composer audit clean.
- Artifact `9901292022`, 712652 bytes, digest `sha256:1457cf8e2c625978959806ceb0e75c2409460a2ac3db4ad03e738de78873c552`.
- Same-session review `5104040492`: **0 Critical / 0 Important unresolved in Task 1 code/test**, explicitly **not independent**.

### M07 Task 1 behavior
`ContentNormalizer` is a pure PHP/WordPress-independent utility. It:
- strips only a leading UTF-8 BOM;
- normalizes CRLF/lone CR to LF;
- removes trailing spaces/tabs from each line;
- collapses runs of three or more line feeds to two;
- trims spaces/tabs/newlines from document edges;
- preserves internal spacing and literal prompt-like/HTML-like/shortcode/PHP-like content;
- is idempotent for canonical output.

It does not parse/execute content, perform provider calls, tokenize for a model, persist data, write vectors, or introduce queue behavior.

## Exact next unfinished action
Freshly recover PR #9 and independently review M07 Task 1 from RED `3e34a6d1125592a256463c3e75ee9406fa3e5e3a` through GREEN `de41fd281d95f2a367df163ea66d8713357b8a14`, including the durable documentation commits that follow it. Focus on meaning preservation, leading-BOM-only behavior, CR/LF normalization, trailing horizontal-space handling, blank-run collapse, document-edge trimming, idempotence, untrusted literal-content preservation, and compatibility with canonical M04-M06 `DocumentRecord` output. Fix any Critical/Important behavior issue via regression RED/GREEN and exact-head CI. If review is 0 Critical / 0 Important unresolved, mark Task 1 complete and begin Task 2 token/config contracts with a test-only RED commit.

## Previous milestone closeout
- M05 merge `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- M05 post-merge CI `33721014064` — all permanent jobs green.
- M05 durable finalization on `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
