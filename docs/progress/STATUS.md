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
- Task 7 — Source-to-index-plan integration and milestone closeout: **IMPLEMENTATION GREEN AFTER REPEATED-HEADING PARENT-LINEAGE REVIEW FIX; NEW FRESH-SESSION INDEPENDENT RE-REVIEW REQUIRED**.

## Task 7 current evidence

### Integration and lineage-refresh work
- Task 7 introduced the pure-PHP `DocumentIndexPipeline` / `DocumentIndexResult` composition and integration fixtures for WordPress-style, file-style, WooCommerce-style, deterministic large-document, unchanged, and localized-change behavior.
- Candidate `bc1039b0ef34fe8d1a4cb1fc5a01d76e05b3e96b` / CI `33831803910` restored bounded localized embedding reuse by no longer forcing every stable chunk into `upsert` solely because document-wide source version/content hash changed.
- Fresh independent review `5109013876` found **0 Critical / 1 Important**: stable chunks with changed document-wide `sourceVersion` / `documentContentHash` became `unchanged`, risking stale citation/lineage metadata.
- Scheduled auto-approval selected deterministic `metadataRefresh` work for reusable embeddings whose document-wide lineage changed.
- Genuine RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185`: PHPStan clean; PHPUnit **309 tests / 1426 assertions / exactly 1 intended failure**.
- Exact lineage-refresh GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002`: all four permanent jobs green; PHPUnit **309/309 / 1429 assertions**; Composer audit clean; artifact `9923024640`, digest `sha256:bd24d8a2163d174f088bebc6ab85b978a61a7529cc469ab0ac88b5e4712e1933`.
- Same-session review `5109068463`: **0 Critical / 0 Important unresolved**, explicitly not independent.

### Fresh whole-M07 repeated-heading parent-lineage review
- Exact durable head before this review: `60a0c49c52dbb854f658ba02b363ce8b0aa65ba1`; CI `33835631535` completed successfully across all permanent jobs.
- Fresh-session independent Task 7 / whole-M07 review `5109303824`: **0 Critical / 1 Important**.
- Finding: `StructureAwareChunker` already tracked a deterministic internal `section_id` to prevent overlap crossing repeated identical headings, but public `parentChunkKey` hashed only document key + chunking fingerprint + heading path. Distinct adjacent sections such as `# Same ... # Same` therefore received the same public structural parent key, violating the approved parent-child contract and risking later small-to-big grouping across separate section instances.

### Strict TDD for the repeated-heading parent-lineage fix
- Genuine test-only RED `c67559f5f8f4f3ae6a7f90e9f5fe4611c3e6818f` / CI `33838410737`:
  - production code untouched;
  - PHPStan **No errors**;
  - PHPUnit reached behavior with **309 tests / 1429 assertions / exactly 1 intended failure**;
  - failure: repeated-identical-heading chunks had identical `parentChunkKey` values.
- Minimal production GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` (`fix: distinguish repeated-heading parent lineage`): the existing deterministic descriptor `section_id` is now included in the structural parent hash. No public record schema, provider, persistence, vector, queue, REST, hook, or WordPress-runtime behavior was introduced.
- Exact implementation CI `33838539319`: `php-quality` ✅, `js-quality` ✅, `package` ✅, `wordpress-smoke` ✅; PHPStan **No errors**; PHPUnit **309/309 tests / 1430 assertions**; Composer audit clean.
- Artifact `9924128495`, digest `sha256:0e39273a5e4df34f89cb838b1981994666423d0e9babcc7fe03855ec025f8910`.
- Same-session post-fix review `5109351740`: **0 Critical / 0 Important unresolved**, explicitly not independent because this session discovered and implemented the fix.

## Current Task 7 contract

`IndexPlan` distinguishes deterministic work classes:

- `upsert`: new chunks or chunks whose embedding/content/security/compatibility/indexed per-chunk metadata changed;
- `metadataRefresh`: chunks whose embedding/content identity remains reusable but whose document-wide `sourceVersion` or `documentContentHash` changed and must be refreshed in index lineage/citation metadata;
- `deleteKeys`: previous canonical keys absent from current output;
- `unchanged`: exact reusable chunks requiring no index work;
- `duplicateAliases`: deterministic duplicate -> canonical traceability.

`metadataRefresh`, `upsert`, and `unchanged` are deterministically ordered by sequence + chunk key. Repeated identical heading labels now retain distinct deterministic structural parent identities through the internal section-instance ID while same-section chunks continue sharing one parent key. The implementation remains pure PHP/no I/O and does not execute provider, persistence, embedding/vector, queue, REST, hook, or WordPress runtime work.

## Active quality gate

Task 7 / M07 is **implementation-GREEN but not complete**. The fresh independent review `5109303824` found one Important issue and this same session implemented its fix, so same-session review `5109351740` cannot satisfy the repository-required independent post-fix gate.

A new fresh-session independent Task 7 / whole-M07 re-review must inspect GREEN implementation `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319` plus the latest durable documentation head and record **0 unresolved Critical / Important findings** before Task 7 or M07 may be marked complete.

The reviewer must verify: end-to-end normalization -> chunking -> dedup -> planning composition; repeated-identical-heading parent identity; same-section parent sharing; exact no-op behavior; bounded localized re-embedding; explicit stale-lineage metadata refresh; deterministic `upsert` / `metadataRefresh` / `deleteKeys` / `unchanged` / duplicate aliases; visibility/language/token/source-metadata/chunking/embedding compatibility boundaries; caller immutability; bounded performance; untrusted content remaining literal data; and absence of M08/M09/provider/network/persistence/vector/WordPress-execution scope leakage.

## Exact next unfinished action

Perform the required **fresh-session independent Task 7 / whole-M07 post-fix re-review** anchored to implementation GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319` and the latest durable documentation head. If and only if it reports 0 unresolved Critical/Important findings, reconcile final M07 acceptance/security/performance/test-matrix/spec-plan documentation, make PR #9 ready, verify the exact final PR head with the full permanent CI matrix, merge using the exact expected head SHA, and verify fresh post-merge `main` CI before starting M08.
