# M07 Chunking, Deduplication & Incremental Indexing Implementation Plan

> **For agentic workers:** use applicable Superpowers skills and execute task-by-task with genuine RED/GREEN evidence, exact-SHA CI, independent review, and durable repository handoff.

**Goal:** Transform canonical `DocumentRecord` values into deterministic structure-aware chunks and pure incremental index plans that avoid unnecessary embedding work.

**Architecture:** WordPress-independent PHP pipeline: normalize text -> deterministic structure-aware bounded splitting -> immutable stable chunk records -> compatibility-safe dedup -> old/new incremental plan. M07 performs no embedding, vector, queue, persistence, network, hook, REST, or WordPress runtime side effects.

**Tech Stack:** PHP 8.2+, PHPUnit 10, PHPStan, PHPCS/WPCS, existing `DocumentRecord`/`DocumentHasher`.

**Spec:** `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md`

Status: **AUTO-APPROVED — SCHEDULED MODE**

## Global constraints

- `DocumentRecord` is the canonical input.
- Source content/metadata remains untrusted literal data.
- Token counting is deterministic and injectable; provider-exact behavior remains M08.
- Every behavior change requires genuine RED before production code.
- Every task must pass its independent review gate before later milestone behavior proceeds.
- Stable chunk identity must not depend on document-global sequence shifts or unrelated earlier heading insertions/removals.

## Task 1 — Deterministic normalization

**Implementation:** `ContentNormalizer::normalize(string): string`.

Required evidence: CRLF/CR, BOM, trailing whitespace, blank-run collapse, trim, instruction-like text preservation, idempotence; strict RED/GREEN; independent review.

## Task 2 — Token/config contracts

**Implementation:** `TokenCounter`, `LexicalTokenCounter`, `ChunkingConfig`.

Required evidence: ASCII/Unicode/punctuation/empty counts; config bounds; deterministic fingerprint; invalid UTF-8 fail-closed; strict RED/GREEN; independent review.

## Task 3 — Immutable chunks and structure-aware splitting

**Implementation:** `ChunkRecord`, `ChunkingException`, `StructureAwareChunker`.

Required behavior:

- ATX heading sections and blank-line paragraphs;
- sentence then lexical/code-point-safe oversized fallback;
- deterministic global `ChunkRecord::sequence` for final presentation order only;
- deterministic section-instance identity = full structural heading path + occurrence ordinal scoped to that same heading path;
- deterministic section-local chunk ordinal;
- `chunkKey = hash(document key + chunking fingerprint + structural path + section instance + section-local ordinal)`;
- global sequence is not stable chunk identity;
- unrelated earlier heading insertion/removal does not destabilize later unrelated section identity;
- repeated identical heading paths remain distinct via same-path occurrence ordinals;
- `parentChunkKey` includes section instance so repeated identical headings remain distinct;
- stable downstream chunk keys when an earlier section changes chunk count.

Required evidence includes headings, paragraphs, oversized blocks, zero/tiny/huge content, metadata/visibility propagation, repeated headings, stable parent identities, stable downstream identities, unrelated-heading insertion/removal, deterministic repeated calls, strict RED/GREEN, independent review.

## Task 4 — Deliberate bounded overlap

Overlap applies only between adjacent chunks from the same section instance. Both overlap and final max-token budgets use the injected counter; new content is preserved. Verify repeated-heading isolation, Unicode/fail-closed behavior, bounded termination/performance, strict RED/GREEN, independent review.

## Task 5 — Compatibility-safe dedup

Deduplicate with deterministic fingerprinting over canonical content plus hard compatibility/privacy boundaries. Canonical selection and aliases are deterministic; aliases point duplicate -> canonical. Verify public/private, language, embedding compatibility, immutability, ordering, strict RED/GREEN, independent review.

## Task 6 — Incremental index planning

`IndexPlan` exposes deterministic `upsert`, `metadataRefresh`, `deleteKeys`, `unchanged`, and duplicate aliases.

Planner must invalidate reuse for real per-chunk content/security/compatibility/indexed-metadata changes while preserving reusable embeddings for document-wide lineage-only changes through explicit `metadataRefresh`. Verify visibility, language, token count, source metadata, chunking/embedding compatibility, exact no-op, additions/deletions/localized changes, deterministic ordering, strict RED/GREEN, independent review.

## Task 7 — Source-to-index-plan integration and milestone closeout

**Implementation:** `DocumentIndexPipeline`, `DocumentIndexResult`, integration tests, durable M07 docs.

Required integration fixtures:

- WordPress-style canonical heading/paragraph text;
- file-style long text;
- WooCommerce-style catalog text;
- prompt/markup-like text remains literal data;
- deterministic repeated calls;
- exact unchanged content produces zero index work;
- localized content edits produce bounded upsert/embedding work plus explicit lineage metadata refresh where needed;
- localized chunk-count change: an early/middle section may gain/lose chunks while a later byte-identical section retains identity and avoids `upsert`;
- unrelated heading insertion/removal: a later byte-identical section retains parent/chunk identity and avoids `upsert`;
- repeated-identical-heading sections remain distinct parents;
- large-document bounded completion without machine-specific timing assertions.

### Task 7 quality sequence

1. Write/verify genuine RED on the exact test-only SHA.
2. Implement the minimum production behavior.
3. Run exact implementation CI: `php-quality`, `js-quality`, `package`, `wordpress-smoke`; record PHPUnit/PHPStan/audits/artifact digest.
4. Same-session implementation review may provide feedback but does not replace a required fresh independent review after production-code fixes.
5. Reconcile `docs/progress/STATUS.md`, milestone ledger, design spec, plan, security/test matrices where applicable, and PR description.
6. Verify the exact durable documentation head with the full permanent CI matrix.
7. Perform the final independent Task 7 / whole-M07 review and require 0 unresolved Critical / Important findings.
8. Only then mark Task 7/M07 complete and PR #9 ready.
9. Verify exact final PR head with full CI.
10. Merge using exact expected head SHA.
11. Verify fresh post-merge `main` CI.
12. Record durable closeout evidence on `main`, verify its exact CI, then begin M08.

## Review-driven Task 7 evidence

- Lineage refresh: review `5109013876`; RED `9fa0fe7eff90fb21aace4000445acdb2c0891ce8` / CI `33834820185`; GREEN `3bf83a1b9b5dee2df2440ff55471b2bf39ba22c0` / CI `33835032002`.
- Repeated-heading parent identity: review `5109303824`; RED `c67559f5f8f4f3ae6a7f90e9f5fe4611c3e6818f` / CI `33838410737`; GREEN `a7e44261d5743db9759c131f2fa5b29cb42fead4` / CI `33838539319`.
- Localized chunk-count identity: fresh review `PRR_kwDOUK8kZs8AAAABMI663g` found 0 Critical / 1 Important. Genuine RED `ba5bda5e22cc5d164ae3fdbe41fd5bf9a717c9cc` / CI `33842200871` proved a later byte-identical section changed key after an earlier section gained a chunk. The fix moved stable identity to section-local ordinals while global sequence remained presentation order only.
- Unrelated-heading identity: fresh review `PRR_kwDOUK8kZs8AAAABMJA_2Q` found 0 Critical / 1 Important. Genuine RED `7dfaae131323839317ceddddc357cf76649cecb3` / CI `33843112724` proved inserting an unrelated earlier heading changed a later stable section identity. The final fix scopes occurrence ordinals to the same full heading path.

## Exact verified implementation head

`c469d761217a1e1bdcf6438c364c661671889b69` / CI `33849180183` is the verified Task 7 implementation/infrastructure head:

- PHPStan: **No errors**.
- PHPUnit: **311/311 tests, 1441 assertions**.
- Composer audit: no security vulnerability advisories.
- JS quality: dependency install/audit, lint/typecheck/test/build, provider live-gating, and package assertion pass.
- Package and WordPress smoke pass.
- Artifact `9927780189`, digest `sha256:02e432b10e7191867603fae5260113cd2248567a6135bd378af6cef849975a03`.

The npm standalone audit service was externally unavailable. CI hardening commits `dac8dc46760114effb94f6524edbbef84a30b86e`, `35dba9209e5c716cf961a35e7455458aea723301`, and `c469d761217a1e1bdcf6438c364c661671889b69` preserve the critical-vulnerability gate with bounded standalone attempts and a fail-closed fallback to the captured `npm ci` audit summary only when the endpoint is unavailable. Verified install-time evidence reported **36 vulnerabilities (26 moderate, 10 high, 0 critical)**; any critical result or missing audit evidence still fails CI.

## Milestone boundary

Embeddings/vector stores remain M08. Queue/synchronization execution remains M09. M07 closes only after durable-doc exact-SHA green CI, clean final independent whole-M07 review, PR-ready/final-head verification, exact-head merge, post-merge `main` CI, and durable main closeout evidence.
