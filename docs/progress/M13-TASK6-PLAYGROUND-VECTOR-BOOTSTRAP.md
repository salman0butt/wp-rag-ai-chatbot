# M13 Task 6 — Shared Vector Store Bootstrap Authority

Status: **COMPLETE prerequisite**

## Scope

Task 6 needs one production-owned vector-store registry authority that can be reused by the Playground composition path without creating a Playground-only adapter stack. This checkpoint establishes that authority in `VectorStoreBootstrap::registry()`.

The selected design is **AUTO-APPROVED — SCHEDULED MODE**: expose one process-local `VectorStoreRegistry` through the existing `src/VectorStore` production namespace. The bootstrap seam owns composition only; it performs no vector search, embedding work, provider call, credential handling, or network I/O.

## Architectural constraints preserved

- The Playground must consume the same production registry authority as other runtime consumers.
- No request-level vector-store override is introduced.
- No Playground-specific registry is created.
- Capability validation remains inside the existing `VectorStoreRegistry` / `PlaygroundVectorStoreResolver` seams.
- This checkpoint does not duplicate semantic retrieval, lexical retrieval, fusion, reranking, grounding, prompt construction, memory, citations, provider selection, embedding selection, or vector-store selection.

## Strict TDD evidence

- Genuine RED: `efafb4332170986b70027acf70178f56ba687aad` / CI `34640827472`.
  - PHP static analysis reached the intended behavior test.
  - PHPUnit failed because the shared `VectorStoreBootstrap` authority did not yet exist.
- Initial implementation: `8ff3e1c2f3ed10b7e549d24c15b9bc29ab81d4c0` / CI `34640964820` — **NOT GREEN**.
  - PHPCS rejected the production file before complete PHP verification because the static property required the repository-standard `@var` annotation.
- Genuine GREEN: `8bca784c9f5b7eea8778f006931de21961c7f9fe` / CI `34641106608`.
  - Exact-head workflow conclusion: **success**.
  - Required permanent CI completed green, including PHP quality, JS quality, package validation, and WordPress smoke.

The chronology above is preserved exactly; the intermediate standards failure is not relabeled as GREEN.

## Review

Scoped correctness/security/performance/architecture review: **0 Critical / 0 Important unresolved**.

- Correctness: `registry()` returns the same process-local registry authority on repeated access.
- Security: no credentials, request overrides, secrets, external calls, or arbitrary identifiers enter this composition seam.
- Performance: constant-time process-local bootstrap access; no retrieval work occurs here.
- Architecture: this establishes a shared production authority rather than a parallel Playground implementation.

This scoped review is not the final independent Task 6 closeout review.

## Exact continuation

The production route is registered, but `AdminRestBootstrap::run_playground()` still fails closed with `invalid_request`. Under a new genuine RED, bind a valid bounded `PlaygroundRequest` into the existing persisted configuration/provider/vector-store/executor/resource authorities exactly once.

The callback must not accept request-level credentials, provider/model overrides, embedding overrides, vector-store options, or retrieval-limit overrides, and it must not construct a second retrieval/chat pipeline.

After the callback binding is exact-head GREEN, continue immediately with REST/integration/WordPress smoke coverage and the fresh independent Task 6 correctness/security/performance review before marking Task 6 complete.
