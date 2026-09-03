# M07 Chunking, Deduplication & Incremental Indexing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform canonical `DocumentRecord` values into deterministic structure-aware chunks and pure incremental index plans that avoid unnecessary embedding work.

**Architecture:** A WordPress-independent PHP pipeline normalizes text, applies deterministic budgeted structure-aware splitting, creates immutable chunk records, deduplicates them by compatibility-safe fingerprints, and compares old/new canonical chunks to produce an index plan. M07 performs no embedding, vector, queue, or persistence side effects.

**Tech Stack:** PHP 8.2+, PHPUnit 10, PHPStan, PHPCS/WPCS, existing `DocumentRecord`/`DocumentHasher`.

**Spec:** `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md`

## Global Constraints

- Server runtime remains PHP/WordPress; no mandatory Node/Python/external service.
- `DocumentRecord` is the canonical input contract.
- M07 must not generate embeddings, write vectors, or implement the M09 job queue.
- Source text/metadata remains untrusted data and is never executed/interpreted as policy.
- Default lexical token budget is deterministic and injectable; provider-exact tokenization remains M08-compatible.
- Every behavior change requires genuine RED before production implementation.
- Every task requires independent review before the next behavior task begins.

---

### Task 1: Deterministic content normalization

**Files:**
- Create: `src/Indexing/Normalization/ContentNormalizer.php`
- Create: `tests/Unit/Indexing/Normalization/ContentNormalizerTest.php`

**Interfaces:**
- Produces: `ContentNormalizer::normalize(string $content): string`

- [ ] **Step 1: Write failing fixtures** covering CRLF/CR, leading UTF-8 BOM, trailing horizontal whitespace, 3+ blank-line collapse, leading/trailing whitespace, instruction-like text preservation, and idempotence.

```php
self::assertSame(
    "# Title\n\nKeep <script>literal</script> text\n\nEnd",
    ContentNormalizer::normalize("\xEF\xBB\xBF# Title  \r\n\r\n\r\nKeep <script>literal</script> text\t\r\n\r\nEnd  ")
);
self::assertSame($normalized, ContentNormalizer::normalize($normalized));
```

- [ ] **Step 2: Run focused test on the test-only commit** via authoritative GitHub Actions. Expected: PHPUnit failure because `ContentNormalizer` does not exist; PHPStan/PHPCS must reach the intended behavioral failure rather than fail for test syntax/style.
- [ ] **Step 3: Implement minimum normalizer**: strip one leading UTF-8 BOM; normalize line endings; `rtrim($line, " \t")`; collapse `\n{3,}` to `\n\n`; trim whole content.
- [ ] **Step 4: Run focused + full PHP verification**. Expected: Task 1 tests green plus all existing tests green.
- [ ] **Step 5: Independent review** for meaning preservation, untrusted-content handling, UTF-8/BOM edge behavior, and M04–M06 compatibility.
- [ ] **Step 6: Commit/update durable M07 evidence** only after review is clean.

### Task 2: Token budget/configuration contracts

**Files:**
- Create: `src/Indexing/Chunking/TokenCounter.php`
- Create: `src/Indexing/Chunking/LexicalTokenCounter.php`
- Create: `src/Indexing/Chunking/ChunkingConfig.php`
- Create: `tests/Unit/Indexing/Chunking/LexicalTokenCounterTest.php`
- Create: `tests/Unit/Indexing/Chunking/ChunkingConfigTest.php`

**Interfaces:**
- `TokenCounter::count(string $text): int`
- `new ChunkingConfig(int $maxTokens = 512, int $overlapTokens = 64, string $chunkingVersion = 'm07-v1', ?string $embeddingCompatibilityKey = null)`
- `ChunkingConfig::fingerprint(): string`

- [ ] **Step 1: RED tests** for ASCII/Unicode/punctuation/empty token counts and config bounds (`maxTokens` 32–4096; overlap >=0 and <=25% max; non-empty version; null-or-non-empty compatibility key; deterministic fingerprint).
- [ ] **Step 2: Verify RED** on exact test SHA.
- [ ] **Step 3: Implement minimal contracts** using Unicode regex `/[\p{L}\p{N}]+|[^\s\p{L}\p{N}]/u`; fail closed on invalid UTF-8.
- [ ] **Step 4: Verify GREEN and full PHP suite.**
- [ ] **Step 5: Independent review** for deterministic cross-source behavior and no provider coupling.

### Task 3: Immutable chunk records and structure-aware splitting

**Files:**
- Create: `src/Indexing/Chunking/ChunkRecord.php`
- Create: `src/Indexing/Chunking/ChunkingException.php`
- Create: `src/Indexing/Chunking/StructureAwareChunker.php`
- Create: `tests/Unit/Indexing/Chunking/ChunkRecordTest.php`
- Create: `tests/Unit/Indexing/Chunking/StructureAwareChunkerTest.php`

**Interfaces:**
- `StructureAwareChunker::__construct(TokenCounter $counter, ChunkingConfig $config)`
- `StructureAwareChunker::chunks(DocumentRecord $document): array<int, ChunkRecord>`
- `ChunkRecord` properties exactly as listed in the M07 design spec.

- [ ] **Step 1: RED fixtures** for headings, paragraphs, sentence fallback, one oversized sentence, zero/tiny content, stable order/keys/hashes, metadata/visibility copying, heading paths, parent keys, and deterministic repeated calls.
- [ ] **Step 2: Verify RED** because chunk types/implementation are absent.
- [ ] **Step 3: Implement section parser** recognizing only ATX `#`–`######` headings already present in canonical text; blank-line blocks are paragraphs.
- [ ] **Step 4: Implement recursive boundary splitting** in section → paragraph → sentence → Unicode lexical-unit order and assign zero-based final sequences only after boundaries are stable.
- [ ] **Step 5: Implement deterministic keys/hashes** through `DocumentHasher`; never use timestamps/randomness.
- [ ] **Step 6: Verify GREEN/full suite, then independent review.**

### Task 4: Deliberate bounded overlap

**Files:**
- Modify: `src/Indexing/Chunking/StructureAwareChunker.php`
- Modify: `tests/Unit/Indexing/Chunking/StructureAwareChunkerTest.php`

**Interfaces:** unchanged from Task 3.

- [ ] **Step 1: RED tests** proving overlap is at most configured tokens, applies only between adjacent chunks with the same structural parent, never crosses sections/documents, and always leaves room for new content.
- [ ] **Step 2: Verify RED** on exact SHA.
- [ ] **Step 3: Implement minimal trailing-unit overlap** using the injected counter/budget rules.
- [ ] **Step 4: Verify GREEN/full suite and independent review** for infinite-loop/quadratic-risk issues.

### Task 5: Compatibility-safe deduplication

**Files:**
- Create: `src/Indexing/Dedup/ChunkDeduplicationResult.php`
- Create: `src/Indexing/Dedup/ChunkDeduplicator.php`
- Create: `tests/Unit/Indexing/Dedup/ChunkDeduplicatorTest.php`

**Interfaces:**
- `ChunkDeduplicator::deduplicate(array $chunks): ChunkDeduplicationResult`
- Result exposes ordered `canonicalChunks` plus `duplicateAliases` (`duplicate chunk key => canonical chunk key`).

- [ ] **Step 1: RED tests** for identical content dedup, earliest-sequence canonical selection, deterministic aliases, public/private isolation, language isolation, embedding-compatibility isolation, and no mutation of input records.
- [ ] **Step 2: Verify RED.**
- [ ] **Step 3: Implement O(n) hash-map dedup** with `DocumentHasher` fingerprint over normalized content + language + visibility + compatibility key.
- [ ] **Step 4: Verify GREEN/full suite and independent privacy review.**

### Task 6: Incremental index planning

**Files:**
- Create: `src/Indexing/Planning/IndexPlan.php`
- Create: `src/Indexing/Planning/IncrementalIndexPlanner.php`
- Create: `tests/Unit/Indexing/Planning/IncrementalIndexPlannerTest.php`

**Interfaces:**
- `IncrementalIndexPlanner::plan(array $previousChunks, ChunkDeduplicationResult $current): IndexPlan`
- `IndexPlan` exposes deterministically ordered `upsert`, `deleteKeys`, `unchanged`, and `duplicateAliases`.

- [ ] **Step 1: RED tests** for initial index, exact no-op, one localized changed chunk, removed chunks, new chunks, chunking-fingerprint change, embedding-compatibility change, and deterministic ordering.
- [ ] **Step 2: Verify RED.**
- [ ] **Step 3: Implement O(n) maps by chunk key**; unchanged requires same content hash, chunking fingerprint, and compatibility key; changed/replaced current keys are upserts; absent old keys are deletes.
- [ ] **Step 4: Verify GREEN/full suite and independent review** focused on zero unnecessary re-embed work.

### Task 7: Source-to-index-plan integration and milestone closeout

**Files:**
- Create: `src/Indexing/DocumentIndexPipeline.php`
- Create: `src/Indexing/DocumentIndexResult.php`
- Create: `tests/Integration/Indexing/DocumentIndexPipelineTest.php`
- Modify: `docs/milestones/M07-chunking-dedup-indexing.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/progress/TEST-MATRIX.md` if its existing structure tracks this layer.
- Modify: `docs/progress/SECURITY.md` if its existing structure tracks milestone reviews.
- Modify: `docs/FEATURE-MATRIX.md` only after M07 completion.

**Interfaces:**
- `DocumentIndexPipeline::__construct(ContentNormalizer $normalizer, StructureAwareChunker $chunker, ChunkDeduplicator $deduplicator, IncrementalIndexPlanner $planner)`
- `DocumentIndexPipeline::plan(DocumentRecord $document, array $previousChunks = []): DocumentIndexResult`

- [ ] **Step 1: RED integration fixtures** representing WordPress-style heading/paragraph content, file-style long text, and WooCommerce-style catalog text; prove deterministic metadata propagation, zero work on unchanged content, and bounded affected work for localized changes.
- [ ] **Step 2: Verify RED.**
- [ ] **Step 3: Implement composition service only**; do not add persistence, embeddings, vector calls, queues, hooks, or REST.
- [ ] **Step 4: Add large-document regression fixture** asserting deterministic bounded chunk count and completion without machine-specific time assertions.
- [ ] **Step 5: Full security/privacy/performance review** including untrusted prompt-like content preservation, visibility boundaries, linear map behavior, and large-block fallback termination.
- [ ] **Step 6: Independent whole-M07 review**; fix every Critical/Important finding with regression TDD where behavior changes.
- [ ] **Step 7: Run exact-final-SHA full CI** (`php-quality`, `js-quality`, `package`, `wordpress-smoke`) and record artifact digest.
- [ ] **Step 8: Reconcile durable docs, finish PR, merge only the exact verified SHA, then verify fresh post-merge `main` CI before marking M07 complete.**

## Plan self-review

- Spec coverage: normalization, token budget, structure-aware recursion, overlap, lineage, hashes, dedup, compatibility boundaries, incremental planning, integration, performance, security, review, CI, docs, merge and post-merge verification are all mapped to tasks.
- Placeholder scan: no TODO/TBD or unspecified implementation steps remain.
- Type consistency: `DocumentRecord` input, `ChunkRecord`, `ChunkDeduplicationResult`, `IndexPlan`, and `DocumentIndexResult` flow consistently from Tasks 1–7.
- Milestone boundaries: embeddings/vector stores remain M08; queues/sync workers remain M09.

Status: **AUTO-APPROVED — SCHEDULED MODE**
