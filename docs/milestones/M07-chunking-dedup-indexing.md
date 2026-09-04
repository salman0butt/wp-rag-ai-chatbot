# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — Tasks 1-6 complete; Task 7 localized chunk-count identity defect fixed, pending full exact-SHA JS verification and a fresh-session independent post-fix review.**

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

## Acceptance criteria
- Boundary fixtures are deterministic, including tiny and huge input.
- Exact unchanged content produces zero index work.
- Localized edits produce bounded embedding/upsert work instead of re-embedding downstream stable chunks.
- Global sequence remains deterministic ordering metadata but does not destabilize chunk identity when an earlier section changes chunk count.
- Stable chunks with document-wide lineage changes use explicit metadata-refresh work.
- Repeated identical heading labels remain distinct structural parent instances while same-section chunks share one parent.
- Dedup never crosses visibility, language, or embedding-compatibility boundaries.
- Retrieved/source text remains untrusted literal data.
- All public plan collections are deterministic.

## Tasks
- [x] Task 1 — Deterministic content normalization. Independent review `5104488263` clean.
- [x] Task 2 — Token budget/configuration contracts. Independent review `5105069991` clean.
- [x] Task 3 — Immutable chunk records and structure-aware splitting. Independent review `5105859046` clean.
- [x] Task 4 — Deliberate bounded overlap. Final independent review `5107540703` clean.
- [x] Task 5 — Compatibility-safe deduplication. Final independent review `5108150441` clean.
- [x] Task 6 — Incremental index planning. Final independent review at `b2ef07e9b7d70626a30906f2648a577e8ce9e2e5` / CI `33827583643` clean.
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout. Current implementation fixes fresh independent review `5109627614`; fresh post-fix independent review remains mandatory.

## Task 7 integration contract
`DocumentIndexPipeline` composes:

`DocumentRecord -> ContentNormalizer -> StructureAwareChunker -> ChunkDeduplicator -> IncrementalIndexPlanner -> DocumentIndexResult`

It remains pure PHP/no I/O and performs no provider, persistence, embedding/vector, queue, REST, hook, or WordPress runtime execution.

## Review-driven hardening

### Document-lineage refresh
Fresh independent review `5109013876` found **0 Critical / 1 Important**: document-wide lineage changes could remain in `unchanged`. Strict TDD produced RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185`, then GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002`, artifact `9923024640`, digest `sha256:bd24d8a2163d174f088bebc6ab85b978a61a7529cc469ab0ac88b5e4712e1933`. The planner now exposes deterministic `metadataRefresh` work for reusable embeddings whose document-wide lineage changed.

### Repeated-heading structural parent identity
Fresh independent review `5109303824` found **0 Critical / 1 Important**: repeated identical headings could share a public structural parent key. Strict TDD produced RED `c67559f5f8f4f3ae6a7f90e9f5fe4611c3e6818f` / CI `33838410737`, then GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319`, artifact `9924128495`, digest `sha256:0e39273a5e4df34f89cb838b1981994666423d0e9babcc7fe03855ec025f8910`. Structural parent hashes now include deterministic section-instance identity.

### Localized chunk-count identity
Fresh-session independent Task 7 / whole-M07 review `5109627614` at `42a7a8e6e4f64e8b51fb7ea9185e1176a120c7b5` found:

- **Critical: 0**
- **Important: 1**

Finding: `chunkKey` included the document-global final `sequence`. If an edited early/middle section gained or lost a chunk, later byte-identical sections shifted sequence and therefore changed keys, causing unnecessary delete/upsert/re-embedding rather than bounded localized reuse.

Strict TDD evidence:
- `c656164c34f3b37572a4b2a2f1e40f88cbee5bdb` / CI `33841997655`: invalid RED; PHPCS stopped before PHPUnit.
- `098e06197dc64804011c379297002775d17aeba0` / CI `33842060013`: invalid RED; PHPCS still stopped before PHPUnit.
- Genuine RED `ba5bda5e22cc5d164ae3fdbe41fd5bf9a717c9cc` / CI `33842200871`: production untouched; PHPStan **No errors**; PHPUnit **310 tests / 1434 assertions / exactly 1 intended failure** proving a later byte-identical Gamma section received a changed key after Beta gained a chunk.
- Production `81202fe0351155ce151ebd5cc428e792d3d203c1`: introduced section-local chunk ordinals while retaining global sequence for ordering; CI stopped on PHPCS before behavior, so not GREEN evidence.
- Formatting candidate `e15d95f3970b7350b99efc459a1c42293e3b16e4`: behavior passed the stable-key assertion but the new test used an outdated PHPUnit `assertNotContains` API, so CI `33842400244` is not GREEN evidence.
- Corrected implementation/test head `a13f6ff1edec5fc0df3c7a319343a1f4dcb24881` / CI `33842525625`: PHPStan **No errors**; PHPUnit **310/310 tests / 1435 assertions**; Composer audit clean; `php-quality` ✅, `package` ✅, `wordpress-smoke` ✅. Artifact `9925441564`, digest `sha256:dbd1f32fdcc3948cfc363612b56b29523992e9643b747f3b33fc243e035655cb`. At this documentation handoff, `js-quality` remains inside external `npm audit --audit-level=critical` following npm-registry instability, so full-matrix GREEN is not yet claimed.

## Current chunk identity contract
- `ChunkRecord::sequence` is deterministic global ordering/presentation metadata.
- `chunkKey` uses document key + chunking fingerprint + structural path + section instance + section-local chunk ordinal.
- A chunk-count change inside one structural section therefore does not renumber the identities of later stable sections.
- `parentChunkKey` remains section-instance-aware.
- Content hashes still include the appropriate source/canonical/parent/content lineage.

## Current index-plan contract
The immutable plan exposes deterministic `upsert`, `metadataRefresh`, `deleteKeys`, `unchanged`, and duplicate -> canonical aliases. `metadataRefresh`, `upsert`, and `unchanged` are ordered deterministically; visibility/language/embedding compatibility remain hard boundaries.

## Security review
M07 executes no retrieved/source content and performs no provider/network/credential/persistence/vector/queue/REST/hook/WordPress-runtime work. Prompt-like or markup-like source content remains literal data.

## Performance review
Normalization/chunking stay bounded by configured limits. Dedup/planner comparison use expected O(n) map/set work plus bounded deterministic sorting. Section-local chunk identity prevents one section's chunk-count change from cascading re-embedding across later stable sections.

## Active quality gate
Task 7 / M07 is **not complete**. The run that performed fresh independent review `5109627614` also implemented the finding and therefore cannot provide the required independent post-fix approval. Exact implementation CI `33842525625` must also finish `js-quality` successfully, or any infrastructure-only npm failure must be rerun successfully.

## Completion checklist
- [ ] Finish full exact-SHA CI for the localized chunk identity implementation.
- [ ] Reconcile design/spec and implementation plan to the section-local identity contract where they still describe global sequence as key identity.
- [ ] Verify the resulting durable documentation head with full permanent CI.
- [ ] Fresh-session independent Task 7 / whole-M07 post-fix review: 0 unresolved Critical / Important findings.
- [ ] Mark PR #9 ready only after the milestone is genuinely complete.
- [ ] Verify exact final PR head with the full permanent CI matrix.
- [ ] Merge using exact expected head SHA.
- [ ] Verify fresh post-merge `main` CI.
- [ ] Only then begin M08.

## Exact next unfinished action
Finish exact-SHA JS verification for the localized chunk identity implementation, reconcile the M07 design/spec/plan and PR body to this contract, verify that durable head, then obtain a **new fresh-session independent Task 7 / whole-M07 post-fix review**. Only a clean review may unlock M07 completion/merge.

## Next milestone
M08 — Embeddings & Vector Stores.
