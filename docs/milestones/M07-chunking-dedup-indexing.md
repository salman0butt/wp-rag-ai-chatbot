# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — Tasks 1-6 complete; Task 7 implementation GREEN after a whole-M07 lineage-refresh review fix, pending a fresh-session independent re-review.**

## Goal
Create deterministic normalized content and chunks with traceability, compatibility-safe deduplication, and an explicit pure-PHP incremental index plan that minimizes re-embedding without allowing stale indexed lineage or security metadata.

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
- Metadata, language, visibility, URL, source/document lineage, heading path, parent identity, sequence, token count, chunking compatibility, and embedding compatibility are represented in the appropriate chunk/planning contracts.
- Exact unchanged content produces zero index work.
- Localized edits produce bounded embedding/upsert work rather than re-embedding every stable chunk.
- Stable chunks whose document-wide lineage changed produce explicit metadata-refresh work instead of silently retaining stale indexed lineage.
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
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout. **Implementation GREEN after independent whole-M07 review finding `5109013876`; fresh-session independent post-fix review required.**

## Completed Task 1-6 review history
Tasks 1-6 reached strict RED/GREEN evidence, exact-SHA CI, durable documentation, and clean independent review gates. The detailed historical RED/GREEN lineage remains available in Git history and `docs/progress/STATUS.md`. Important review-driven hardening included:

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

### Recovered Task 7 candidate
Candidate `bc1039b0ef34fe8d1a4cb1fc5a01d76e05b3e96b` / CI `33831803910` restored bounded localized reuse by no longer forcing every stable chunk into `upsert` merely because document-wide `sourceVersion` or `documentContentHash` changed.

### Fresh independent Task 7 / whole-M07 review
Review `5109013876` reported:

- **Critical: 0**
- **Important: 1**

Finding: localized re-embedding was bounded, but stable chunks with changed document-wide lineage were emitted as `unchanged`. Because `IndexPlan::unchanged` means no index work, a future executor could preserve stale source-version/document-hash citation lineage.

Three designs were considered under scheduled auto-approval:
1. restore source-version/document-hash equality in ordinary upsert reuse — rejected because it re-embeds every stable chunk after ordinary document edits;
2. remove document-wide lineage from the chunk/index contract — rejected because it conflicts with the approved M07 traceability contract;
3. **selected:** expose explicit deterministic metadata-only refresh work for reusable embeddings whose document-wide lineage changed.

### Strict review-fix TDD
- First test-only attempt `fe2c61a038c4473e385066412c61137539a8e3ce` / CI `33834769874` is **not valid RED** because PHPCS stopped before PHPUnit on test-helper documentation.
- Corrected genuine RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185`: PHPCS/PHPStan clean; PHPUnit **309 tests / 1426 assertions / exactly 1 intended failure**, proving changed document-wide lineage was incorrectly classified as `unchanged` with no explicit work.
- Production candidate `3b715b0b0d0b27c45d9930026eec45989ee7c64e` introduced deterministic `metadataRefresh`, but CI `33834951323` is **not accepted as GREEN** because an older localized integration expectation still required stable chunks to be in `unchanged`.
- Systematic root-cause analysis confirmed the production behavior matched the reviewed contract and that the integration expectation was stale.
- Integration reconciliation `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` requires exact unchanged documents to have no `metadataRefresh`, while localized source-version/document-hash churn has bounded `upsert` plus explicit `metadataRefresh` for reusable stable chunks.

### Exact implementation GREEN
Exact head `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0`, CI `33835032002`:

- `php-quality` ✅
- `js-quality` ✅
- `package` ✅
- `wordpress-smoke` ✅
- PHPStan: **No errors**
- PHPUnit: **309/309 tests, 1429 assertions**
- Composer audit: **clean**
- Artifact `9923024640`
- Digest `sha256:bd24d8a2163d174f088bebc6ab85b978a61a7529cc469ab0ac88b5e4712e1933`

Same-session post-fix review `5109068463`: **0 Critical / 0 Important unresolved**, but it is explicitly not independent because the same session discovered and implemented the fix.

## Current `IndexPlan` contract
The immutable plan now exposes deterministic work classes:

- `upsert`: new chunks or chunks whose content/embedding/security/compatibility/per-chunk indexed metadata changed and therefore require normal indexing/embedding work;
- `metadataRefresh`: chunks whose embedding/content identity remains reusable but whose document-wide `sourceVersion` or `documentContentHash` changed and therefore require index-lineage metadata refresh without re-embedding;
- `deleteKeys`: previous canonical keys absent from current output;
- `unchanged`: exact reusable chunks requiring no index work;
- `duplicateAliases`: deterministic duplicate -> canonical traceability.

`upsert`, `metadataRefresh`, and `unchanged` are sorted by sequence then stable chunk key; delete keys and aliases are deterministic.

## Security review
M07 remains pure PHP and does not execute retrieved/source content, fetch URLs, invoke providers, read credentials, persist records, write embeddings/vectors, execute queues, or add REST/hook/WordPress runtime behavior. Visibility/language/embedding compatibility remain hard dedup/planning boundaries. Literal prompt-like or markup-like source content is treated as data.

## Performance review
Normalization/chunking are bounded by configured chunk limits and deterministic fallbacks. Dedup and planner comparison use hash maps for expected O(n) grouping/set comparison plus bounded deterministic sorting of emitted result collections. The metadata-refresh distinction prevents document-wide source-version/hash churn from causing unnecessary re-embedding while still making lineage work explicit.

## Active quality gate
Task 7 and M07 are **implementation-GREEN but not complete** until a new fresh-session independent Task 7 / whole-M07 review inspects implementation GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002` and records **0 unresolved Critical / Important findings**.

That review must verify:
- complete normalize -> chunk -> dedup -> plan composition;
- exact no-op behavior;
- bounded localized embedding/upsert work;
- explicit lineage-only metadata refresh instead of stale `unchanged` state;
- correct invalidation for visibility, language, token count, source metadata, chunking compatibility, and embedding compatibility;
- deterministic `upsert`, `metadataRefresh`, `deleteKeys`, `unchanged`, and duplicate aliases;
- caller immutability and bounded performance;
- untrusted content remaining literal data;
- no M08/M09/provider/network/persistence/vector/WordPress-execution scope leakage.

## Completion checklist
Before M07 can be merged:
- [ ] Fresh-session independent Task 7 / whole-M07 post-fix review: 0 unresolved Critical/Important findings.
- [ ] Reconcile final acceptance/security/performance/test-matrix docs after that review.
- [ ] Mark PR #9 ready only after the milestone is genuinely complete.
- [ ] Verify exact final PR head with the full permanent CI matrix.
- [ ] Merge using exact expected head SHA.
- [ ] Verify fresh post-merge `main` CI.
- [ ] Only then begin M08.

## Exact next unfinished action
Perform the required **fresh-session independent Task 7 / whole-M07 re-review** anchored to implementation GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002` and the latest durable documentation head. If and only if it records 0 unresolved Critical/Important findings, proceed through the final M07 documentation, exact-final-SHA CI, PR-ready, merge, and post-merge-main gates.

## Next milestone
M08 — Embeddings & Vector Stores.
