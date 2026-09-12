# M13 Task 6 — Playground Vector Store Resolution

Status: **COMPLETE prerequisite**

## Scope

Task 6 must reuse the production vector-store registry instead of creating a Playground-only adapter path. This bounded prerequisite resolves the already-persisted `PlaygroundSemanticConfiguration::$vector_store_id` through the production `VectorStoreRegistry::search()` authority.

The selected design is **AUTO-APPROVED — SCHEDULED MODE**: `PlaygroundVectorStoreResolver` is a narrow composition seam. It delegates the persisted identifier to `VectorStoreRegistry::search()` and returns the exact registered `VectorSearchStore`. The production registry remains the single authority for unknown-store and search-capability validation.

## Design

`PlaygroundVectorStoreResolver::resolve()`:

- consumes only the persisted `PlaygroundSemanticConfiguration`;
- delegates `vector_store_id` directly to `VectorStoreRegistry::search()`;
- preserves the registry's fail-closed behavior for unknown identifiers;
- preserves the registry's fail-closed behavior for stores that do not implement `VectorSearchStore`;
- adds no default/fallback store, request-level override, credentials, external I/O, or vector-search execution.

## TDD evidence

- Test-only checkpoints `5c165603c72458892652caa30711acd52b8552ba` and `0d8b12107950da502d15936f5f0b6e41ce6c1638` are **NOT RED** because PHP verification stopped at coding-standard issues before PHPUnit.
- Premature implementation checkpoint `52e50e55b7cbc1e9f23c99f719b705285b0dd289` is **NOT GREEN**: exact CI failed PHPCS because the new anonymous fixture constructors were missing parameter documentation.
- Convention repair `39f5544914bfafa95658de6e3c180a7b692e9673` proved the implementation/test set was convention-clean, but strict TDD evidence was repaired rather than retroactively relabeling the earlier checkpoints.
- Genuine RED `64b511102e4c9c73a72c52025ab3b8c4efcddc25` / CI `34577015318`: PHPCS and PHPStan passed; PHPStan completed **306/306** with no errors; PHPUnit executed **721 tests / 3,045 assertions** and failed with exactly **3 errors**, all because `PlaygroundVectorStoreResolver` was absent.
- Genuine GREEN production SHA `e8096ad6b3734fb8a1d89adb35859f70708b0af2` / CI `34577117948`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed. PHPStan completed **307/307** with no errors; PHPUnit passed **721 tests / 3,050 assertions**; Composer audit reported no security advisories. WordPress smoke passed activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment shutdown.

## Review

Scoped cold correctness/security/performance review: **0 Critical / 0 Important unresolved**.

- Correctness: the resolver delegates the exact persisted identifier to the canonical registry and returns the exact registered search adapter.
- Fail-closed behavior: unknown stores and non-search-capable stores use the registry's existing exceptions; no fallback/default selection exists.
- Security: the seam accepts no credentials or arbitrary request-level runtime override and performs no external call.
- Performance: one O(1) registry lookup; no vector search or embedding work occurs during resolution.
- Architecture: capability validation remains centralized in `VectorStoreRegistry::search()` instead of being duplicated in the Playground layer.

This scoped cold review is not the final independent Task 6 closeout review.

## Exact continuation

Under a fresh strict-TDD RED, resolve the persisted embedding provider through the existing provider registry and compose the existing `EmbeddingService`, canonical `VectorCollection`, resolved `VectorSearchStore`, and `SemanticRetriever`. Then compose the existing semantic + lexical retrieval, grounding, prompt, memory, citation, `PlaygroundRetrievalCapture`, `ChatOrchestrator`, and `ProductionPlaygroundExecutor` graph exactly once before restoring protected `POST /admin/debug/playground`.
