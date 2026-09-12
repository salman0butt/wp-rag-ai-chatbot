# M13 Task 6 — Shared Vector Store Bootstrap Authority

Status: **COMPLETE prerequisite**

## Scope

Task 6 needs one production-owned vector-store registry authority that can be reused by the Playground composition path without creating a Playground-only adapter stack. This checkpoint establishes that authority in `VectorStoreBootstrap::registry()` and adds the production local WordPress adapter registration seam through that same shared registry.

The selected design is **AUTO-APPROVED — SCHEDULED MODE**: expose one process-local `VectorStoreRegistry` through the existing `src/VectorStore` production namespace, then register the local WordPress adapter exactly once from WordPress runtime composition. The bootstrap seam owns composition only; it performs no vector search, embedding work, provider call, credential handling, or network I/O.

## Architectural constraints preserved

- The Playground must consume the same production registry authority as other runtime consumers.
- No request-level vector-store override is introduced.
- No Playground-specific registry is created.
- The local WordPress adapter is registered on the shared authority rather than on a request-local registry.
- Capability validation remains inside the existing `VectorStoreRegistry` / `PlaygroundVectorStoreResolver` seams.
- This checkpoint does not duplicate semantic retrieval, lexical retrieval, fusion, reranking, grounding, prompt construction, memory, citations, provider selection, embedding selection, or vector-store selection.

## Strict TDD evidence — shared registry authority

- Genuine RED: `efafb4332170986b70027acf70178f56ba687aad` / CI `34640827472`.
  - PHP static analysis reached the intended behavior test.
  - PHPUnit failed because the shared `VectorStoreBootstrap` authority did not yet exist.
- Initial implementation: `8ff3e1c2f3ed10b7e549d24c15b9bc29ab81d4c0` / CI `34640964820` — **NOT GREEN**.
  - PHPCS rejected the production file before complete PHP verification because the static property required the repository-standard `@var` annotation.
- Genuine GREEN: `8bca784c9f5b7eea8778f006931de21961c7f9fe` / CI `34641106608`.
  - Exact-head workflow conclusion: **success**.
  - Required permanent CI completed green, including PHP quality, JS quality, package validation, and WordPress smoke.

## Strict TDD evidence — local WordPress adapter registration

- Genuine RED: `93ff59c471c5e75111f70fee85801b9061e9e04e` / CI `34666502175`.
  - Composer validation/install and PHPStan succeeded.
  - PHPUnit ran 738 tests / 3098 assertions and failed exactly one new behavioral assertion because `VectorStoreBootstrap::register_local()` did not exist.
- Initial implementation: `71b770593f764d7cfd4932a41484bf7aa5615cb1` / CI `34666570819` — **NOT GREEN**.
  - PHPCS stopped verification on the new boolean static property because its member comment lacked the repository-standard `@var` tag; PHPUnit therefore did not run at this checkpoint.
- Genuine GREEN: `b767ffbbf72b97aa6b9428f0fb9f7096a67ef1bf` / CI `34666617930`.
  - `php-quality`: GREEN, including Composer validation, PHPCS/PHPStan/PHPUnit and Composer audit.
  - `js-quality`: GREEN.
  - `package`: GREEN.
  - `wordpress-smoke`: GREEN, including activation, database, provider, knowledge, file-ingestion and WooCommerce knowledge smoke coverage.

The chronology above is preserved exactly; neither intermediate standards failure is relabeled as GREEN.

## Review

Scoped correctness/security/performance/architecture review: **0 Critical / 0 Important unresolved**.

- Correctness: `registry()` returns the same process-local registry authority; `register_local()` composes `LocalVectorStore` into that authority and is idempotent within the process.
- Security: no credentials, request overrides, secrets, external calls, arbitrary identifiers, or request-owned runtime options enter this composition seam.
- Performance: registry access and the idempotence guard are constant-time; registration performs no vector retrieval or provider work.
- Architecture: local production vector resolution now has a shared composition seam rather than requiring a Playground-specific/request-local registry.

Independent reviewer transport was unavailable during this checkpoint, so this is the repository-approved scoped fallback review and is not represented as the final independent Task 6 closeout review.

## Exact continuation

The protected production route is registered, but `AdminRestBootstrap::run_playground()` still fails closed with `invalid_request`. Continue by composing `PlaygroundRequestHandler` only from existing production authorities: persisted configuration repositories, `ProviderBootstrap::registry()`, the shared `VectorStoreBootstrap::registry()`, existing lexical/semantic/hybrid retrieval seams, the existing M11 chat graph, and `PlaygroundRestResource`.

The Playground request is stateless and `ProductionPlaygroundExecutor` does not set a conversation ID, so the existing `ChatOrchestrator` does not read conversation history for this execution. Do not invent a parallel memory implementation or expand unfinished M11 stateful-memory scope merely to complete Task 6.

Under a new genuine RED, bind a valid bounded `PlaygroundRequest` into that existing production composition exactly once, then bind `AdminRestBootstrap::run_playground()` as the thin `WP_REST_Request` adapter. The callback must not accept request-level credentials, provider/model overrides, embedding overrides, vector-store options, or retrieval-limit overrides, and it must not construct a second retrieval/chat pipeline.

After the callback binding is exact-head GREEN, continue immediately with REST/integration/WordPress smoke coverage and the fresh independent Task 6 correctness/security/performance review before marking Task 6 complete.
