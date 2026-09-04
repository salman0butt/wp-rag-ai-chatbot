# Global Status

- Completed milestones on `main`: **M00-M07**.
- Latest integrated milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — COMPLETE**.
- Verified `main` integration SHA: `173bef46d5301f185018cac93256521a5bf23032`.
- M07 integration PR: **#9 — merged** on 2026-09-04.
- M07 final feature head: `a73eccba8a974fae28e34d7cee807dbad5cb2be6`.
- M07 final-head CI: `33853251131` — all permanent jobs passed.
- M07 post-merge `main` CI: `33854072764` — all permanent jobs passed.
- M07 post-merge artifact: `9929654030`, digest `sha256:dde8996ed71b6b3d3d1ff6ec0fdb93d932d85993e00cc8f7bbd9ab70624cdcc3`.
- Design/spec and implementation plan: **AUTO-APPROVED — SCHEDULED MODE**.
- Next milestone: **M08 — Embeddings & Vector Stores — NOT STARTED**.

## M07 final state — COMPLETE

M07 delivers the pure-PHP deterministic pipeline:

`DocumentRecord -> ContentNormalizer -> StructureAwareChunker -> ChunkDeduplicator -> IncrementalIndexPlanner -> DocumentIndexResult`

The milestone remains execution-free: M08 owns embedding/vector-store/provider execution and M09 owns queue/synchronization execution.

### Completed task gates

- Task 1 — Deterministic content normalization: complete; independent review clean.
- Task 2 — Token budget/configuration contracts: complete; independent review clean.
- Task 3 — Immutable chunk records and structure-aware splitting: complete; independent review clean.
- Task 4 — Deliberate bounded overlap: complete after injected-counter and repeated-heading section-isolation review fixes; final independent review clean.
- Task 5 — Compatibility-safe deduplication: complete after deterministic canonical-selection/output-order fixes; final independent review clean.
- Task 6 — Incremental index planning: complete after visibility/indexed-metadata/token-count invalidation fixes; final independent review clean.
- Task 7 — Source-to-index-plan integration and milestone closeout: complete after lineage-refresh and stable section/chunk identity hardening; final whole-M07 review clean.

## Task 7 review-driven hardening

- **Document-lineage metadata refresh:** independent review found stable embeddings could retain stale citation lineage. Genuine RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185`; GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002`. `IndexPlan` now has deterministic `metadataRefresh` work.
- **Repeated-heading public parent identity:** independent review found distinct repeated headings could share a parent key. RED `c67559f5f8f4f3ae6a7f90e9f5fe4611c3e6818f` / CI `33838410737`; GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319`.
- **Localized chunk-count identity:** independent review found document-global chunk sequence caused downstream key churn. Genuine RED `ba5bda5e22cc5d164ae3fdbe41fd5bf9a717c9cc` / CI `33842200871`: PHPStan clean; PHPUnit 310 tests / 1434 assertions / exactly 1 intended failure. Stable chunk identity now uses section-local chunk ordinals while global `sequence` remains presentation/order metadata only.
- **Unrelated-heading insertion stability:** independent review found document-global section ordinals still destabilized later unchanged sections. Genuine RED `7dfaae131323839317ceddddc357cf76649cecb3` / CI `33843112724`: PHPStan clean; PHPUnit 311 tests / 1439 assertions / exactly 1 intended failure. Section occurrence ordinals are now scoped to the same full heading path.

## Final M07 contracts

- Deterministic normalization and structure-aware bounded chunking.
- Global `ChunkRecord::sequence` is ordering/presentation metadata only.
- Stable section identity is full structural heading path + same-path occurrence ordinal.
- Stable chunk identity adds a section-local chunk ordinal.
- Repeated identical heading paths remain distinct section instances.
- Overlap never crosses section instances and obeys injected-counter/configured token budgets.
- Dedup never crosses visibility, language, or embedding-compatibility boundaries.
- `IndexPlan` deterministically separates `upsert`, `metadataRefresh`, `deleteKeys`, `unchanged`, and duplicate -> canonical aliases.
- Retrieved/source content remains literal untrusted data.
- No provider/network/persistence/vector/embedding execution/queue/REST/hook/WordPress-runtime behavior is introduced by M07.

## Final review / verification

Fresh-session independent whole-M07 final-head review `PRR_kwDOUK8kZs8AAAABMKGtxA` at `a73eccba8a974fae28e34d7cee807dbad5cb2be6` reported:

- Critical: **0**
- Important: **0**
- Unresolved blocking review findings: **none**

Final-head CI `33853251131` passed all permanent jobs before merge.

Fresh post-merge `main` CI `33854072764` at `173bef46d5301f185018cac93256521a5bf23032` passed:

- `php-quality` ✅ — PHPStan no errors; PHPUnit **311/311 tests, 1441 assertions**; Composer audit found no security advisories.
- `js-quality` ✅ — dependency install/audit gate, lint/typecheck/tests/build, provider live-gating, and package assertion all passed.
- `package` ✅.
- `wordpress-smoke` ✅ — activation, database, providers, knowledge, file-ingestion, and WooCommerce knowledge smoke passed.

Post-merge artifact: `9929654030`, digest `sha256:dde8996ed71b6b3d3d1ff6ec0fdb93d932d85993e00cc8f7bbd9ab70624cdcc3`.

## Exact next unfinished action

Verify this documentation-only M07 closeout branch with the full permanent CI matrix, merge it to `main`, verify fresh post-closeout `main` CI, then begin **M08 — Embeddings & Vector Stores** with fresh repository recovery plus design/spec/plan auto-approval.
