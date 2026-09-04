# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **CLOSEOUT IN PROGRESS — Tasks 1-6 complete; Task 7 implementation verified. Final durable-doc CI, independent closeout review, merge, and post-merge `main` verification remain.**

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
- Localized edits produce bounded embedding/upsert work instead of re-embedding stable chunks outside the changed structural unit.
- Global sequence remains deterministic ordering metadata only.
- Changing chunk count in one section does not destabilize later byte-identical sections.
- Inserting/removing an unrelated earlier heading does not destabilize later byte-identical section identity.
- Stable chunks with document-wide lineage changes use explicit metadata-refresh work.
- Repeated identical heading paths remain distinct structural parent instances while same-section chunks share one parent.
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
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout. **Implementation verified; final durable-doc CI + independent closeout review + integration remain.**

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
Fresh-session independent Task 7 / whole-M07 review `PRR_kwDOUK8kZs8AAAABMI663g` at `42a7a8e6e4f64e8b51fb7ea9185e1176a120c7b5` found **0 Critical / 1 Important**: `chunkKey` used document-global final sequence, so a changed early/middle section could renumber later stable identities.

- Genuine RED `ba5bda5e22cc5d164ae3fdbe41fd5bf9a717c9cc` / CI `33842200871`: PHPStan clean; PHPUnit **310 tests / 1434 assertions / exactly 1 intended failure**.
- The fix keeps global `ChunkRecord::sequence` only for ordering and uses section-local chunk ordinals for stable identity.

### Unrelated-heading insertion/removal identity
Fresh-session independent Task 7 / whole-M07 review `PRR_kwDOUK8kZs8AAAABMJA_2Q` at `a13f6ff1edec5fc0df3c7a319343a1f4dcb24881` found **0 Critical / 1 Important**: a document-global section ordinal still changed later stable identities when an unrelated earlier heading was inserted/removed.

- Genuine RED `7dfaae131323839317ceddddc357cf76649cecb3` / CI `33843112724`: PHPStan clean; PHPUnit **311 tests / 1439 assertions / exactly 1 intended failure**.
- Final fix scopes the section occurrence ordinal to the same full structural heading path.
- The dedicated integration regression verifies an unrelated inserted section does not change a later byte-identical section's `parentChunkKey`/`chunkKey` and does not add that stable chunk to `upsert`.

## Final chunk identity contract
- `ChunkRecord::sequence` is deterministic global ordering/presentation metadata only.
- Section-instance identity is based on full heading path plus deterministic occurrence ordinal scoped to that same heading path.
- Stable `chunkKey` adds a section-local chunk ordinal.
- Unrelated sibling heading insertions/removals therefore do not cascade identity churn.
- Repeated identical heading paths are still distinct because their same-path occurrence ordinals differ.
- `parentChunkKey` includes the stable section-instance identity.
- Content hashes retain canonical/source/parent/content lineage as designed.

## Current index-plan contract
The immutable plan exposes deterministic `upsert`, `metadataRefresh`, `deleteKeys`, `unchanged`, and duplicate -> canonical aliases. Stable embedding/content identity with changed document-wide lineage routes to `metadataRefresh`; actual content/security/compatibility/indexed-metadata changes route to `upsert`.

## Security review
M07 executes no retrieved/source content and performs no provider/network/credential/persistence/vector/queue/REST/hook/WordPress-runtime work. Prompt-like or markup-like source content remains literal data. Visibility/language/embedding compatibility remain hard boundaries.

The JS critical-vulnerability gate remains enforced during the npm audit-service outage. The standalone audit endpoint is attempted with bounded retries; only a captured `npm ci` audit summary with no critical vulnerabilities is accepted as the outage fallback. Missing audit evidence or any critical result fails CI.

## Performance review
Normalization/chunking stay bounded by configured limits. Dedup/planner comparison use expected O(n) map/set work plus bounded deterministic sorting. Stable section-local identity and same-path-scoped section occurrence identity prevent localized structural changes from cascading delete/upsert/re-embedding across unrelated downstream sections.

## Exact verified implementation evidence
Feature head `c469d761217a1e1bdcf6438c364c661671889b69` / CI `33849180183`:

- `php-quality` ✅ — PHPStan **No errors**; PHPUnit **311/311 / 1441 assertions**; Composer audit clean.
- `js-quality` ✅ — install-time audit reports **36 vulnerabilities (26 moderate, 10 high, 0 critical)**; standalone audit endpoint unavailable; approved fail-closed outage fallback accepted the captured no-critical summary; JS lint/typecheck/test/build, provider live-gating, package assertion all pass.
- `package` ✅.
- `wordpress-smoke` ✅ — activation/database/providers/knowledge/file-ingestion/WooCommerce knowledge tests pass.
- Artifact `9927780189`, digest `sha256:02e432b10e7191867603fae5260113cd2248567a6135bd378af6cef849975a03`.

CI reliability commits used to preserve the security gate during the external npm audit outage:
- `dac8dc46760114effb94f6524edbbef84a30b86e` — retry transient audit failures.
- `35dba9209e5c716cf961a35e7455458aea723301` — bound retry latency.
- `c469d761217a1e1bdcf6438c364c661671889b69` — preserve critical audit semantics using captured install-time audit evidence only as a transient-outage fallback.

## Completion checklist
- [x] Task 7 implementation behavior verified on exact SHA.
- [x] Full implementation CI matrix green on `c469d761217a1e1bdcf6438c364c661671889b69` / `33849180183`.
- [x] Security/performance/boundary review reconciled.
- [ ] Verify final durable documentation head with full permanent CI.
- [ ] Final independent Task 7 / whole-M07 closeout review: 0 unresolved Critical / Important findings.
- [ ] Mark Task 7/M07 complete and PR #9 ready.
- [ ] Verify exact final PR head with the full permanent CI matrix.
- [ ] Merge using exact expected head SHA.
- [ ] Verify fresh post-merge `main` CI.
- [ ] Record durable closeout evidence on `main` and verify its exact CI.
- [ ] Only then begin M08.

## Exact next unfinished action
Verify the reconciled durable documentation head, obtain the final clean independent Task 7 / whole-M07 closeout review, then complete PR #9 integration and post-merge `main` verification.

## Next milestone
M08 — Embeddings & Vector Stores.
