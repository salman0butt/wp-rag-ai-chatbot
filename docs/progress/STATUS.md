# Global Status

- Completed milestones on `main`: **M00-M06**.
- Current milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — CLOSEOUT IN PROGRESS**.
- Current verified `main`: `747733a92c23d411ccba2592d5cb8c7858b95a03`.
- Latest verified pre-M07 `main` CI: `33770388757` — all permanent jobs passed.
- Feature branch: `feat/m07-chunking-dedup-indexing`.
- Draft PR: **#9 — `feat: build M07 chunking dedup incremental indexing`**.
- Design/spec and implementation plan: **AUTO-APPROVED — SCHEDULED MODE**.
- Architecture: canonical `DocumentRecord` -> deterministic normalization -> structure-aware bounded chunking -> stable hashes/lineage -> compatibility-safe deduplication -> pure incremental index plan.
- M08 owns embeddings/vector stores/provider-exact execution. M09 owns queue/synchronization execution.

## M07 task status

- Task 1 — Deterministic content normalization: **COMPLETE**. Independent review `5104488263`: 0 Critical / 0 Important unresolved.
- Task 2 — Token budget/configuration contracts: **COMPLETE**. Independent review `5105069991`: clean.
- Task 3 — Immutable chunk records and structure-aware splitting: **COMPLETE**. Independent review `5105859046`: clean.
- Task 4 — Deliberate bounded overlap: **COMPLETE**. Final independent review `5107540703`: clean.
- Task 5 — Compatibility-safe deduplication: **COMPLETE**. Final independent review `5108150441`: clean.
- Task 6 — Incremental index planning: **COMPLETE**. Final independent review at `b2ef07e9b7d70626a30906f2648a577e8ce9e2e5` / CI `33827583643`: clean.
- Task 7 — Source-to-index-plan integration and M07 closeout: **IMPLEMENTATION VERIFIED; final durable-doc CI + final independent closeout review + merge/post-merge verification remain**.

## Task 7 review-driven hardening

### Document-lineage metadata refresh

Independent review `5109013876`: 0 Critical / 1 Important. Stable embeddings whose document-wide lineage changed could otherwise remain `unchanged` with stale citation metadata.

- Genuine RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185`.
- GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002`.
- Artifact `9923024640`, digest `sha256:bd24d8a2163d174f088bebc6ab85b978a61a7529cc469ab0ac88b5e4712e1933`.
- `IndexPlan` now exposes deterministic `metadataRefresh` work for lineage-only changes.

### Repeated-heading public parent identity

Independent review `5109303824`: 0 Critical / 1 Important. Separate repeated identical headings could share one public parent key.

- Genuine RED `c67559f5f8f4f3ae6a7f90e9f5fe4611c3e6818f` / CI `33838410737`.
- GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319`.
- Artifact `9924128495`, digest `sha256:0e39273a5e4df34f89cb838b1981994666423d0e9babcc7fe03855ec025f8910`.
- Public `parentChunkKey` now includes deterministic section-instance identity.

### Localized chunk-count identity

Fresh whole-M07 review `PRR_kwDOUK8kZs8AAAABMI663g` at `42a7a8e6e4f64e8b51fb7ea9185e1176a120c7b5`: 0 Critical / 1 Important. Global final sequence inside `chunkKey` caused unchanged downstream sections to churn when an earlier section gained/lost chunks.

- Genuine RED `ba5bda5e22cc5d164ae3fdbe41fd5bf9a717c9cc` / CI `33842200871`: PHPStan clean; PHPUnit 310 tests / 1434 assertions / exactly 1 intended failure.
- Fix introduced section-local chunk ordinals while keeping global `ChunkRecord::sequence` only as presentation/order metadata.

### Unrelated-heading insertion stability

Fresh whole-M07 review `PRR_kwDOUK8kZs8AAAABMJA_2Q` at `a13f6ff1edec5fc0df3c7a319343a1f4dcb24881`: 0 Critical / 1 Important. A document-global section ordinal still changed later stable identities when an unrelated earlier heading was inserted or removed.

- Genuine RED `7dfaae131323839317ceddddc357cf76649cecb3` / CI `33843112724`: PHPStan clean; PHPUnit 311 tests / 1439 assertions / exactly 1 intended failure.
- Final structure fix scopes section occurrence ordinals to the same full heading path. Therefore unrelated heading insertion/removal does not renumber later stable section identities, while repeated identical heading paths remain distinct.
- Dedicated integration regression verifies a byte-identical later section retains both `parentChunkKey` and `chunkKey` and avoids `upsert`.

## Current Task 7 contracts

- Global `ChunkRecord::sequence` is deterministic presentation/order metadata only.
- Stable section identity is full structural heading path + occurrence ordinal scoped to that same heading path.
- Stable chunk identity adds the section-local chunk ordinal.
- Repeated identical heading paths remain separate section instances; chunks within one instance share one parent.
- Overlap never crosses section instances and obeys both injected-counter overlap and final max-token budgets.
- `IndexPlan` deterministically separates `upsert`, `metadataRefresh`, `deleteKeys`, `unchanged`, and duplicate -> canonical aliases.
- Stable embeddings with changed document-wide lineage use `metadataRefresh`; actual content/security/compatibility/indexed-metadata changes remain `upsert`.
- Visibility, language, token-count, source metadata, chunking compatibility, and embedding compatibility boundaries remain explicit.
- Source/retrieved content is literal untrusted data; M07 performs no provider, persistence, vector/embedding execution, queue, REST, hook, or WordPress-runtime work.

## Exact verified implementation head

Feature head `c469d761217a1e1bdcf6438c364c661671889b69` / CI `33849180183`:

- `php-quality` ✅ — PHPStan **No errors**; PHPUnit **311/311 tests, 1441 assertions**; Composer audit: no security vulnerability advisories.
- `js-quality` ✅ — dependency install/audit gate, JS lint/typecheck/test/build, provider live-gating, and package assertion all pass.
- `package` ✅.
- `wordpress-smoke` ✅ — activation, database, providers, knowledge, file ingestion, and WooCommerce knowledge smoke tests pass.
- Artifact `9927780189`, digest `sha256:02e432b10e7191867603fae5260113cd2248567a6135bd378af6cef849975a03`.

### npm audit infrastructure hardening

The npm standalone audit endpoint repeatedly returned 503/timeouts while `npm ci` still completed and audited the installed dependency graph. CI was hardened without disabling the critical-vulnerability gate:

- `dac8dc46760114effb94f6524edbbef84a30b86e` — retry recognized transient audit failures.
- `35dba9209e5c716cf961a35e7455458aea723301` — bound each standalone audit attempt.
- `c469d761217a1e1bdcf6438c364c661671889b69` — preserve fail-closed critical gating by falling back only on the captured `npm ci` audit summary after bounded standalone endpoint failures.

On verified CI `33849180183`, `npm ci` audited 1799 packages and reported **36 vulnerabilities: 26 moderate, 10 high, 0 critical**. The standalone endpoint remained unavailable, the no-critical install-time summary was accepted as the approved outage fallback, and all subsequent JS checks passed. A real critical audit result or missing auditable summary still fails CI.

## Active completion gate

M07 is not yet marked complete because the repository requires the final durable documentation head to pass exact-SHA CI and then the final independent Task 7 / whole-M07 closeout review to have 0 unresolved Critical / Important findings. After that, PR #9 may be marked ready, exact-final-head CI must remain green, the PR may be merged using the expected head SHA, and fresh post-merge `main` CI must pass.

## Exact next unfinished action

1. Verify this durable documentation head with all permanent CI jobs.
2. Record the final independent Task 7 / whole-M07 closeout review and require 0 unresolved Critical / Important findings.
3. Mark Task 7/M07 complete in the feature-branch ledger and PR #9 ready.
4. Confirm exact final PR head CI and no blocking review threads.
5. Merge PR #9 using exact expected-head protection.
6. Verify fresh post-merge `main` CI.
7. Write final main closeout evidence and verify that exact closeout head before beginning M08.
