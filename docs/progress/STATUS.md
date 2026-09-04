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
- Task 4 — Deliberate bounded overlap: **COMPLETE**. Final fresh-session independent review `5107540703`: 0 Critical / 0 Important unresolved.
- Task 5 — Compatibility-safe deduplication: **COMPLETE**. Final fresh-session independent review `5108150441`: 0 Critical / 0 Important unresolved.
- Task 6 — Incremental index planning: **COMPLETE**. Final fresh-session independent review at `b2ef07e9b7d70626a30906f2648a577e8ce9e2e5` / CI `33827583643`: 0 Critical / 0 Important unresolved.
- Task 7 — Source-to-index-plan integration and milestone closeout: **IN PROGRESS — localized chunk-count identity defect fixed in the current implementation; full exact-SHA JS verification and a new fresh-session independent post-fix review remain required**.

## Task 7 durable evidence

### Earlier integration hardening

- Document-lineage review `5109013876`: 0 Critical / 1 Important. Stable embeddings with changed document-wide lineage could remain `unchanged` and preserve stale citation metadata.
- Genuine RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185`; GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002`; artifact `9923024640`, digest `sha256:bd24d8a2163d174f088bebc6ab85b978a61a7529cc469ab0ac88b5e4712e1933`.
- Repeated-heading parent review `5109303824`: 0 Critical / 1 Important. Distinct repeated identical headings shared a public parent key.
- Genuine RED `c67559f5f8f4f3ae6a7f90e9f5fe4611c3e6818f` / CI `33838410737`; GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319`; artifact `9924128495`, digest `sha256:0e39273a5e4df34f89cb838b1981994666423d0e9babcc7fe03855ec025f8910`.

### Fresh whole-M07 localized chunk identity review

Fresh-session independent Task 7 / whole-M07 review `5109627614` at durable head `42a7a8e6e4f64e8b51fb7ea9185e1176a120c7b5` reported:

- **Critical: 0**
- **Important: 1**

Finding: `StructureAwareChunker` used the document-global final `sequence` inside `chunkKey`. When an edited early/middle section gained or lost a chunk, every later byte-identical section shifted global sequence and therefore changed key. `IncrementalIndexPlanner` consequently produced downstream delete/upsert/re-embedding work instead of bounded localized reuse, violating M07 acceptance criteria.

### Strict TDD for localized chunk-count identity

- Test-only `c656164c34f3b37572a4b2a2f1e40f88cbee5bdb` / CI `33841997655`: **invalid RED** because PHPCS stopped before PHPUnit.
- Test-only formatting `098e06197dc64804011c379297002775d17aeba0` / CI `33842060013`: **invalid RED** because PHPCS still stopped before PHPUnit.
- Genuine RED `ba5bda5e22cc5d164ae3fdbe41fd5bf9a717c9cc` / CI `33842200871`: production untouched; PHPStan **No errors**; PHPUnit **310 tests / 1434 assertions / exactly 1 intended failure** proving the byte-identical later Gamma section received a different chunk key after Beta gained a chunk.
- Production candidate `81202fe0351155ce151ebd5cc428e792d3d203c1` introduced deterministic section-local chunk ordinals while retaining global `ChunkRecord::sequence` solely for ordering. CI `33842300930` did not reach PHPUnit because two PHPCS alignment warnings remained, so it is **not GREEN evidence**.
- Formatting-only candidate `e15d95f3970b7350b99efc459a1c42293e3b16e4` passed the stable-key assertion but exposed an outdated PHPUnit assertion API in the new regression test; CI `33842400244` is **not GREEN evidence**.
- Corrected implementation/test head `a13f6ff1edec5fc0df3c7a319343a1f4dcb24881` / CI `33842525625`: PHPStan **No errors**; PHPUnit **310/310 tests / 1435 assertions**; Composer audit clean; `php-quality` ✅, `package` ✅, `wordpress-smoke` ✅. Artifact `9925441564`, digest `sha256:dbd1f32fdcc3948cfc363612b56b29523992e9643b747f3b33fc243e035655cb`. At this handoff, `js-quality` is still inside the external `npm audit --audit-level=critical` call following earlier npm-registry instability, so the exact SHA is **not yet claimed full-matrix GREEN**.

## Current Task 7 contracts

- Global `ChunkRecord::sequence` remains deterministic presentation/order metadata.
- Chunk identity uses document key + chunking fingerprint + structural path + section instance + section-local chunk ordinal, preventing chunk-count changes in one section from renumbering all later stable chunk identities.
- Structural parent identity remains section-instance-aware so repeated identical headings stay distinct while same-section chunks share one parent.
- `IndexPlan` separates deterministic `upsert`, `metadataRefresh`, `deleteKeys`, `unchanged`, and duplicate -> canonical aliases.
- Stable content whose document-wide lineage changed uses `metadataRefresh` rather than re-embedding.
- The pipeline remains pure PHP/no I/O and performs no provider, persistence, embedding/vector, queue, REST, hook, or WordPress runtime execution.

## Active quality gate

Task 7 / M07 is **not complete**. This run performed the fresh independent review and then implemented its finding, so it cannot count its own post-fix inspection as the repository-required fresh independent review. In addition, exact implementation CI `33842525625` must finish `js-quality` successfully (or a transient infrastructure failure must be rerun successfully) before the implementation can be called full-matrix GREEN.

## Exact next unfinished action

1. Recover PR #9 and confirm the head has not advanced unexpectedly.
2. Finish exact-SHA verification for `a13f6ff1edec5fc0df3c7a319343a1f4dcb24881` / CI `33842525625`; if npm registry instability causes an infrastructure-only failure, rerun the failed JS job and require success.
3. Reconcile the M07 milestone/spec/plan and PR description with the section-local chunk identity contract.
4. Verify the resulting durable documentation head with the full permanent CI matrix.
5. Perform a **new fresh-session independent Task 7 / whole-M07 post-fix review**. Only with 0 unresolved Critical / Important findings may Task 7/M07 be marked complete, PR #9 be made ready, exact-final-head CI be accepted, the PR be merged using the exact expected SHA, and fresh post-merge `main` CI be verified before M08 begins.
