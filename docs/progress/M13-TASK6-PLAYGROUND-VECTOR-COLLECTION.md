# M13 Task 6 — Playground Vector Collection Reconstruction

Status: **COMPLETE prerequisite**

## Scope

Task 6 needs to reuse the production semantic-retrieval graph without inventing a parallel Playground vector stack. This bounded prerequisite reconstructs the canonical production `VectorCollection` from the already-resolved persisted retrieval and semantic configuration.

The selected design is **AUTO-APPROVED — SCHEDULED MODE**: `PlaygroundVectorCollectionResolver` performs only deterministic value-object composition and leaves persisted fingerprint/dimension compatibility enforcement to the existing production vector-store authority.

## Design

`PlaygroundVectorCollectionResolver::resolve()` returns a `VectorCollection` using:

- the explicit persisted `PlaygroundRetrievalConfiguration::$collection_id`;
- a canonical `VectorIndexProfile` built from `PlaygroundSemanticConfiguration::$embedding_profile` and `$distance`.

It intentionally performs no database/network I/O, no vector search, no embedding generation, no request-level runtime override, and no duplicate fingerprint/dimension validation.

## TDD evidence

- Preparation `49b50c43a158d6327e35dbb9734f66300288a469` is **NOT RED** because the initial test fixture strategy was invalid.
- Genuine RED `ef31aeb2de91547d3192121aea05d96778fa43e5` / CI `34544180850`: PHPStan completed without errors; PHPUnit executed **718 tests / 3,042 assertions** with exactly one intended error because `PlaygroundVectorCollectionResolver` did not exist. `js-quality`, `package`, and complete `wordpress-smoke` were GREEN.
- GREEN production SHA `05db6a3db65a8c4db7a6b8cdf670fdc3c3301a4e` / CI `34559781767`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed. PHPStan completed **306/306** with no errors; PHPUnit passed **718 tests / 3,045 assertions**; Composer audit reported no security advisories.

## Review

Scoped cold correctness/security/performance review: **0 Critical / 0 Important unresolved**.

- Correctness: uses the canonical `VectorCollection` and `VectorIndexProfile` types and exact persisted selector/profile values.
- Security: introduces no credentials, untrusted runtime overrides, external calls, or new exposure surface.
- Performance: O(1) in-memory value-object construction only.
- Compatibility: production vector-store code remains the single authority for persisted collection fingerprint/dimension compatibility.

This scoped review is not the final independent Task 6 closeout review.

## Exact continuation

Under a fresh strict-TDD RED, resolve `PlaygroundSemanticConfiguration::$vector_store_id` through the existing production `VectorStoreRegistry::search()` authority, failing closed for unknown or non-search-capable stores. Then continue composing the existing embedding provider/service and semantic + lexical M10/M11 graph exactly once before restoring the protected Playground route.
