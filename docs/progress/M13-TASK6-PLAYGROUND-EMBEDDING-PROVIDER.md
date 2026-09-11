# M13 Task 6 — Playground Embedding Provider Resolution

Status: **COMPLETE prerequisite**

## Scope

Task 6 must reuse the production provider registry instead of creating a Playground-only embedding adapter path. This bounded prerequisite resolves the embedding provider ID already persisted in `PlaygroundSemanticConfiguration::$embedding_profile` through the production `ProviderRegistry::embedding()` authority.

The selected design is **AUTO-APPROVED — SCHEDULED MODE**: `PlaygroundEmbeddingProviderResolver` is a narrow composition seam. It delegates the persisted provider ID to `ProviderRegistry::embedding()` and returns the exact registered `EmbeddingProvider`. Because the production registry intentionally exposes embedding capability as a nullable lookup, the Playground seam converts `null` into one stable fail-closed `OutOfBoundsException` rather than choosing a fallback provider.

## Design

`PlaygroundEmbeddingProviderResolver::resolve()`:

- consumes only the persisted `PlaygroundSemanticConfiguration`;
- reads `embedding_profile->provider_id`, which is already part of the canonical persisted semantic identity;
- delegates that ID directly to `ProviderRegistry::embedding()`;
- returns the exact registered `EmbeddingProvider` when embedding capability exists;
- fails closed when the provider ID is unknown or the registered provider has no embedding capability;
- adds no default/fallback provider, request-level provider/model override, credentials, external I/O, provider-availability probe, or embedding execution;
- does not construct `EmbeddingService`; runtime composition remains the next bounded Task 6 unit.

## TDD evidence

- Test-only checkpoint `2fd671ba6d138e8fa544aec880b2384f3e86c568` is **NOT RED** because PHP verification stopped at a coding-standard issue before the intended missing-resolver failure could be accepted.
- Initial implementation checkpoint `309b1887e0457f80317c10bd4f086cf458f60626` is **NOT GREEN** because the same coding-standard issue still stopped PHP verification.
- Convention repair culminating at `8c5d2f6fa45f422875c134040f2cf5d1da0c3cf0` proved the implementation/test pair was clean through PHP verification, JavaScript verification, and packaging. Strict TDD evidence was repaired instead of relabeling the earlier invalid checkpoints.
- Genuine RED `46c7e1c43dea99e15920b6fd4a2f15e311f73fae` / CI `34592282437`: the convention-clean tests remained while only `PlaygroundEmbeddingProviderResolver` was removed. `package` and `js-quality` passed; `php-quality` failed in `composer verify:php` at the expected absent-resolver contract. The immediately preceding convention-clean pair had passed PHP verification, and PHPStan scans production `src` rather than the PHPUnit tests, isolating the missing production class as the intended RED condition.
- Genuine GREEN production SHA `e1cea0467dd40bc356385625e3b67c3d7f888e48` / CI `34592394078`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed. Composer validation, PHP verification, and Composer audit passed. WordPress smoke passed environment startup, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment shutdown.

## Review

Scoped cold correctness/security/performance review: **0 Critical / 0 Important unresolved**.

- Correctness: the resolver reads the canonical persisted embedding-provider identity and returns the exact registry adapter; it does not infer a provider from vector-store or generation configuration.
- Fail-closed behavior: both unknown provider IDs and known providers without embedding capability resolve to `null` in the canonical registry and are converted into the same stable exception; there is no fallback/default selection.
- Security: the seam accepts no credentials or request-level runtime provider/model overrides and performs no external call, availability probe, or embedding execution.
- Performance: one O(1) registry lookup plus a null check; no model or network work occurs during resolution.
- Architecture: provider registration/capability remains centralized in `ProviderRegistry`; `EmbeddingService` remains the production runtime authority for the actual embedding request.

This scoped cold review is not the final independent Task 6 closeout review.

## Exact continuation

Under a fresh strict-TDD RED, compose the resolved `EmbeddingProvider` into the existing `EmbeddingService`, canonical `VectorCollection`, resolved `VectorSearchStore`, and production `SemanticRetriever`, using the already-persisted embedding profile and distance identity without runtime defaults. Then compose the existing semantic + lexical retrieval, grounding, prompt, memory, citation, `PlaygroundRetrievalCapture`, `ChatOrchestrator`, and `ProductionPlaygroundExecutor` graph exactly once before restoring protected `POST /admin/debug/playground`.
