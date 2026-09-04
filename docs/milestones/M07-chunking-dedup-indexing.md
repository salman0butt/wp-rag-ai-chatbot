# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — Tasks 1-6 complete; Task 7 implementation GREEN after repeated-heading parent-lineage review fix, pending a fresh-session independent re-review.**

## Goal
Create deterministic normalized content and chunks with traceability, compatibility-safe deduplication, and an explicit pure-PHP incremental index plan that minimizes re-embedding without allowing stale indexed lineage, structural parent identity, or security metadata.

## Dependencies
M04-M06 source/document contracts.

## Design / plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md`.
- Status: **AUTO-APPROVED — SCHEDULED MODE** under `AGENTS.md` and `docs/AUTONOMOUS-DEVELOPMENT.md`.
- Architecture: pure-PHP deterministic `DocumentRecord -> normalize -> structure-aware bounded chunk -> stable hash/lineage -> compatibility-safe dedup -> incremental index plan`.
- M08 retains embedding/vector-store/provider-exact execution ownership; M09 retains queue/synchronization execution ownership.

## Acceptance criteria
- Boundary fixtures are deterministic, including tiny and huge input.
- Metadata, language, visibility, URL, source/document lineage, heading path, distinct section-parent identity, sequence, token count, chunking compatibility, and embedding compatibility are represented in the appropriate chunk/planning contracts.
- Exact unchanged content produces zero index work.
- Localized edits produce bounded embedding/upsert work rather than re-embedding every stable chunk.
- Stable chunks whose document-wide lineage changed produce explicit metadata-refresh work instead of silently retaining stale indexed lineage.
- Repeated identical heading labels remain distinct structural parent instances, while chunks within the same section share one parent key.
- Dedup never crosses visibility, language, or embedding-compatibility boundaries.
- Retrieved/source text remains untrusted literal data and is never executed.
- All public plan collections are deterministic.

## Tasks
- [x] Task 1 — Deterministic content normalization. Independent review `5104488263`: 0 Critical / 0 Important unresolved.
- [x] Task 2 — Token budget/configuration contracts. Independent review `5105069991`: 0 Critical / 0 Important unresolved.
- [x] Task 3 — Immutable chunk records and structure-aware splitting. Independent review `5105859046`: 0 Critical / 0 Important unresolved.
- [x] Task 4 — Deliberate bounded overlap. Final independent re-review `5107540703`: 0 Critical / 0 Important unresolved.
- [x] Task 5 — Compatibility-safe deduplication. Final independent re-review `5108150441`: 0 Critical / 0 Important unresolved.
- [x] Task 6 — Incremental index planning. Final fresh-session independent re-review at durable head `b2ef07e9b7d70626a30906f2648a577e8ce9e2e5` / CI `33827583643`: 0 Critical / 0 Important unresolved.
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout. **Implementation GREEN after fresh independent whole-M07 finding `5109303824`; new fresh-session independent post-fix review required.**

## Completed Task 1-6 review history
Tasks 1-6 reached strict RED/GREEN evidence, exact-SHA CI, durable documentation, and clean independent review gates. Important review-driven hardening included:

- overlap honoring injected token-counter budgets and section-instance boundaries;
- deterministic dedup canonical/alias ordering;
- planner invalidation for visibility, language, token count, chunking/embedding compatibility, and per-chunk indexed/citation metadata;
- deterministic alias/delete/upsert/unchanged presentation;
- pure-PHP/no-I/O scope throughout.

## Task 7 — Source-to-index-plan integration and milestone closeout

### Integration contract
`DocumentIndexPipeline` composes the M07 stages without performing M08/M09 execution:

`DocumentRecord -> ContentNormalizer -> StructureAwareChunker -> ChunkDeduplicator -> IncrementalIndexPlanner -> DocumentIndexResult`

The integration suite exercises WordPress-style canonical text, file-style long text, WooCommerce-style content, literal untrusted text, deterministic repeated calls, exact unchanged reuse, and localized structural changes.

### Lineage-refresh review hardening
Candidate `bc1039b0ef34fe8d1a4cb1fc5a01d76e05b3e96b` / CI `33831803910` restored bounded localized reuse by no longer forcing every stable chunk into `upsert` merely because document-wide `sourceVersion` or `documentContentHash` changed.

Fresh independent review `5109013876` reported **0 Critical / 1 Important**: stable chunks with changed document-wide lineage were emitted as `unchanged`, so a future executor could preserve stale source-version/document-hash citation lineage.

Scheduled auto-approval selected explicit deterministic `metadataRefresh` work for reusable embeddings whose document-wide lineage changed. Genuine RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185` reached PHPUnit with **309 tests / 1426 assertions / exactly 1 intended failure**. Final implementation/test head `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002` passed all four permanent jobs with PHPUnit **309/309 / 1429 assertions**, Composer audit clean, artifact `9923024640`, digest `sha256:bd24d8a2163d174f088bebc6ab85b978a61a7529cc469ab0ac88b5e4712e1933`. Same-session review `5109068463` was clean but not independent.

### Fresh repeated-heading structural-parent review
Durable head `60a0c49c52dbb854f658ba02b363ce8b0aa65ba1` was verified by CI `33835631535` with all permanent jobs green before the next fresh review.

Fresh-session independent Task 7 / whole-M07 review `5109303824` reported:

- **Critical: 0**
- **Important: 1**

Finding: the chunker correctly used deterministic internal `section_id` values to prevent overlap across repeated identical headings, but the public `parentChunkKey` omitted that section-instance identity and hashed only document key, chunking fingerprint, and heading path. Two separate `# Same` sections therefore received the same public parent key. That violated the approved parent-child semantics and could collapse distinct sections during future small-to-big retrieval/grouping.

### Strict TDD for the repeated-heading parent fix
- Genuine test-only RED `c67559f5f8f4f3ae6a7f90e9f5fe4611c3e6818f` / CI `33838410737`: production untouched; PHPStan **No errors**; PHPUnit **309 tests / 1429 assertions / exactly 1 intended failure** proving repeated-identical-heading parent keys were identical.
- Minimal GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` adds the existing deterministic descriptor `section_id` to the structural parent hash. No new public DTO field or M08/M09/provider/network/persistence behavior was added.
- Exact implementation CI `33838539319`: `php-quality` ✅, `js-quality` ✅, `package` ✅, `wordpress-smoke` ✅; PHPStan **No errors**; PHPUnit **309/309 / 1430 assertions**; Composer audit clean.
- Artifact `9924128495`, digest `sha256:0e39273a5e4df34f89cb838b1981994666423d0e9babcc7fe03855ec025f8910`.
- Same-session post-fix review `5109351740`: **0 Critical / 0 Important unresolved**, explicitly not independent because the same session found and fixed the issue.

## Current `IndexPlan` contract
The immutable plan exposes deterministic work classes:

- `upsert`: new chunks or chunks whose content/embedding/security/compatibility/per-chunk indexed metadata changed and therefore require normal indexing/embedding work;
- `metadataRefresh`: chunks whose embedding/content identity remains reusable but whose document-wide `sourceVersion` or `documentContentHash` changed and therefore require index-lineage metadata refresh without re-embedding;
- `deleteKeys`: previous canonical keys absent from the current output;
- `unchanged`: exact reusable chunks requiring no index work;
- `duplicateAliases`: deterministic duplicate -> canonical traceability.

`upsert`, `metadataRefresh`, and `unchanged` are sorted by sequence then stable chunk key; delete keys and aliases are deterministic.

## Structural parent contract
A section instance owns one deterministic `parentChunkKey`. The internal deterministic section-instance ID participates in that parent hash so repeated identical heading paths remain distinct parents. Multiple chunks emitted from the same section instance still share one parent key. Heading labels remain available separately through `headingPath`.

## Security review
M07 remains pure PHP and does not execute retrieved/source content, fetch URLs, invoke providers, read credentials, persist records, write embeddings/vectors, execute queues, or add REST/hook/WordPress runtime behavior. Visibility/language/embedding compatibility remain hard dedup/planning boundaries. Literal prompt-like or markup-like source content is treated as data.

## Performance review
Normalization/chunking are bounded by configured chunk limits and deterministic fallbacks. Dedup and planner comparison use hash maps for expected O(n) grouping/set comparison plus bounded deterministic sorting of emitted result collections. The metadata-refresh distinction prevents document-wide source-version/hash churn from causing unnecessary re-embedding while still making lineage work explicit. Section IDs are assigned in the existing single parse pass and add constant work per emitted descriptor.

## Active quality gate
Task 7 and M07 are **implementation-GREEN but not complete** until a new fresh-session independent Task 7 / whole-M07 review inspects implementation GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319` plus the latest durable documentation head and records **0 unresolved Critical / Important findings**.

That review must verify:
- complete normalize -> chunk -> dedup -> plan composition;
- exact no-op behavior;
- bounded localized embedding/upsert work;
- explicit lineage-only metadata refresh instead of stale `unchanged` state;
- repeated-identical-heading section parent separation and same-section parent sharing;
- correct invalidation for visibility, language, token count, source metadata, chunking compatibility, and embedding compatibility;
- deterministic `upsert`, `metadataRefresh`, `deleteKeys`, `unchanged`, and duplicate aliases;
- caller immutability and bounded performance;
- untrusted content remaining literal data;
- no M08/M09/provider/network/persistence/vector/WordPress-execution scope leakage.

## Completion checklist
Before M07 can be merged:
- [ ] Fresh-session independent Task 7 / whole-M07 post-fix review: 0 unresolved Critical/Important findings.
- [ ] Reconcile final acceptance/security/performance/test-matrix/spec-plan docs after that review.
- [ ] Mark PR #9 ready only after the milestone is genuinely complete.
- [ ] Verify exact final PR head with the full permanent CI matrix.
- [ ] Merge using exact expected head SHA.
- [ ] Verify fresh post-merge `main` CI.
- [ ] Only then begin M08.

## Exact next unfinished action
Perform the required **fresh-session independent Task 7 / whole-M07 re-review** anchored to implementation GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319` and the latest durable documentation head. If and only if it records 0 unresolved Critical/Important findings, proceed through final M07 documentation reconciliation, exact-final-SHA CI, PR-ready, exact-SHA merge, and post-merge-main verification.

## Next milestone
M08 — Embeddings & Vector Stores.
