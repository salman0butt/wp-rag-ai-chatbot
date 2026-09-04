# M08 — Embeddings & Vector Stores Design

Status: **AUTO-APPROVED — SCHEDULED MODE**

## Context

M08 follows the completed M07 deterministic chunk/index-plan pipeline. It owns embedding execution and replaceable vector-store infrastructure, but not background scheduling/synchronization (M09) or hybrid retrieval orchestration (M10).

Repository invariants already require:

- PHP-first WordPress runtime with no mandatory external backend;
- provider-specific HTTP/authentication to remain behind provider boundaries;
- embedding/index compatibility to include provider, model, dimensions, and relevant normalization/distance configuration;
- incompatible vectors never to coexist silently;
- a modest-installation local WordPress vector store with explicit scale limits;
- external vector stores to be optional;
- credentials to remain server-side;
- deterministic, testable contracts and no paid-provider calls in normal CI.

## Design classification

Architectural. M08 introduces new application/domain contracts and multiple infrastructure adapters.

## Considered approaches

### Approach A — One universal lowest-common-denominator vector-store interface

Expose `upsert`, `delete`, and `search` on every adapter and force every provider to implement the same raw-vector semantics.

**Pros:** small API, simple registry.

**Cons:** managed services do not all expose identical primitives; pretending they do would require leaky emulation, silent capability loss, or adapter-specific exceptions in normal flows. It also encourages future retrieval code to assume capabilities that may not exist.

### Approach B — Separate unrelated interfaces per vendor

Give local/Qdrant/Pinecone/Chroma/OpenAI each an independent API and let higher layers branch on vendor type.

**Pros:** maps directly to vendor APIs.

**Cons:** vendor logic leaks upward, contract testing becomes fragmented, configuration is harder to validate, and M10 would need vendor conditionals.

### Approach C — Capability-aware common contracts with optional operation interfaces — selected

Use a stable base vector-store identity/health/capability contract, plus explicit operation interfaces for raw-vector upsert/delete/search and any managed-store-only behavior. Higher layers select required capabilities before execution. A registry returns adapters by stable store ID without hiding unsupported operations.

**Why selected:** preserves a clean application boundary while remaining truthful about provider differences. It is the smallest design that supports replaceable stores without fake parity.

## Embedding architecture

### Provider boundary

Add an embedding capability to the existing `Providers` domain rather than creating vendor HTTP clients in `Embeddings`.

`EmbeddingProvider` owns only provider-facing embedding execution:

- stable `provider_id()`;
- `embed(EmbeddingRequest): EmbeddingResult`;
- normalized provider exceptions through the existing provider error model where possible.

OpenAI Direct and OpenRouter Direct reuse the existing credential resolver, fixed-endpoint HTTP client, redaction, timeout policy, and provider registry composition patterns. WordPress AI Client embedding support is capability-gated: if the installed WordPress public API cannot provide the required embedding operation, that provider simply has no embedding capability rather than using private Core APIs.

### Application embedding service

`Embeddings\EmbeddingService` is vendor-neutral and owns:

- deterministic input ordering;
- configurable bounded batching;
- empty-input rejection;
- vector-count/order validation;
- finite numeric value validation;
- consistent dimensions across a result;
- optional expected-dimension validation;
- aggregate usage accounting when providers expose usage;
- fail-fast semantics for a failed batch in M08. M09 later owns retry/queue execution.

The service does not cache embeddings and does not write vectors. It returns immutable embedding records for the caller to persist through a vector store.

## Compatibility model

Introduce immutable value objects:

### `EmbeddingProfile`

Defines the embedding-generation identity:

- provider ID;
- model ID;
- dimensions;
- normalization mode.

### `VectorIndexProfile`

Defines index compatibility:

- the complete `EmbeddingProfile`;
- distance metric;
- schema/fingerprint version.

The compatibility fingerprint is a versioned SHA-256 over a canonical field order, not PHP serialization or unordered JSON. Equivalent profiles must produce byte-identical fingerprints across runs; any incompatible field change must change the fingerprint.

The fingerprint is metadata and must never contain credentials.

## Embedding request/result contracts

`EmbeddingRequest` contains model ID, ordered non-empty text inputs, and optional requested dimensions when supported. Text is untrusted data and is never interpolated into instructions.

`EmbeddingVector` contains the original zero-based input index and a finite ordered float vector.

`EmbeddingUsage` contains normalized input-token usage when available and remains explicitly unknown when the provider omits it; unknown usage must not be represented as a fabricated zero.

`EmbeddingResult` contains vectors in input order plus usage and provider/model metadata needed for diagnostics. Provider responses with missing, duplicated, out-of-range, or inconsistent indices/dimensions fail closed.

## Provider batching and network policy

M08 does not infer provider limits from model-name strings. Batch size is an application configuration with conservative bounded defaults and may later be informed by explicit model/provider metadata.

Direct embedding calls use fixed HTTPS endpoints and disabled redirects, reusing M03's network boundary. Embedding requests are potentially billable, so normal CI uses fake transports/providers only. No automatic retry is added inside embedding execution; M09 owns retry policy and idempotent job orchestration.

## Vector-store contracts

### Base contract

`VectorStore` exposes:

- stable `store_id()`;
- `capabilities(): VectorStoreCapabilities`;
- `health(): VectorStoreHealth`.

### Raw-vector operations

`VectorUpsertStore`:

- `upsert(VectorCollection $collection, VectorRecord ...$records): VectorWriteResult`.

`VectorDeleteStore`:

- deterministic deletion by stable vector/chunk IDs scoped to one collection/namespace.

`VectorSearchStore`:

- `search(VectorSearchRequest): VectorSearchResult` with top-K, query vector, required compatibility fingerprint, and typed metadata filters.

Adapters implement only operation interfaces they truly support.

### Registry

`VectorStoreRegistry` registers one adapter per stable store ID, rejects duplicates/mismatches, exposes deterministic IDs, and can require a capability before returning an adapter.

## Collection and record model

`VectorCollection` contains:

- plugin-controlled logical collection/namespace ID;
- `VectorIndexProfile` / compatibility fingerprint;
- tenant/site scope identity where required.

`VectorRecord` contains:

- stable vector ID derived from the M07 chunk identity supplied by the caller;
- vector values;
- immutable searchable metadata;
- compatibility fingerprint.

The vector store must reject records whose vector dimensions/fingerprint are incompatible with the target collection. It must not silently create a mixed index.

## Metadata and isolation

Only allow a typed, bounded metadata schema required by later retrieval/citation filtering, including stable document/chunk/source IDs, language, visibility/access classification, and selected scalar source metadata. Arbitrary secret-bearing metadata is forbidden.

Every operation is explicitly scoped to a plugin-controlled collection/namespace. External adapter configuration never accepts a namespace supplied by anonymous/public request data.

## Filter model

M08 defines a small portable filter AST rather than raw vendor query fragments:

- equality on allowed scalar keys;
- membership (`IN`) on allowed scalar keys;
- conjunction (`AND`).

Unsupported filter semantics fail before network execution. OR/range/full-text filtering is deferred until a real retrieval requirement needs it.

## Search result model

`VectorMatch` contains stable vector ID, normalized adapter-returned score, and safe metadata. M08 preserves the native score meaning and records the configured distance metric; it does not attempt cross-store score normalization or hybrid fusion. M10 owns retrieval fusion/threshold semantics.

Results are deterministically tie-broken by stable vector ID where the adapter returns equal scores and the adapter can do so without changing semantic rank.

## Local WordPress vector store

The local adapter is a zero-infrastructure correctness path for modest installations.

### Storage

Use dedicated versioned database tables, not `wp_options`. Store vector data and searchable metadata separately enough to permit bounded candidate selection before PHP similarity calculation. Do not claim MySQL/MariaDB native vector features as a baseline dependency.

### Bounded search

Local semantic search must never load an unbounded table into PHP. A configurable hard candidate ceiling is mandatory. SQL first narrows by site/collection, compatibility fingerprint, visibility/language/portable filters, and bounded candidate count; PHP computes similarity only over that bounded candidate set.

If a query cannot be safely satisfied within the configured local scale/candidate policy, return an explicit scale-limit error/warning rather than silently scanning the complete corpus.

### Metrics

Implement cosine similarity first because it is portable and deterministic. Additional metrics are introduced only when an implemented external adapter/index profile requires them and a contract suite exists.

## External adapters

### Qdrant

Implement raw-vector upsert/delete/search, namespace/collection scoping, portable filters, health, and capabilities. Use fixed HTTPS endpoint configuration validated as an administrator-owned server URL; credentials remain encrypted/server-side. Collection compatibility is checked before writes/search.

### Pinecone

Implement the same raw-vector contract where its API supports it, with namespace isolation and metadata filters. Index dimension/metric compatibility is verified before use.

### Chroma

Implement raw-vector add/upsert/delete/query against a configured Chroma server. Treat it as optional external infrastructure; it is never required for plugin activation or normal CI.

### OpenAI Vector Store

OpenAI's managed vector-store/file-search model is not assumed to be equivalent to arbitrary raw-vector upsert/search. M08 therefore integrates it only through capabilities that its current public API genuinely exposes. The adapter must not fake raw-vector support. If its managed ingestion/search contract cannot satisfy the raw-vector operation interfaces, it remains a capability-specific adapter and M10 must explicitly select compatible retrieval paths.

This boundary is deliberate and prevents the common abstraction error of pretending all "vector stores" have identical data models.

## Error model

Add normalized vector-store exception codes for:

- unavailable/misconfigured;
- authentication/authorization;
- transport/timeout;
- rate limit;
- invalid request/response;
- incompatible index profile;
- unsupported capability/filter;
- local scale limit.

Never include credentials, raw authorization headers, or opaque provider response bodies in user-visible messages/loggable exception text.

## Health/capability behavior

Health checks are explicit operations and may perform bounded remote calls only when requested. Registry construction itself performs no network I/O.

Capabilities are declarative and deterministic. They include raw upsert/delete/search, portable metadata filters, managed ingestion/search if relevant, and supported distance metrics.

## TDD and test strategy

Normal CI remains offline from paid/external providers.

Test layers:

1. pure unit tests for immutable request/result/profile/fingerprint/filter/value validation;
2. provider fake-transport tests for request normalization, batching, response validation, usage, and sanitized errors;
3. reusable vector-store contract suite exercised by an in-memory test adapter and every concrete adapter where practical;
4. WordPress integration tests for local table migrations/storage/filtering/bounded candidate behavior;
5. adapter HTTP fake tests for Qdrant/Pinecone/Chroma/OpenAI managed behavior;
6. opt-in live external tests only when credentials/services are explicitly available;
7. full PHP/JS/package/WordPress smoke CI at every merge gate.

Each behavior task begins with a genuine RED test commit/run before implementation.

## Security review requirements

- credentials server-side only and reuse authenticated encrypted storage patterns;
- fixed/validated administrator-owned endpoints, no public SSRF surface;
- redirects disabled for direct external adapters;
- tenant/site/collection scope on every read/write/search/delete;
- no arbitrary raw vendor filter/query fragments;
- metadata allowlist and bounded sizes;
- finite vectors and bounded dimensions/batches/top-K;
- sanitized external errors;
- no retrieved metadata/content promoted to instructions or authorization state.

## Performance requirements

- hard maximum embedding batch/input counts and text-size reuse from upstream chunk bounds;
- no quadratic embedding orchestration;
- local candidate ceiling before PHP distance calculation;
- bounded top-K and filter cardinality;
- adapter bulk operations where public APIs support them;
- benchmark/document local-store practical limits before M08 closeout.

## Milestone decomposition

1. Embedding contracts, profiles, fingerprints, and provider-registry capability.
2. Embedding service batching/validation and OpenAI/OpenRouter direct adapters.
3. Vector-store base/operation contracts, portable filters, registry, and reusable contract suite.
4. Local WordPress vector store + migration + bounded search.
5. Qdrant adapter.
6. Pinecone adapter.
7. Chroma adapter.
8. OpenAI managed vector-store capability adapter, limited to truthful public capabilities.
9. Source/index-plan-to-embedding/vector integration boundary, security/performance review, benchmarks, durable docs, final independent review, exact-SHA CI, merge, post-merge verification.

External adapter tasks may be split into subplans while preserving this design.

## Self-review

- Placeholder scan: no TODO/TBD or unresolved design placeholders.
- Scope: M09 queue/retries and M10 hybrid retrieval/fusion remain explicitly out of scope.
- Compatibility: satisfies ADR-006 without relying on model-name inference.
- Security: no client credentials, arbitrary public endpoints, raw vendor filters, or mixed-compatibility collections.
- Testability: every contract has offline fake/contract-test coverage and explicit failure behavior.
- YAGNI: filter language and distance metrics remain intentionally small; optional interfaces prevent speculative universal features.

**Approved outcome:** Approach C is selected and this corrected design is **AUTO-APPROVED — SCHEDULED MODE**.