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

## Review correction — multisite / long-lived worker isolation

A later fresh-session security/architecture review found one **Important** defect in the original idempotence guard: the process-global `local_registered` flag could permanently retain the first WordPress site's `Connection` / `TableNames` authority. In a multisite or long-lived PHP worker, a later site could therefore resolve the first site's `local-wordpress` adapter and table prefix.

Strict TDD evidence for the correction:

- Test-only checkpoint `37235ae17c8ccd183ee79f47fee6730072f7f53f` / CI `34668673027` — **NOT RED**.
  - Composer validation/install succeeded.
  - PHPCS stopped before PHPUnit because the new `setUp()` / `tearDown()` methods lacked required doc comments.
  - This checkpoint is retained honestly and is not counted as RED.
- Genuine RED: `cb44a904da693bdfad061ff35c9be95faec3df48` / CI `34668765144`.
  - PHPCS and PHPStan passed; PHPStan analyzed all 318 files without error.
  - PHPUnit ran 745 tests / 3127 assertions.
  - Exactly one assertion failed: the second-site registration expected `wp_2_rag_ai_vectors` but still received `wp_rag_ai_vectors`.
- Genuine GREEN: `41fccbccd1cebc1994cab1f70d63a6c3df8fa5fb` / CI `34668826324`.
  - `php-quality`: GREEN, including Composer validation, PHPCS/PHPStan/PHPUnit and Composer audit.
  - `js-quality`: GREEN.
  - `package`: GREEN.
  - `wordpress-smoke`: GREEN, including activation, database, provider, knowledge, file-ingestion and WooCommerce knowledge smoke coverage.

The fix keeps one active process-local registry authority, but scopes the registered local adapter to the current per-site vector table authority. When the WordPress site/table scope changes, the local registry is re-composed before retrieval, preventing the prior site's database/table binding from being reused.

Updated scoped correctness/security/performance/architecture review after the correction: **0 Critical / 0 Important unresolved for this vector-bootstrap prerequisite**.

## Review

- Correctness: `registry()` exposes the active process-local registry authority; `register_local()` is idempotent only while the current per-site table authority is unchanged.
- Security: local vector access cannot retain another site's table prefix across a site-scope change; no credentials, request overrides, secrets, external calls, arbitrary identifiers, or request-owned runtime options enter this composition seam.
- Performance: registry access and site-scope comparison are constant-time; registration performs no vector retrieval or provider work.
- Architecture: local production vector resolution has a shared composition seam rather than a Playground-specific/request-local registry.

Independent reviewer transport was unavailable during these checkpoints, so this is the repository-approved scoped fallback review and is not represented as the final independent Task 6 closeout review.

## Current continuation

The production Playground runtime and protected callback are now composed on the active Task 6 branch: `AdminRestBootstrap::run_playground()` parses through `PlaygroundRequest`, creates the current WordPress connection/table authority, and delegates through `PlaygroundRuntimeBootstrap::handler()` into the existing production retrieval/chat graph. Integration coverage also correlates the projected Playground evidence with the retrieval consumed by the M10/M11 chat path and proves semantic and lexical channels execute once.

Continue Task 6 with a fresh correctness/security/performance/architecture review of the completed runtime/callback/integration surface, repair every Critical/Important finding under strict TDD, add any missing WordPress-level route/smoke evidence required by the milestone, then persist a Task 6 closeout with exact-final-head CI before advancing to Task 7.
