# M13 Task 6 — Playground Semantic Retriever Composition

Status: COMPLETE PREREQUISITE — Task 6 remains IN PROGRESS.

## Scope

Compose the persisted Playground semantic runtime identity into the existing production M08/M10 semantic retrieval path without introducing a Playground-specific provider, vector-store, embedding, filtering, or retrieval implementation.

The request-local composition boundary must reuse:

- `PlaygroundEmbeddingProviderResolver` for the exact persisted embedding provider;
- `EmbeddingService` with explicit bounded `EmbeddingBatchConfig`;
- `PlaygroundVectorCollectionResolver` for the canonical `VectorCollection` and persisted embedding/distance identity;
- `PlaygroundVectorStoreResolver` for the exact persisted `VectorSearchStore`;
- production `VectorFilterMapper`;
- explicit bounded production `RetrievalConfig`;
- production `SemanticRetriever` and its existing compatibility/top-K validation.

Composition itself must not execute generation, embedding, or vector search.

## Design / Approval

This is a bounded continuation of the already auto-approved M13 Task 6 design and implementation plan under scheduled-development mode. It adds no new architecture or public/request contract. No additional human approval gate applies.

## Strict TDD Evidence

### Test preparation — NOT RED

Commit `35c4ad7b0eadf2a0b35d48e6cfe28218158737c0` added the semantic-composition regression, but CI `34596798535` stopped in PHPCS on test-fixture documentation before PHPUnit reached the intended missing-production-contract failure. This checkpoint is explicitly **NOT RED**.

### Genuine RED

Commit `7690eaef19980e676e978dc80019c3dd52fa2367` repaired test conventions only. CI `34599924148` then reached the intended failure:

- PHPStan: clean;
- PHPUnit: 725 tests / 3,055 assertions;
- exactly one error in `PlaygroundSemanticRetrieverResolverTest::test_composes_production_semantic_retriever_from_persisted_identity`;
- failure: `WpRagAiChatbot\Admin\Rest\PlaygroundSemanticRetrieverResolver` did not exist;
- `js-quality`: GREEN;
- `package`: GREEN.

This is the genuine RED for the subunit.

### Initial implementation — NOT GREEN

Commit `4e9c2ef063824ced215c95a670699b7aef803942` added the minimal production composer, but CI `34600027776` stopped in PHPCS on three auto-fixable docblock-alignment errors before PHP tests could validate the implementation. This checkpoint is explicitly **NOT GREEN**. The same run's `js-quality`, `package`, and complete `wordpress-smoke` jobs passed.

### Genuine GREEN

Commit `079fe4061f34aa4e0f36fbd542c47a5e4e4d9c86` repaired only those production documentation conventions. Exact-SHA CI `34600110031` passed all permanent jobs:

- `php-quality`: GREEN;
  - PHPStan 309/309 with 0 errors;
  - PHPUnit 725/725 tests / 3,056 assertions;
  - Composer audit: no security vulnerability advisories;
- `js-quality`: GREEN;
- `package`: GREEN;
- `wordpress-smoke`: GREEN through environment start, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and clean environment shutdown.

## Implementation

`PlaygroundSemanticRetrieverResolver` is request-local composition only. It resolves the persisted embedding adapter, constructs the existing `EmbeddingService` with an explicit batch bound, resolves the canonical collection and exact searchable store, and constructs the existing production `SemanticRetriever` with explicit filtering and retrieval configuration.

No provider/network call, embedding, vector search, lexical retrieval, reranking, grounding, generation, or duplicate retrieval is performed during composition.

## Review

Scoped correctness/security/performance review was recorded on PR #18 at exact implementation head `079fe4061f34aa4e0f36fbd542c47a5e4e4d9c86`.

Findings:

- Critical: 0.
- Important: 0.

Correctness: production semantic contracts and existing compatibility validation remain authoritative; no parallel semantic pipeline was introduced.

Security: persisted provider/store identity remains fail-closed through existing registries; no request-level credentials, provider/model/embedding/vector-store overrides, secret serialization, raw provider payload, or fallback selection was added.

Performance: composition is O(1) object wiring and triggers no provider or retrieval work.

Accessibility: not applicable to this server-only composition subunit.

Independent reviewer/subagent transport is not exposed in this runtime, so this scoped review does **not** satisfy or claim the mandatory genuinely fresh independent Task 6 closeout review. That review remains required after Task 6 chat-graph, REST, and integration work is complete.

## Current Task 6 Continuation

The next unfinished unit is production chat-graph composition:

1. Under a fresh genuine RED, compose the existing semantic and lexical retrieval channels, hybrid retrieval/fusion/reranking path, grounding, prompt, memory, citation, `PlaygroundRetrievalCapture`, `ChatOrchestrator`, and `ProductionPlaygroundExecutor` exactly once.
2. Reuse the existing M10/M11 production pipeline; do not create a separate Playground retrieval or generation path.
3. Preserve persisted configuration authority and do not accept request-level credentials/provider/model/embedding/vector-store/retrieval overrides.
4. Only after production chat composition is exact-head GREEN, restore protected `POST /admin/debug/playground` behind `AdminCapability::can_manage` under a new strict RED → GREEN cycle.
5. Then add REST/integration/WordPress smoke coverage and perform the genuinely fresh independent Task 6 correctness/security/performance closeout review.

Task 6 is not complete. Tasks 7-8 remain pending. PR #18 is not merge-ready.
