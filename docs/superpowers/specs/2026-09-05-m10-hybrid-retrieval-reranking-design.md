# M10 — Hybrid Retrieval & Reranking Design

Status: **AUTO-APPROVED — SCHEDULED MODE**

## Context

M10 follows the completed M07 chunking/index-plan pipeline, M08 embedding/vector-store infrastructure, and M09 durable synchronization. It owns retrieval orchestration only: query preprocessing, semantic and lexical candidate generation, deterministic fusion, access filtering, optional reranking, confidence/context candidates, and trace data. Final answer generation and citation rendering remain M11.

Repository invariants require a PHP-first WordPress runtime, no mandatory external backend, hybrid retrieval as a first-class product capability, lexical retrieval for identifiers/exact phrases, bounded local execution, provider-independent vector contracts, fail-closed access controls, and retrieved content/metadata to remain untrusted data.

M08 deliberately stores vectors plus bounded scalar metadata rather than chunk body text. M07 `ChunkRecord` owns the canonical chunk content and lineage. Therefore M10 needs a durable lexical/search projection of indexed chunks instead of encoding text into vector metadata.

## Design classification

Architectural. M10 introduces a new Retrieval application/domain subsystem, a durable lexical projection, fusion/reranking contracts, and security-sensitive filtering behavior.

## Considered approaches

### Approach A — Weighted raw-score fusion

Run vector and lexical retrieval, min/max-normalize each score set, then compute a configured weighted sum.

**Pros:** intuitive numeric score; easy to tune.

**Cons:** vector adapters expose different score semantics/distributions, lexical scores vary with corpus/query shape, tiny candidate sets make min/max unstable, and tuning can accidentally make one channel dominate. It is harder to keep behavior stable across local/Qdrant/Pinecone/Chroma implementations.

### Approach B — Reciprocal Rank Fusion (RRF) with per-channel diagnostics — selected

Retrieve bounded semantic and lexical lists independently, preserve their native scores for traces, assign deterministic ranks, and fuse by weighted RRF: `weight / (rrf_k + rank)`. Deduplicate by stable chunk ID and tie-break by chunk ID. Expose native score, channel rank, RRF contribution, and fused score in the trace.

**Pros:** robust to incomparable score scales, deterministic, explainable, portable across adapters, simple to test, and naturally rewards agreement between semantic and lexical channels.

**Cons:** rank-based fusion discards some magnitude information, so tuning relies on candidate depth/weights rather than native score calibration.

### Approach C — Learned/LLM-first reranking as the primary hybrid mechanism

Generate a broad union of candidates and ask a hosted reranker/LLM to choose the order.

**Pros:** potentially stronger relevance on difficult queries.

**Cons:** adds cost, latency, availability risk, prompt-injection surface, provider coupling, and nondeterminism. It cannot be the baseline because local/no-external-backend operation is a product invariant.

**Selected outcome:** Approach B. Deterministic weighted RRF is the default hybrid fusion. Optional reranking is a post-fusion extension point and is never required for correctness.

## Retrieval architecture

The application flow is:

1. validate and preprocess the query;
2. derive a bounded typed filter policy from trusted server-side context;
3. generate a query embedding through `EmbeddingService` when semantic retrieval is enabled;
4. execute semantic search through a `VectorSearchStore` with the same trusted filters where supported;
5. execute lexical/exact search against the local chunk-search projection with the same trusted filters;
6. normalize each channel into deterministic ranked candidates while preserving native scores;
7. fuse by weighted RRF and deduplicate by stable chunk ID;
8. apply a fail-closed post-fusion access guard so an adapter/filter translation defect cannot leak a disallowed chunk;
9. optionally rerank only the bounded top-N fused candidates;
10. return bounded context candidates plus deterministic confidence and trace data.

Semantic and lexical retrieval are logically parallel and independent. One configured channel may degrade only when policy explicitly permits it; access-filter failures never degrade open.

## Query preprocessing

Add `RetrievalQuery` and `QueryPreprocessor`.

Rules:

- trim and collapse Unicode/ASCII whitespace;
- reject empty queries;
- enforce a hard UTF-8 byte limit and lexical token limit before provider/store calls;
- retain the normalized full query for embeddings;
- derive lexical terms using deterministic Unicode-aware lowercasing when available, while preserving identifier-friendly tokens containing letters, numbers, `_`, `-`, `.`, `/`, and `:`;
- derive an exact-phrase form from the normalized query;
- never interpret query text as SQL, provider filter syntax, instructions, or authorization state.

The preprocessor does not perform LLM query rewriting in M10.

## Durable lexical/search projection

Add a versioned `wprag_chunk_search` database table through the existing migration system. Each row is scoped by site/collection and stable `chunk_key`, and stores only retrieval/citation fields required by M10/M11:

- collection ID;
- chunk key;
- document key;
- source ID;
- document type;
- title;
- canonical URL when present;
- chunk content;
- content hash;
- language when present;
- visibility;
- sequence;
- bounded safe source metadata JSON;
- updated timestamp.

Indexes must cover collection + visibility, collection + language, document/source identifiers, and the lexical lookup strategy supported by the baseline MySQL/MariaDB versions. No assumption is made that every host supports modern vector/full-text extensions. A portable bounded SQL candidate stage is mandatory; PHP may perform deterministic lexical scoring only over that bounded set.

`ChunkSearchStore` owns `replace_document_chunks`, `delete_document`, and `search`. M10 extends the existing index execution boundary so the same accepted M07 plan that writes vectors also updates this projection. Projection writes are idempotent by collection + chunk key.

## Lexical retrieval and scoring

The baseline lexical engine is deterministic and exact-friendly:

- exact normalized full-query occurrence receives the strongest boost;
- exact identifier/token matches receive a strong boost;
- title matches receive a bounded field boost;
- remaining term overlap uses a simple normalized coverage score;
- stable chunk ID is the final tie-breaker.

The SQL stage only narrows candidates using trusted scope/filter predicates and bounded lexical predicates. PHP scoring runs on at most the configured lexical candidate ceiling. This avoids relying on DB-specific ranking formulas while still allowing future FULLTEXT optimization behind the same contract.

## Semantic retrieval

`SemanticRetriever` consumes the existing `EmbeddingService`, configured `EmbeddingProfile`, `VectorCollection`, and `VectorSearchStore`.

It embeds exactly one normalized query, validates the returned vector through existing embedding contracts, creates a bounded `VectorSearchRequest`, and converts `VectorMatch` objects into retrieval candidates using stable metadata keys (`chunk_id`, `document_id`, `source_id`, `language`, `visibility`). Missing/invalid required lineage metadata causes that match to be dropped fail-closed and recorded in the trace.

No cross-store score normalization is required for fusion because RRF uses rank. Native vector scores remain observable.

## Filter and access model

`RetrievalFilter` is built only from trusted server-side policy and supports the M10 requirements already representable by M08 portable filters: collection/site scope, visibility, language, source/document identifiers, and bounded membership lists.

Security rules:

- public request data never supplies raw vendor filter fragments, namespaces, collection IDs, or authorization classifications;
- access/visibility filters are pushed into semantic and lexical stores whenever possible;
- every fused candidate is rechecked by a trusted `CandidateAccessPolicy` before reranking/context selection;
- missing visibility/source lineage fails closed;
- unsupported mandatory filters fail the retrieval request rather than running a broader query;
- retrieved metadata/content is never promoted into system instructions, tool permissions, or authorization policy.

## Candidate and trace model

Introduce immutable DTOs:

- `RetrievalCandidate`: stable chunk/document/source IDs, safe citation fields, content, language, visibility, channel evidence, fused score, optional rerank score, confidence;
- `ChannelEvidence`: channel, native score, rank, configured weight, RRF contribution;
- `RetrievalTrace`: normalized query summary, channel counts, dropped/filtered IDs with reason codes, fusion settings, rerank status, final selected IDs;
- `RetrievalResult`: ordered candidates + trace.

Trace data must not contain credentials, raw authorization context, provider response bodies, or unrestricted source metadata. Query text may be included only where the caller's diagnostic policy explicitly permits it; default trace uses length/hash plus safe normalized tokens.

## RRF fusion

`ReciprocalRankFusion` accepts one or more bounded ranked channel lists and configuration:

- `rrf_k` positive integer, default 60;
- semantic weight positive finite float, default 1.0;
- lexical weight positive finite float, default 1.0;
- max fused candidates hard-bounded.

For candidate rank `r` in channel `c`, contribution is `w_c / (k + r)`. Contributions for the same chunk are summed. Sort descending fused score, then by best channel rank, then stable chunk ID. The same inputs must always produce byte-for-byte equivalent ordering and trace contributions within normal float serialization tolerance.

## Confidence

M10 confidence is a deterministic retrieval signal, not an answer-truth probability. It is derived from bounded evidence:

- channel agreement increases confidence;
- exact phrase/identifier evidence increases confidence;
- high semantic rank contributes positively;
- candidates surviving only at weak tail ranks remain low confidence.

Expose an enum-like level (`high`, `medium`, `low`) plus numeric `[0,1]` score. M11 decides how confidence gates answer generation/no-answer behavior.

## Optional reranking

Define `Reranker` with a bounded request/result contract. The default path uses `NoOpReranker`. A reranker receives only the top-N already access-approved candidates and untrusted query/content data. It cannot add candidates, alter authorization metadata, or return arbitrary IDs; results must be a permutation/subset of supplied candidate IDs with finite scores.

M10 may include a deterministic local lexical-overlap reranker adapter for offline coverage. Hosted/model-specific rerankers are optional and must be capability-gated; no paid provider call is required by normal CI or baseline plugin operation.

## Failure and degradation policy

- Invalid query/configuration: fail before external work.
- Mandatory access/filter translation unsupported: fail closed.
- Semantic provider/vector-store failure: may degrade to lexical-only only when retrieval policy explicitly enables semantic degradation; trace records the normalized reason code.
- Lexical store failure: may degrade to semantic-only only under the same explicit policy.
- Both channels unavailable: retrieval fails.
- Reranker failure: configurable fail-open-to-fused-order is allowed because filtering already occurred; trace records the reranker failure code.
- No candidates: return an empty successful `RetrievalResult`; M11 applies answer/no-answer policy.

## Performance bounds

Configuration defines hard ceilings for:

- query bytes and lexical tokens;
- semantic top-K;
- lexical SQL candidate count;
- lexical scored candidate count;
- fused candidate count;
- rerank top-N;
- final context candidates;
- membership-filter cardinality.

No retrieval operation scans an unbounded corpus into PHP. Semantic search continues to rely on M08's local candidate ceiling/external top-K. Lexical search must apply collection/access predicates and bounded candidate selection before PHP scoring.

## Testing strategy

1. Unit tests for query preprocessing/bounds and identifier preservation.
2. Unit tests for RRF deduplication, weighting, deterministic ties, and invalid configuration.
3. Unit tests for access-policy fail-closed behavior and trace redaction.
4. Repository/store tests for lexical projection writes, deletes, filters, candidate bounds, exact phrases, SKUs/error codes, and deterministic scoring.
5. Semantic retriever tests with recording embedding provider/in-memory vector store.
6. Hybrid orchestrator tests proving exact lexical + paraphrase semantic candidates fuse correctly, degradation policy is explicit, and mandatory filter failures never broaden access.
7. Optional reranker contract tests proving it cannot inject or re-authorize candidates.
8. WordPress integration fixture proving an exact SKU/identifier and a paraphrase are retrievable and restricted chunks remain inaccessible.
9. Full PHP static analysis/lint/unit/integration/package/WordPress smoke gates.

Every production behavior task begins with a genuine RED test commit/run before its GREEN implementation commit.

## Milestone decomposition

1. Query/config/result/trace contracts and deterministic query preprocessing.
2. Weighted RRF fusion and deterministic confidence evidence.
3. Chunk-search projection migration/store and index-pipeline synchronization boundary.
4. Lexical retriever with exact/identifier/coverage scoring and hard candidate bounds.
5. Semantic retriever using M08 embedding/vector contracts and typed filters.
6. Hybrid orchestrator, fail-closed access guard, explicit channel degradation, and bounded context selection.
7. Optional reranker contract + no-op/local deterministic adapter.
8. End-to-end WordPress indexed-fixture integration, security/performance review, durable docs, independent review, exact-SHA CI, merge, and post-merge verification.

## Self-review

- **Spec coverage:** semantic, lexical/exact, hybrid fusion, filters, optional reranking, confidence/context candidates, deterministic traces, security bounds, and required exact/paraphrase fixtures are covered.
- **M08 compatibility:** fusion uses ranks rather than assuming vector score comparability; existing portable filters and stable metadata are reused.
- **M07 compatibility:** canonical chunk content/lineage remains owned by `ChunkRecord`; the lexical table is an indexing projection, not a second source of truth.
- **Security:** authorization comes only from trusted policy, is pushed down where possible, and is rechecked fail-closed after fusion before reranking.
- **Performance:** all query/candidate/filter/rerank dimensions are hard-bounded; no unbounded PHP corpus scan is permitted.
- **Offline baseline:** local lexical + local vector retrieval and no-op reranking require no paid/external service.
- **Placeholder scan:** no TODO/TBD or unresolved design placeholder remains.
- **YAGNI:** no LLM query rewrite, learned fusion, arbitrary filter language, or mandatory hosted reranker is introduced.

**Approved outcome:** deterministic weighted RRF with a durable lexical chunk-search projection and optional post-filter reranking is **AUTO-APPROVED — SCHEDULED MODE**.