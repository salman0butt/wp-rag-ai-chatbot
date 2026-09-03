# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing Design

Status: **AUTO-APPROVED — SCHEDULED MODE**

## Context

M04–M06 now emit canonical immutable `DocumentRecord` values containing stable source identity, normalized content, metadata, source version/hash, language, visibility, canonical URL, and timestamps. M07 transforms those documents into deterministic chunks and an incremental index plan. It must not embed content, write vectors, or introduce asynchronous execution; M08 and M09 own those responsibilities.

## Goals

- Normalize document text deterministically without interpreting or executing it.
- Prefer structure boundaries in this order: heading/section, paragraph, sentence, then bounded lexical fallback.
- Enforce a configurable deterministic token budget with deliberate configurable overlap.
- Emit immutable chunk records with stable identity, content hashes, sequence/parent/heading lineage, citation/source metadata, chunking-version metadata, and a nullable embedding-compatibility key reserved for M08.
- Deduplicate identical canonical chunk payloads deterministically.
- Compare previous and current chunks to produce bounded `upsert`, `delete`, and `unchanged` decisions so unchanged chunks do not require re-embedding.
- Keep the entire M07 pipeline pure PHP and WordPress-independent so it can be unit tested without network/provider/runtime dependencies.

## Non-goals

- Provider/model-specific exact tokenizer implementations.
- Embedding generation or batching.
- Vector-store upsert/delete/search.
- Database job/lease/retry execution.
- Re-parsing original HTML/PDF/DOCX/WordPress structures already normalized by M04–M06.
- Treating retrieved/document text as trusted instructions.

## Existing boundaries preserved

- `DocumentRecord` remains the canonical source-to-index input.
- `DocumentHasher` remains the canonical associative-payload SHA-256 utility.
- M08 owns provider/model/dimension/distance compatibility and may inject a more exact token counter plus a non-null embedding compatibility key.
- M09 owns background execution and synchronization orchestration.
- Current WooCommerce price/stock/order/customer state remains excluded because M06 never places it in canonical documents.

## Approaches considered

### A. Deterministic structure-aware pure-PHP pipeline — selected

Parse only the structure present in canonical text, preserve heading/paragraph boundaries, recursively split oversized units, and expose a small token-counting contract. Stable chunk/hash/planning records stay vendor-neutral.

**Advantages:** satisfies M07 directly; no runtime dependency; deterministic; testable; compatible with every M04–M06 source; keeps M08/M09 boundaries clean.

**Trade-off:** the default token counter is a deterministic lexical estimate rather than a provider-exact tokenizer. M08 can inject a provider-aware implementation without redesigning chunk records.

### B. Fixed-size character/word windows

Simpler and fast, but loses headings/paragraphs/sentences and violates the approved structure-aware architecture. Rejected.

### C. Format-specific AST/DOM chunkers

Could preserve richer HTML/Markdown/PDF structure, but M04–M06 deliberately normalize source formats into canonical documents. Re-parsing formats would duplicate extraction concerns and leak scope. Rejected for M07.

## Components

### `ContentNormalizer`

Pure utility that:

1. converts CRLF/CR to LF;
2. removes UTF-8 BOM only at the beginning;
3. strips trailing horizontal whitespace from each line;
4. trims leading/trailing blank space from the whole document;
5. collapses runs of three or more blank lines to exactly two blank lines;
6. preserves all non-whitespace characters, heading markers, punctuation, and instruction-like text verbatim.

It does **not** case-fold, remove punctuation, strip HTML-like text, execute shortcodes, sanitize for rendering, or Unicode-normalize through an optional extension.

### `TokenCounter`

Contract:

```php
public function count(string $text): int;
```

The M07 default `LexicalTokenCounter` counts Unicode letter/number runs and individual non-whitespace punctuation/symbol units using a deterministic Unicode regex. It is a stable internal budget, not a claim to match OpenAI/OpenRouter tokenization exactly. Empty/whitespace-only text counts as zero.

### `ChunkingConfig`

Immutable validated configuration:

- `maxTokens`: 32–4096; default 512.
- `overlapTokens`: 0–25% of `maxTokens`; default 64 for the default max.
- `chunkingVersion`: non-empty stable version string, initial `m07-v1`.
- `embeddingCompatibilityKey`: nullable non-empty string; normally null until M08.

Its deterministic fingerprint is SHA-256 over those values except that the embedding key is included only as metadata/plan compatibility, not as text content.

### Structure model

`StructureAwareChunker` consumes one `DocumentRecord` and normalized content. It identifies ATX headings (`#` through `######`) present in canonical text and otherwise treats blank-line-delimited blocks as paragraphs. Heading paths apply to following blocks until replaced by a heading of the same or shallower level.

Splitting order for oversized content:

1. section/heading boundary;
2. paragraph boundary;
3. sentence boundary using deterministic punctuation + whitespace rules;
4. lexical-unit fallback for a single oversized sentence/block.

No source-format API is called.

### `ChunkRecord`

Immutable WordPress-independent record containing:

- `chunkKey`: stable `sha256(documentKey + chunkingFingerprint + structuralPath + sequence)` identity;
- `documentKey`, `sourceId`, `documentType`;
- `title`, `canonicalUrl`;
- `content`, `contentHash`;
- `sourceVersion`, `documentContentHash`;
- `language`, `visibility`;
- `sequence` (zero-based final chunk order);
- `parentChunkKey` nullable;
- `headingPath` ordered heading strings;
- `tokenCount`;
- `chunkingVersion`, `chunkingFingerprint`;
- `embeddingCompatibilityKey` nullable;
- strict copied source metadata under `sourceMetadata`.

Chunk content hashes use `DocumentHasher` over canonical chunk content plus structural/citation identity needed to prevent accidental collision across semantically distinct lineage.

### Parent-child semantics

For M07, parent identity is structural rather than a separately embedded parent record. A heading/section receives a deterministic parent key; child chunks from that section reference the same `parentChunkKey`. This preserves small-to-big lineage without creating an additional persistence/vector lifecycle before a concrete consumer exists.

### Overlap

Overlap is applied only between adjacent chunks generated from the same structural parent. The chunker copies up to `overlapTokens` trailing lexical units from the prior chunk before appending new content. Overlap never crosses document or section-parent boundaries. If overlap would leave no room for new content, configuration is invalid.

### `ChunkDeduplicator`

Deduplicates final chunks by a canonical dedup fingerprint consisting of normalized chunk content plus language/visibility and embedding-compatibility key. The earliest chunk in deterministic sequence is canonical; later duplicates are represented in a duplicate map pointing to the canonical chunk key rather than silently disappearing without lineage.

This prevents redundant embeddings while preserving traceability. Different visibility or embedding compatibility values never deduplicate together.

### `IncrementalIndexPlanner`

Inputs:

- previous canonical chunks (possibly empty);
- current canonical chunks after deduplication;
- previous/current chunking fingerprint;
- previous/current embedding compatibility key.

Outputs immutable `IndexPlan` collections:

- `upsert`: new keys or keys whose content/lineage/hash/compatibility changed;
- `delete`: previous canonical keys absent from the new set;
- `unchanged`: same key and same canonical hash/compatibility;
- duplicate aliases for traceability.

If chunking fingerprint or embedding compatibility changes, all current canonical chunks are `upsert` and all prior keys not reused are `delete`; this is controlled reindex planning, not destructive execution.

The planner performs no persistence, embedding, queue, or vector calls.

### `DocumentIndexPipeline`

Small application service composing:

`DocumentRecord -> ContentNormalizer -> StructureAwareChunker -> ChunkDeduplicator -> IncrementalIndexPlanner`

It returns an `IndexPlan` plus current chunk/duplicate evidence. It has no WordPress hooks and no I/O.

## Determinism rules

- Same `DocumentRecord`, config, token counter, and previous chunk set must produce byte-identical normalized content, chunk ordering, keys, hashes, duplicate map, and index plan.
- Associative metadata hashing uses existing `DocumentHasher` canonical key ordering.
- User/source metadata order is not trusted for identity unless canonicalized.
- Sequence is assigned only after final chunk boundaries are stable.
- Empty normalized documents produce zero chunks and delete any previous chunks for that document.

## Error handling and validation

- Invalid config fails fast with `InvalidArgumentException`.
- Invalid UTF-8 for Unicode token matching fails closed with a domain exception rather than silently producing unstable counts.
- A non-empty normalized lexical unit that cannot fit under a valid budget is split by Unicode code-point-safe bounded fallback; infinite loops are prohibited by tests.
- Negative source IDs, malformed hashes, and invalid `DocumentRecord` values continue to be rejected by existing contracts.

## Security/privacy

- Document content and metadata remain untrusted data throughout normalization/chunking.
- M07 never evaluates HTML, PHP, shortcodes, URLs, tool instructions, or prompt-like text.
- Visibility and source metadata are copied into chunks and participate in dedup/planning boundaries where needed to avoid public/private cross-deduplication.
- No secrets, provider credentials, customer/order data, or live WooCommerce state are introduced.

## Performance

- Pipeline is streaming-friendly internally but may return arrays at the M07 planning boundary because M07 needs old/new set comparison.
- Chunking scans text linearly apart from bounded recursive splitting of oversized blocks.
- Default maximum is 512 lexical tokens per chunk with 64 overlap; no whole-document quadratic substring search is permitted.
- Dedup and incremental comparison use hash maps keyed by fingerprints/chunk keys for expected O(n) behavior.
- A large-document regression fixture must assert bounded completion and stable chunk counts without enforcing machine-specific wall-clock thresholds in CI.

## Testing strategy

1. Normalizer fixtures: CRLF/BOM/trailing spaces/blank runs/instruction-like text/idempotence.
2. Token/config contract tests: Unicode, punctuation, bounds, invalid config.
3. Chunking fixtures: headings, paragraphs, sentences, oversized sentence, overlap, tiny/huge sections, metadata/visibility, deterministic keys.
4. Dedup tests: identical content, lineage aliasing, private/public isolation, compatibility isolation.
5. Incremental planner tests: no-op unchanged document, localized change, deletion, chunking-version change, compatibility change.
6. Source-to-index-plan integration fixtures using representative WordPress/file/WooCommerce-style `DocumentRecord` content.
7. Security/performance review and broad PHP/WordPress/package CI.

## Milestone completion gate

M07 is complete only after every planned task has genuine RED/GREEN evidence, focused and broad verification is green on the exact final SHA, independent review has zero unresolved Critical/Important findings, durable docs match implementation, PR is merged, and fresh post-merge `main` CI passes.
