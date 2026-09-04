# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **COMPLETE — merged via PR #9 and verified on `main`.**

## Goal
Create deterministic normalized content and chunks with traceability, compatibility-safe deduplication, and a pure-PHP incremental index plan that minimizes re-embedding without allowing stale lineage, structural identity, or security metadata.

## Dependencies
M04-M06 source/document contracts.

## Design / plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md`.
- Status: **AUTO-APPROVED — SCHEDULED MODE** under `AGENTS.md` and `docs/AUTONOMOUS-DEVELOPMENT.md`.
- Architecture: pure-PHP deterministic `DocumentRecord -> normalize -> structure-aware bounded chunk -> stable hash/lineage -> compatibility-safe dedup -> incremental index plan`.
- M08 owns embedding/vector-store/provider execution; M09 owns queue/synchronization execution.

## Acceptance criteria — satisfied
- Boundary fixtures are deterministic, including tiny and huge input.
- Exact unchanged content produces zero index work.
- Localized edits produce bounded embedding/upsert work instead of re-embedding stable chunks outside the changed structural unit.
- Global sequence is deterministic ordering metadata only.
- Changing chunk count in one section does not destabilize later byte-identical sections.
- Inserting/removing an unrelated earlier heading does not destabilize later byte-identical section identity.
- Stable chunks with document-wide lineage changes use explicit metadata-refresh work.
- Repeated identical heading paths remain distinct structural parent instances while same-section chunks share one parent.
- Dedup does not cross visibility, language, or embedding-compatibility boundaries.
- Retrieved/source text remains untrusted literal data.
- Public plan collections are deterministic.

## Tasks
- [x] Task 1 — Deterministic content normalization. Independent review `5104488263` clean.
- [x] Task 2 — Token budget/configuration contracts. Independent review `5105069991` clean.
- [x] Task 3 — Immutable chunk records and structure-aware splitting. Independent review `5105859046` clean.
- [x] Task 4 — Deliberate bounded overlap. Final independent review `5107540703` clean.
- [x] Task 5 — Compatibility-safe deduplication. Final independent review `5108150441` clean.
- [x] Task 6 — Incremental index planning. Final independent review at `b2ef07e9b7d70626a30906f2648a577e8ce9e2e5` / CI `33827583643` clean.
- [x] Task 7 — Source-to-index-plan integration and milestone closeout. Final independent whole-M07 review `PRR_kwDOUK8kZs8AAAABMKGtxA`: 0 Critical / 0 Important unresolved.

## Task 7 integration contract
`DocumentIndexPipeline` composes:

`DocumentRecord -> ContentNormalizer -> StructureAwareChunker -> ChunkDeduplicator -> IncrementalIndexPlanner -> DocumentIndexResult`

It remains pure PHP/no I/O and performs no provider, persistence, embedding/vector, queue, REST, hook, or WordPress runtime execution.

## Review-driven hardening

### Document-lineage refresh
Independent review `5109013876` found **0 Critical / 1 Important**: document-wide lineage changes could remain `unchanged`. Strict TDD produced RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185`, then GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002`. The planner now exposes deterministic `metadataRefresh` work for reusable embeddings whose document-wide lineage changed.

### Repeated-heading structural parent identity
Independent review `5109303824` found **0 Critical / 1 Important**: repeated identical headings could share a public structural parent key. Strict TDD produced RED `c67559f5f8f4f3ae6a7f90e9f5fe4611c3e6818f` / CI `33838410737`, then GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319`. Structural parent hashes now include deterministic section-instance identity.

### Localized chunk-count identity
Fresh-session independent review `PRR_kwDOUK8kZs8AAAABMI663g` found **0 Critical / 1 Important**: document-global final sequence destabilized later unchanged chunk keys when an earlier section changed chunk count.

- Genuine RED `ba5bda5e22cc5d164ae3fdbe41fd5bf9a717c9cc` / CI `33842200871`: PHPStan clean; PHPUnit **310 tests / 1434 assertions / exactly 1 intended failure**.
- Final behavior keeps global `ChunkRecord::sequence` only for ordering and uses section-local chunk ordinals for stable identity.

### Unrelated-heading insertion/removal identity
Fresh-session independent review `PRR_kwDOUK8kZs8AAAABMJA_2Q` found **0 Critical / 1 Important**: a document-global section ordinal changed later stable identities when an unrelated earlier heading was inserted or removed.

- Genuine RED `7dfaae131323839317ceddddc357cf76649cecb3` / CI `33843112724`: PHPStan clean; PHPUnit **311 tests / 1439 assertions / exactly 1 intended failure**.
- Final fix scopes section occurrence ordinals to the same full structural heading path.
- Integration coverage proves an unrelated inserted section does not change a later byte-identical section's `parentChunkKey`/`chunkKey` and does not add that stable chunk to `upsert`.

## Final chunk identity contract
- `ChunkRecord::sequence` is deterministic global ordering/presentation metadata only.
- Section-instance identity is based on full heading path plus deterministic occurrence ordinal scoped to that same heading path.
- Stable `chunkKey` adds a section-local chunk ordinal.
- Unrelated sibling heading insertions/removals do not cascade identity churn.
- Repeated identical heading paths remain distinct because their same-path occurrence ordinals differ.
- `parentChunkKey` includes stable section-instance identity.
- Content hashes retain canonical/source/parent/content lineage as designed.

## Final index-plan contract
The immutable plan exposes deterministic `upsert`, `metadataRefresh`, `deleteKeys`, `unchanged`, and duplicate -> canonical aliases. Stable embedding/content identity with changed document-wide lineage routes to `metadataRefresh`; actual content/security/compatibility/indexed-metadata changes route to `upsert`.

## Security review
M07 executes no retrieved/source content and performs no provider/network/credential/persistence/vector/queue/REST/hook/WordPress-runtime work. Prompt-like or markup-like source content remains literal data. Visibility/language/embedding compatibility remain hard boundaries.

The JS critical-vulnerability gate remains fail-closed during npm audit-service outages. The final feature head pins fallback eligibility to the exact previously audited `package-lock.json` Git blob and fails on non-transient audit errors or lockfile mismatch.

## Performance review
Normalization/chunking stay bounded by configured limits. Dedup/planner comparison use expected O(n) map/set work plus bounded deterministic sorting. Stable section-local identity and same-path-scoped section occurrence identity prevent localized structural changes from cascading delete/upsert/re-embedding across unrelated downstream sections.

## Final verification / integration evidence
Final feature head `a73eccba8a974fae28e34d7cee807dbad5cb2be6`:

- Fresh-session independent whole-M07 final-head review `PRR_kwDOUK8kZs8AAAABMKGtxA`: **0 Critical / 0 Important unresolved**.
- Exact-head CI `33853251131`: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all green.
- PR #9 merged on 2026-09-04 as `173bef46d5301f185018cac93256521a5bf23032`.

Fresh post-merge `main` CI `33854072764` at `173bef46d5301f185018cac93256521a5bf23032`:

- `php-quality` ✅ — PHPStan **No errors**; PHPUnit **311/311 tests / 1441 assertions**; Composer audit: no security vulnerability advisories.
- `js-quality` ✅ — install/audit, lint/typecheck/tests/build, provider live-gating, and package assertion passed.
- `package` ✅.
- `wordpress-smoke` ✅ — activation, database, providers, knowledge, file ingestion, and WooCommerce knowledge smoke passed.
- Artifact `9929654030`, digest `sha256:dde8996ed71b6b3d3d1ff6ec0fdb93d932d85993e00cc8f7bbd9ab70624cdcc3`.

## Completion checklist
- [x] Task 7 implementation behavior verified on exact SHA.
- [x] Security/performance/boundary review reconciled.
- [x] Final independent Task 7 / whole-M07 closeout review: 0 unresolved Critical / Important findings.
- [x] Exact final PR head passed the full permanent CI matrix.
- [x] PR #9 merged using expected-head protection.
- [x] Fresh post-merge `main` CI passed.
- [x] Durable post-merge closeout evidence prepared.
- [ ] Verify and merge the documentation-only closeout branch, then verify its fresh `main` CI.

## Exact next unfinished action
Verify and integrate the documentation-only M07 closeout record, verify fresh `main` CI, then begin M08 — Embeddings & Vector Stores.

## Next milestone
M08 — Embeddings & Vector Stores.
