# Global Status

- Completed milestones on `main`: **M00-M06**.
- Current milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — IN PROGRESS**.
- Current verified `main`: `747733a92c23d411ccba2592d5cb8c7858b95a03`.
- Latest verified `main` CI: `33770388757` — all permanent jobs passed.
- Feature branch: `feat/m07-chunking-dedup-indexing`.
- Draft PR: **#9 — `feat: build M07 chunking dedup incremental indexing`**.
- Design/spec: `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Architecture: canonical `DocumentRecord` -> deterministic normalization -> structure-aware bounded chunking -> stable chunk hashes/lineage -> compatibility-safe dedup -> pure incremental index plan.
- M08 owns embedding generation/vector stores/provider-exact compatibility; M09 owns queue/synchronization execution.

## M07 task status

- Task 1 — Deterministic content normalization: **COMPLETE**. Independent review `5104488263`: 0 Critical / 0 Important unresolved.
- Task 2 — Token budget/configuration contracts: **COMPLETE**. Independent review `5105069991`: 0 Critical / 0 Important unresolved.
- Task 3 — Immutable chunk records and structure-aware splitting: **COMPLETE**. Independent review `5105859046`: 0 Critical / 0 Important unresolved.
- Task 4 — Deliberate bounded overlap: **COMPLETE**. Final fresh-session independent re-review `5107540703`: 0 Critical / 0 Important unresolved.
- Task 5 — Compatibility-safe deduplication: **COMPLETE**. Final fresh-session independent re-review `5108150441`: 0 Critical / 0 Important unresolved.
- Task 6 — Incremental index planning: **COMPLETE**. Final fresh-session independent re-review at durable head `b2ef07e9b7d70626a30906f2648a577e8ce9e2e5` / CI `33827583643`: 0 Critical / 0 Important unresolved.
- Task 7 — Source-to-index-plan integration and milestone closeout: **IMPLEMENTATION GREEN AFTER WHOLE-M07 LINEAGE-REFRESH REVIEW FIX; NEW FRESH-SESSION INDEPENDENT RE-REVIEW REQUIRED**.

## Task 7 current evidence

### Recovered integration work
- Task 7 genuine test-first work introduced the pure-PHP `DocumentIndexPipeline` / `DocumentIndexResult` composition and integration fixtures for WordPress-style, file-style, WooCommerce-style, deterministic large-document, unchanged, and localized-change behavior.
- Candidate `bc1039b0ef34fe8d1a4cb1fc5a01d76e05b3e96b` / CI `33831803910` restored bounded localized reuse by no longer forcing every stable chunk into `upsert` solely because document-wide source version/content hash changed.

### Fresh independent Task 7 / whole-M07 review
- Review `5109013876`: **0 Critical / 1 Important**.
- Finding: excluding document-wide `sourceVersion` / `documentContentHash` from reuse restored bounded re-embedding, but stable chunks were then emitted as `unchanged` even though `IndexPlan::unchanged` means no index work. A future executor could therefore retain stale lineage/citation metadata.
- Scheduled auto-approved reconciliation selected an explicit deterministic metadata-only refresh collection instead of either re-embedding every stable chunk or removing required lineage from the chunk/index contract.

### Strict TDD review fix
- First test-only attempt `fe2c61a038c4473e385066412c61137539a8e3ce` / CI `33834769874` is **not valid RED** because PHPCS stopped before PHPUnit on test-helper parameter documentation.
- Corrected genuine RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185`: PHPCS and PHPStan clean; PHPUnit **309 tests / 1426 assertions / exactly 1 intended failure**. The lineage-changed chunk was incorrectly returned in `unchanged` rather than explicit metadata-refresh work.
- First production candidate `3b715b0b0d0b27c45d9930026eec45989ee7c64e` added deterministic `metadataRefresh` planning, but its CI `33834951323` is **not GREEN evidence** because the pre-existing localized integration expectation still required stable chunks to be `unchanged`.
- Root-cause analysis showed production behavior matched the reviewed contract and the integration expectation was obsolete. Test-only reconciliation `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` updates unchanged-document coverage to require no metadata refresh and localized-change coverage to require bounded `upsert` plus non-empty `metadataRefresh` for stable chunks.
- Exact-head GREEN CI `33835032002`: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed; PHPStan **No errors**; PHPUnit **309/309 tests / 1429 assertions**; Composer audit clean.
- Artifact `9923024640`, digest `sha256:bd24d8a2163d174f088bebc6ab85b978a61a7529cc469ab0ac88b5e4712e1933`.
- Same-session post-fix review `5109068463`: **0 Critical / 0 Important unresolved**, explicitly not independent.

## Current Task 7 contract

`IndexPlan` now distinguishes four deterministic work classes:

- `upsert`: new chunks or chunks whose embedding/content/security/compatibility/indexed per-chunk metadata changed;
- `metadataRefresh`: chunks whose embedding/content identity remains reusable but whose document-wide `sourceVersion` or `documentContentHash` changed and must be refreshed in index lineage/citation metadata;
- `deleteKeys`: previous canonical keys absent from current output;
- `unchanged`: exact reusable chunks requiring no index work.

`metadataRefresh`, `upsert`, and `unchanged` are deterministically ordered by sequence + chunk key. Duplicate aliases remain duplicate -> canonical and deterministically ordered. The implementation remains pure PHP/no I/O and does not execute provider, persistence, embedding/vector, queue, REST, hook, or WordPress runtime work.

## Active quality gate

Task 7 / M07 is **implementation-GREEN but not complete**. Because the session that discovered review `5109013876` also implemented the fix, review `5109068463` cannot satisfy the repository-required independent post-fix gate.

A new fresh-session independent Task 7 / whole-M07 re-review must inspect GREEN head `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002` and record **0 unresolved Critical / Important findings** before Task 7 or M07 may be marked complete.

The reviewer must verify: end-to-end normalization -> chunking -> dedup -> planning composition; exact no-op behavior; bounded localized re-embedding; explicit stale-lineage metadata refresh; deterministic `upsert` / `metadataRefresh` / `deleteKeys` / `unchanged` / duplicate aliases; visibility/language/token/source-metadata/embedding compatibility boundaries; caller immutability; bounded performance; untrusted content remaining literal data; and absence of M08/M09/provider/network/persistence/vector/WordPress-execution scope leakage.

## Exact next unfinished action

Perform the required **fresh-session independent Task 7 / whole-M07 re-review** anchored to GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002`. If and only if it reports 0 unresolved Critical/Important findings, reconcile the final M07 acceptance/security/performance/test-matrix documentation, make PR #9 ready, verify the exact final PR head with the full CI matrix, merge, and verify fresh post-merge `main` CI before starting M08.
