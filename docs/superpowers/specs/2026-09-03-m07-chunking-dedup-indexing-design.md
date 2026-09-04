# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing Design

Status: **AUTO-APPROVED — SCHEDULED MODE**

## Context

M04–M06 emit canonical immutable `DocumentRecord` values containing stable source identity, normalized content, metadata, source version/hash, language, visibility, canonical URL, and timestamps. M07 transforms those documents into deterministic chunks and an incremental index plan. M08 owns embeddings/vector stores; M09 owns asynchronous synchronization execution.

## Goals

- Normalize document text deterministically without interpreting or executing it.
- Prefer structure boundaries: heading/section, paragraph, sentence, then bounded lexical fallback.
- Enforce a configurable deterministic token budget with deliberate bounded overlap.
- Emit immutable chunk records with stable identity, content hashes, sequence/parent/heading lineage, citation/source metadata, chunking-version metadata, and embedding compatibility metadata.
- Preserve **localized identity stability**: changing the number of chunks in one structural section must not renumber the chunk identities of later byte-identical sections.
- Deduplicate identical compatible canonical chunk payloads deterministically.
- Produce bounded deterministic `upsert`, `metadataRefresh`, `delete`, and `unchanged` decisions so unchanged embeddings can be reused without stale document lineage.
- Keep the whole pipeline pure PHP and WordPress-independent.

## Non-goals

Provider/model-specific exact tokenizers; embedding generation/batching; vector-store execution; queue/lease/retry execution; re-parsing source formats already normalized by M04–M06; trusting retrieved text as instructions.

## Components

### `ContentNormalizer`

Pure deterministic normalization: CRLF/CR -> LF, beginning BOM removal, trailing horizontal-whitespace stripping, outer trim, and collapse of 3+ blank lines to two. It preserves all non-whitespace content, heading markers, punctuation, markup-like text, and instruction-like text verbatim.

### `TokenCounter`

```php
public function count(string $text): int;
```

The default `LexicalTokenCounter` counts Unicode letter/number runs plus individual non-whitespace punctuation/symbol units. M08 may inject a provider-aware counter without redesigning M07 records.

### `ChunkingConfig`

Immutable validated configuration:

- `maxTokens`: 32–4096; default 512;
- `overlapTokens`: 0–25% of max; default 64;
- `chunkingVersion`: non-empty stable version string, initially `m07-v1`;
- `embeddingCompatibilityKey`: nullable non-empty string.

Its fingerprint deterministically captures chunking compatibility.

### Structure model

`StructureAwareChunker` identifies ATX headings and otherwise treats blank-line-delimited blocks as paragraphs. Oversized content splits at sentence boundaries then lexical/code-point-safe fallback. No source-format API is called.

Each heading occurrence creates a deterministic **section instance**. Repeated identical heading paths remain distinct section instances. Section instance participates in overlap isolation, public parent identity, and stable chunk identity.

### `ChunkRecord`

Immutable WordPress-independent record containing `chunkKey`, document/source identity, title/URL, content/content hash, source version/document hash, language/visibility, global `sequence`, `parentChunkKey`, `headingPath`, token count, chunking compatibility, embedding compatibility, and copied source metadata.

#### Stable chunk identity

`ChunkRecord::sequence` is **global deterministic presentation/order metadata only**. It is intentionally **not** part of stable chunk identity.

`chunkKey` is the deterministic SHA-256 identity over:

- document key;
- chunking fingerprint;
- structural heading path;
- deterministic section-instance identity;
- **section-local chunk ordinal**.

This prevents an early/middle section changing from N to N+1 chunks from changing the keys of later byte-identical sections merely because their global sequence shifted.

`parentChunkKey` hashes document key + chunking fingerprint + heading path + section-instance identity. Chunks from one section share a parent; repeated identical heading labels in separate section instances do not.

Chunk content hashes cover canonical chunk content plus citation/structural identity required to distinguish semantically different lineage.

### Overlap

Overlap is allowed only between adjacent chunks from the same structural section instance. Both configured overlap budget and final max-token budget are enforced using the injected `TokenCounter`. New content is never discarded merely to fit overlap.

### `ChunkDeduplicator`

Deduplication uses normalized chunk content plus hard compatibility/privacy boundaries including language, visibility, and embedding compatibility. Canonical selection and all observable output ordering are deterministic; duplicate aliases point duplicate -> canonical.

### `IncrementalIndexPlanner`

The planner compares previous/current canonical chunks and emits immutable deterministic collections:

- `upsert`: new chunks or chunks whose content/embedding/security/compatibility/per-chunk indexed metadata changed;
- `metadataRefresh`: same reusable embedding/content identity but changed document-wide lineage (`sourceVersion`/`documentContentHash`);
- `deleteKeys`: previous keys absent from current output;
- `unchanged`: exact reusable chunks requiring no index work;
- duplicate aliases.

Chunking or embedding compatibility changes invalidate reuse deliberately. Visibility/language/token/source metadata boundaries are explicit. Planner execution is pure and performs no persistence, embedding, queue, network, or vector work.

### `DocumentIndexPipeline`

Pure composition:

`DocumentRecord -> ContentNormalizer -> StructureAwareChunker -> ChunkDeduplicator -> IncrementalIndexPlanner -> DocumentIndexResult`

## Determinism rules

- Same document/config/counter/previous-set produces byte-identical normalized text, chunk ordering, identities, hashes, dedup aliases, and plan collections.
- Associative metadata uses canonical key ordering through `DocumentHasher`.
- Global `sequence` is assigned after final boundaries settle and controls presentation/order, not stable key identity.
- Section-local chunk ordinals restart within each structural section instance.
- Repeated identical heading labels are isolated by deterministic section-instance identity.
- Empty normalized documents emit zero current chunks and delete prior chunks for that document.

## Error handling

Invalid config fails fast. Invalid UTF-8 fails closed for Unicode matching. Oversized units use bounded sentence/lexical/code-point-safe splitting; infinite loops are prohibited by tests. Existing `DocumentRecord` validation remains authoritative.

## Security/privacy

Document content/metadata remain untrusted data. M07 never evaluates HTML, PHP, shortcodes, URLs, tools, or prompt-like text. Visibility, language, and embedding compatibility remain hard dedup/planning boundaries. No provider credentials, secrets, live order/customer data, persistence, network calls, or runtime hooks are introduced.

## Performance

Chunking is bounded/linear apart from bounded splitting. Dedup/planning use expected O(n) map/set work plus deterministic sorting of emitted results. Stable section-local identity ensures localized chunk-count changes do not cascade delete/upsert/re-embedding across downstream unchanged sections.

## Testing strategy

1. Normalizer idempotence and whitespace/BOM/CRLF fixtures.
2. Token/config Unicode and validation fixtures.
3. Structure-aware chunking, overlap, repeated headings, injected-counter budgets, invalid UTF-8, tiny/huge sections.
4. **Localized chunk-count regression:** an early/middle section gains a chunk while a later byte-identical section must retain its `chunkKey` and avoid `upsert`.
5. Dedup canonical/alias ordering and privacy/compatibility boundaries.
6. Incremental no-op, localized changes, deletions, metadata refresh, chunking/embedding compatibility changes.
7. Representative WordPress/file/WooCommerce source-to-plan integration.
8. Security/performance review and broad PHP/JS/package/WordPress CI.

## Milestone completion gate

M07 is complete only after every task has genuine RED/GREEN evidence, focused and broad exact-SHA verification is green, a fresh-session independent whole-M07 review has zero unresolved Critical/Important findings, durable docs match implementation, PR #9 is merged using the verified exact head, and fresh post-merge `main` CI passes.
