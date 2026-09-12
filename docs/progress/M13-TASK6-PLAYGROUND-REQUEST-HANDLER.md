# M13 Task 6 — Playground Request Handler

Status: **COMPLETE subunit — Task 6 remains IN PROGRESS**

## Scope

Bind one already-validated `PlaygroundRequest` to the established production Playground authorities without duplicating retrieval, generation, scoring, reranking, grounding, prompt construction, memory, citations, provider selection, embedding selection, or vector-store selection.

`PlaygroundRequestHandler` now:

1. resolves persisted bot/source/collection configuration through `PlaygroundConfigurationResolver`;
2. resolves the already-existing production executor through `PlaygroundExecutorResolver`;
3. delegates the bounded question exactly once through `PlaygroundRestResource`;
4. maps unexpected composition/runtime failures to the repository-owned non-leaking `playground_failed` code.

It accepts no request-level credentials, provider/model overrides, embedding overrides, vector-store options, or retrieval limits.

## Strict TDD evidence

### Genuine RED

`3b4bdb1df6de76474e60693abbb97dd28a92672b` / CI `34659505729` added the focused request-handler contract.

The PHP verification path reached PHPUnit and failed for the intended missing behavior: `PlaygroundRequestHandler is missing.` The suite otherwise reached 737 tests / 3,093 assertions. This was a genuine behavioral RED, not a lint, static-analysis, infrastructure, or fixture failure.

### GREEN

`1b85b0185689472bd38035f1757da29b7ef8058d` added the minimal production handler. Exact-head CI `34659592284` passed all permanent jobs:

- `php-quality`;
- `js-quality`;
- `package`;
- complete `wordpress-smoke`.

## Review

Scoped correctness/security/performance/architecture review: **0 Critical / 0 Important unresolved** for this subunit.

- Correctness: persisted configuration is resolved once, the production executor is composed once, and `PlaygroundRestResource` is invoked once.
- Security: no runtime override input is introduced and thrown internals are not exposed.
- Performance: the handler adds only orchestration; it performs no duplicate retrieval or generation.
- Architecture: the handler is a thin seam over the existing Task 6 production graph, not a parallel RAG implementation.
- Accessibility: not applicable to this server-only subunit.

No independent final Task 6 review is claimed here; that remains required after the protected WordPress callback and integration coverage are complete.

## Exact next unfinished work

Compose one shared WordPress runtime authority that can construct `PlaygroundRequestHandler` from existing production services only. Reuse the existing provider registry, shared vector-store registry, persisted repositories, lexical retrieval channel, retrieval/fusion/access services, and M11 chat graph collaborators.

Then, under a separate genuine RED, replace the fail-closed `AdminRestBootstrap::run_playground()` placeholder with the smallest WordPress adapter that parses `WP_REST_Request::get_json_params()` through `PlaygroundRequest::from_array()` and delegates a valid request exactly once to that shared production handler. Malformed input must continue returning `invalid_request`.
