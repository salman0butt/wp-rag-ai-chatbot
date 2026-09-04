# M08 Embeddings & Vector Stores Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add production-safe embedding execution and replaceable vector-store infrastructure with strict compatibility, bounded local search, and truthful adapter capabilities.

**Architecture:** Vendor HTTP/auth remains in `Providers`; `Embeddings` owns vendor-neutral batching/validation/compatibility; `VectorStore` owns capability-aware storage/search contracts and adapters. Incompatible provider/model/dimension/normalization/distance profiles are rejected before mixed writes/searches can occur. External stores are optional; the local WordPress store is bounded and intended only for modest installations.

**Tech Stack:** PHP 8.2+, WordPress 6.9+, PHPUnit/PHPStan, WordPress HTTP API through existing provider transport, dedicated MySQL/MariaDB tables for local storage, GitHub Actions authoritative dependency-backed verification.

**Spec:** `docs/superpowers/specs/2026-09-04-m08-embeddings-vector-stores-design.md`

## Global Constraints

- PHP/WordPress remains the mandatory server runtime; Node/Python/external vector services are optional only.
- Provider credentials remain server-side and reuse M03 authenticated storage/redaction/network boundaries.
- Normal CI never performs paid embedding requests or requires external vector services.
- No incompatible embedding/index profiles may silently coexist.
- M09 owns queue/retry/synchronization execution; M10 owns hybrid retrieval/fusion/threshold semantics.
- Retrieved/source text and metadata remain untrusted data, never instructions or authorization policy.
- Every meaningful behavior change requires genuine RED -> GREEN evidence on the exact branch SHA.

---

### Task 1: Embedding contracts and compatibility fingerprints

**Files:**
- Create: `src/Providers/EmbeddingProvider.php`
- Create: `src/Providers/EmbeddingRequest.php`
- Create: `src/Providers/EmbeddingResult.php`
- Create: `src/Providers/EmbeddingVector.php`
- Create: `src/Providers/EmbeddingUsage.php`
- Create: `src/Embeddings/EmbeddingProfile.php`
- Create: `src/Embeddings/VectorIndexProfile.php`
- Create: `src/Embeddings/NormalizationMode.php`
- Create: `src/Embeddings/DistanceMetric.php`
- Modify: `src/Providers/ProviderRegistry.php`
- Test: `tests/Unit/Embeddings/EmbeddingContractsTest.php`
- Test: `tests/Unit/Providers/ProviderRegistryEmbeddingTest.php`

**Interfaces:**
- `EmbeddingProvider::provider_id(): string`
- `EmbeddingProvider::available(): bool`
- `EmbeddingProvider::embed(EmbeddingRequest $request): EmbeddingResult`
- `EmbeddingRequest::__construct(string $model, array $inputs, ?int $dimensions = null)` rejects blank model/input, empty list, non-string inputs, invalid dimensions.
- `EmbeddingVector::__construct(int $index, array $values)` rejects negative index, empty/non-numeric/non-finite values.
- `EmbeddingUsage::unknown(): self` and `EmbeddingUsage::input_tokens(int $tokens): self`; unknown remains distinguishable from zero.
- `EmbeddingProfile::__construct(string $provider_id, string $model_id, int $dimensions, NormalizationMode $normalization)`.
- `VectorIndexProfile::__construct(EmbeddingProfile $embedding, DistanceMetric $distance)`.
- `VectorIndexProfile::fingerprint(): string` returns versioned deterministic SHA-256 based on canonical field ordering.
- `ProviderRegistry::register(...)` gains optional `EmbeddingProvider`; `embedding(string $provider_id): ?EmbeddingProvider` returns the capability without changing generation semantics.

- [ ] **Step 1: Write the failing embedding/profile contract tests**

Create tests that assert:

```php
$profile = new VectorIndexProfile(
    new EmbeddingProfile('openai-direct', 'text-embedding-model', 1536, NormalizationMode::NONE),
    DistanceMetric::COSINE
);

self::assertSame($profile->fingerprint(), (new VectorIndexProfile(
    new EmbeddingProfile('openai-direct', 'text-embedding-model', 1536, NormalizationMode::NONE),
    DistanceMetric::COSINE
))->fingerprint());
self::assertNotSame($profile->fingerprint(), (new VectorIndexProfile(
    new EmbeddingProfile('openai-direct', 'text-embedding-model', 3072, NormalizationMode::NONE),
    DistanceMetric::COSINE
))->fingerprint());
```

Also assert invalid vectors (`INF`, `NAN`, empty), invalid profile dimensions, blank IDs, and unknown-vs-zero usage behavior are rejected/preserved.

- [ ] **Step 2: Write the failing provider-registry capability test**

Use a tiny test double implementing `GenerationProvider` + `EmbeddingProvider` and assert `registry->embedding($id)` returns it; a generation-only provider returns `null`; mismatched embedding provider IDs are rejected.

- [ ] **Step 3: Push the test-only RED commit and run exact-SHA CI**

Expected: PHP quality fails only because the new contracts/classes/registry capability do not yet exist. Record SHA/run/failure in `docs/milestones/M08-embeddings-vector-stores.md`.

- [ ] **Step 4: Implement the minimum immutable contracts/value objects and registry change**

Fingerprint canonical payload exactly:

```text
v1\nprovider=<provider-id>\nmodel=<model-id>\ndimensions=<int>\nnormalization=<enum>\ndistance=<enum>
```

Return `hash('sha256', $payload)`.

- [ ] **Step 5: Run focused GREEN then full PHP verification**

Run via authoritative CI: focused PHPUnit class filters where workflow support permits, then `composer verify:php` + `composer audit`. Expected: all existing plus new tests green.

- [ ] **Step 6: Independent review and commit any required fixes**

Reviewer checks deterministic fingerprinting, finite-value validation, provider-registry backwards compatibility, secret absence, and no M09/M10 leakage. Fix all Critical/Important findings with fresh regression RED/GREEN when behavior changes.

### Task 2: Embedding service batching and direct-provider adapters

**Files:**
- Create: `src/Embeddings/EmbeddingService.php`
- Create: `src/Embeddings/EmbeddingBatchConfig.php`
- Modify: `src/Providers/OpenAI/OpenAiProvider.php`
- Modify: `src/Providers/OpenRouter/OpenRouterProvider.php`
- Modify: `src/Providers/ProviderBootstrap.php`
- Test: `tests/Unit/Embeddings/EmbeddingServiceTest.php`
- Test: `tests/Unit/Providers/OpenAI/OpenAiEmbeddingTest.php`
- Test: `tests/Unit/Providers/OpenRouter/OpenRouterEmbeddingTest.php`

**Interfaces:**
- `EmbeddingBatchConfig::__construct(int $max_inputs_per_batch)` with bounded positive maximum.
- `EmbeddingService::__construct(EmbeddingProvider $provider, EmbeddingBatchConfig $config)`.
- `EmbeddingService::embed(EmbeddingRequest $request): EmbeddingResult` partitions inputs deterministically, restores global input order, aggregates known usage, rejects count/index/dimension inconsistencies, and fails the whole call on a failed batch.
- OpenAI/OpenRouter adapters implement `EmbeddingProvider` using existing credential/http/redaction infrastructure and fixed endpoints.

- [ ] **Step 1: RED tests for deterministic batching and response validation**

Test 5 inputs with batch size 2 -> provider receives 2/2/1; returned vectors remain input order. Test missing/duplicate/out-of-range indices and inconsistent dimensions fail closed. Test unknown usage remains unknown unless all batch usage is known.

- [ ] **Step 2: RED tests for OpenAI/OpenRouter normalized HTTP requests**

With fake transport assert fixed embedding endpoint, auth header presence at transport boundary, redirects disabled by existing client policy, ordered `input`, model ID, optional dimensions only when configured, sanitized errors, and no paid live call.

- [ ] **Step 3: Record RED exact SHA/CI**

Expected failures are missing embedding service/provider behavior, not syntax/fixture errors.

- [ ] **Step 4: Implement minimum service and adapters**

Do not add automatic retries. Reuse existing `ProviderException` normalization and credential resolver. Provider bootstrap registers embedding capability only where supported.

- [ ] **Step 5: GREEN focused + full CI, review, fix findings**

Require PHP quality and existing provider tests green; independent review must explicitly check billable-call retry absence, credential redaction, batching bounds, and malformed-response fail-closed behavior.

### Task 3: Vector-store contracts, filters, registry, and contract suite

**Files:**
- Create: `src/VectorStore/VectorStore.php`
- Create: `src/VectorStore/VectorUpsertStore.php`
- Create: `src/VectorStore/VectorDeleteStore.php`
- Create: `src/VectorStore/VectorSearchStore.php`
- Create: `src/VectorStore/VectorStoreRegistry.php`
- Create: `src/VectorStore/VectorStoreCapabilities.php`
- Create: `src/VectorStore/VectorStoreHealth.php`
- Create: `src/VectorStore/VectorCollection.php`
- Create: `src/VectorStore/VectorRecord.php`
- Create: `src/VectorStore/VectorWriteResult.php`
- Create: `src/VectorStore/VectorSearchRequest.php`
- Create: `src/VectorStore/VectorSearchResult.php`
- Create: `src/VectorStore/VectorMatch.php`
- Create: `src/VectorStore/Filter/VectorFilter.php`
- Create: `src/VectorStore/Filter/EqualsFilter.php`
- Create: `src/VectorStore/Filter/InFilter.php`
- Create: `src/VectorStore/Filter/AndFilter.php`
- Create: `src/VectorStore/VectorStoreException.php`
- Create: `src/VectorStore/VectorStoreErrorCode.php`
- Test: `tests/Unit/VectorStore/VectorStoreContractsTest.php`
- Test: `tests/Unit/VectorStore/VectorStoreRegistryTest.php`
- Test support: `tests/Support/VectorStore/InMemoryVectorStore.php`
- Test support: `tests/Support/VectorStore/VectorStoreContractAssertions.php`

**Interfaces:**
- Base store exposes ID/capabilities/health only.
- Operation interfaces are separate and capability-checked.
- `VectorSearchRequest` requires collection, query vector, top-K, compatibility fingerprint, optional typed filter.
- Metadata keys/values are validated against a fixed portable scalar allowlist and bounded counts/lengths.

- [ ] **Step 1: RED contract/value validation tests**

Assert duplicate registry IDs fail, capability requirements fail before operations, dimension/fingerprint mismatch rejects writes/search, top-K/filter cardinality bounds apply, and raw vendor filter fragments have no API path.

- [ ] **Step 2: RED reusable in-memory contract suite**

Assert upsert replaces same stable ID, delete is collection-scoped/idempotent, search honors compatibility + filters, result ordering is score-descending with stable-ID tie break, and cross-collection records never leak.

- [ ] **Step 3: Record RED SHA/CI, implement minimum contracts/test adapter, then GREEN**

Keep in-memory adapter under tests only. Production code remains infrastructure-neutral.

- [ ] **Step 4: Independent review**

Check isolation, metadata bounds, compatibility enforcement, deterministic ordering, and unsupported capability behavior.

### Task 4: Local WordPress vector store and bounded candidate search

**Files:**
- Create: `src/Database/Migrations/V003CreateVectorCollectionsTable.php`
- Create: `src/Database/Migrations/V004CreateVectorsTable.php`
- Modify: `src/Database/MigrationRegistry.php` or existing migration composition file discovered at execution time
- Create: `src/VectorStore/Local/LocalVectorStore.php`
- Create: `src/VectorStore/Local/LocalVectorStoreConfig.php`
- Create: `src/VectorStore/Local/CosineSimilarity.php`
- Test: `tests/Unit/VectorStore/Local/CosineSimilarityTest.php`
- Test: `tests/Unit/VectorStore/Local/LocalVectorStoreConfigTest.php`
- Test: `tests/Unit/Database/LocalVectorMigrationSqlTest.php`
- Integration: `tests/Integration/VectorStore/LocalVectorStoreIntegrationTest.php`

**Interfaces:**
- Dedicated per-site tables use `$wpdb->prefix`, versioned migrations, no giant options blob.
- `LocalVectorStoreConfig` includes hard `candidate_limit` and `max_top_k`.
- Search SQL scopes site/collection/fingerprint/portable filters and applies hard candidate limit before vectors reach PHP.
- `CosineSimilarity::score(array $query, array $candidate): float` rejects dimensions mismatch/non-finite/zero-norm ambiguity explicitly.

- [ ] **Step 1: RED migration/config/similarity tests**
- [ ] **Step 2: RED WordPress integration test proving collection isolation, filters, upsert/delete, and bounded candidates**
- [ ] **Step 3: Record RED SHA/CI**
- [ ] **Step 4: Implement migrations + bounded local store**
- [ ] **Step 5: GREEN full PHP + WordPress smoke; benchmark bounded candidate path**
- [ ] **Step 6: Document practical local-store limit and independent security/performance review**

### Task 5: Qdrant adapter

**Files:**
- Create: `src/VectorStore/Qdrant/QdrantVectorStore.php`
- Create: `src/VectorStore/Qdrant/QdrantConfig.php`
- Create: `src/VectorStore/Http/VectorStoreHttpClient.php` only if existing provider HTTP client cannot be reused without provider coupling
- Test: `tests/Unit/VectorStore/Qdrant/QdrantVectorStoreTest.php`

**Interfaces:**
- Implements raw upsert/delete/search capabilities plus portable filters and health.
- Admin-owned HTTPS endpoint is validated; redirects disabled; API key stays secret.
- Collection/index configuration must match `VectorIndexProfile` before writes/search.

- [ ] **Step 1: RED fake-HTTP tests for upsert/delete/search/filter mapping and sanitized errors**
- [ ] **Step 2: RED compatibility mismatch test proving no write request occurs**
- [ ] **Step 3: Record RED, implement minimum adapter, GREEN, independent review**
- [ ] **Step 4: Add opt-in live contract test hook without making CI require Qdrant**

### Task 6: Pinecone adapter

**Files:**
- Create: `src/VectorStore/Pinecone/PineconeVectorStore.php`
- Create: `src/VectorStore/Pinecone/PineconeConfig.php`
- Test: `tests/Unit/VectorStore/Pinecone/PineconeVectorStoreTest.php`

- [ ] **Step 1: RED fake-HTTP tests for namespace-scoped upsert/delete/query/filter behavior**
- [ ] **Step 2: RED index dimension/metric incompatibility test**
- [ ] **Step 3: Record RED, implement minimum adapter, GREEN, independent review**
- [ ] **Step 4: Opt-in live contract hook only**

### Task 7: Chroma adapter

**Files:**
- Create: `src/VectorStore/Chroma/ChromaVectorStore.php`
- Create: `src/VectorStore/Chroma/ChromaConfig.php`
- Test: `tests/Unit/VectorStore/Chroma/ChromaVectorStoreTest.php`

- [ ] **Step 1: RED fake-HTTP tests for collection identity, explicit embeddings, metadata filters, delete/query**
- [ ] **Step 2: RED compatibility/unsupported-filter tests**
- [ ] **Step 3: Record RED, implement minimum adapter, GREEN, independent review**
- [ ] **Step 4: Opt-in live contract hook only**

### Task 8: OpenAI managed vector-store capability adapter

**Files:**
- Create: `src/VectorStore/OpenAI/OpenAiManagedVectorStore.php`
- Create: `src/VectorStore/OpenAI/OpenAiVectorStoreConfig.php`
- Create only if public API requires separate operations: `src/VectorStore/Managed/ManagedVectorStore.php`
- Test: `tests/Unit/VectorStore/OpenAI/OpenAiManagedVectorStoreTest.php`

**Interfaces:**
- Exposes only capabilities supported by the current public OpenAI vector-store/file-search API.
- Must not implement raw-vector interfaces unless the public API truly supports caller-supplied vectors with required semantics.

- [ ] **Step 1: Re-verify current official OpenAI public API and record supported capability matrix in milestone docs**
- [ ] **Step 2: RED test proving unsupported raw-vector capability is not advertised/castable**
- [ ] **Step 3: RED fake-HTTP tests for supported managed operation(s), fixed endpoint, auth, errors**
- [ ] **Step 4: Implement minimum truthful adapter, GREEN, independent review**

### Task 9: M07 plan-to-embedding/vector integration and M08 closeout

**Files:**
- Create: `src/Embeddings/IndexEmbeddingExecutor.php` or narrower integration service selected from actual Task 1-8 contracts
- Test: `tests/Integration/Embeddings/IndexEmbeddingExecutorTest.php`
- Modify: `docs/milestones/M08-embeddings-vector-stores.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/progress/TEST-MATRIX.md`
- Modify: `docs/progress/SECURITY.md`
- Modify: `docs/progress/KNOWN-ISSUES.md` / `TECH-DEBT.md` only when actual findings exist
- Modify: `docs/DECISIONS.md` only for material final architecture decisions not already covered by ADR-006/007

- [ ] **Step 1: RED integration test from M07 `IndexPlan::upsert` chunks through embedding service to a compatible vector store**

Assert unchanged/metadata-refresh/delete semantics are not accidentally converted into unnecessary embedding work; incompatible target profile stops before paid embedding/write execution.

- [ ] **Step 2: Record RED, implement minimum integration boundary, GREEN**
- [ ] **Step 3: Run full contract/unit/integration/PHP/JS/package/WordPress verification on exact SHA**
- [ ] **Step 4: Security review**

Review credentials, SSRF/endpoint validation, namespace/site isolation, metadata sensitivity, error redaction, bounded input/top-K/filter/candidate sizes.

- [ ] **Step 5: Performance review and local benchmark**

Record batch/candidate/top-K limits and benchmark evidence without claiming dedicated-vector-engine scale.

- [ ] **Step 6: Fresh independent whole-M08 review**

Require 0 unresolved Critical/Important findings. Any behavior fix gets its own genuine RED/GREEN regression evidence.

- [ ] **Step 7: Reconcile durable docs and verify exact final PR head**

All permanent CI jobs must be green on the exact final documentation/code SHA.

- [ ] **Step 8: Finish branch, merge with expected-head SHA, verify fresh `main` CI, then mark M08 complete**

Do not start M09 until post-merge `main` verification is green.

## Plan self-review

- Spec coverage: embedding contracts, batching, provider adapters, compatibility fingerprints, local store, target external adapters, health/capabilities/filters, security, and performance all map to explicit tasks.
- Placeholder scan: no TODO/TBD/"implement later" placeholders; execution-time discovery is limited to the exact existing migration composition filename and current public API capability verification where vendor behavior can change.
- Type consistency: Task 2 consumes Task 1 provider/profile contracts; Tasks 4-8 consume Task 3 vector-store contracts; Task 9 composes the completed interfaces.
- TDD: every behavior task starts with test-only RED evidence before production implementation.
- Scope: M09 retries/jobs and M10 hybrid retrieval remain excluded.

Status: **AUTO-APPROVED — SCHEDULED MODE**. Inline execution is selected under ADR-017 because this runtime exposes no independent subagent dispatch interface.