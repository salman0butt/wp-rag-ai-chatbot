# M13 Task 6 — Playground Route Callable Checkpoint

Status: **COMPLETE subunit — Task 6 remains IN PROGRESS**

## Scope

Restore the previously registered protected Playground REST route to a valid WordPress callable without prematurely claiming full production request binding.

The route remains:

- `POST /wp-rag-ai-chatbot/v1/admin/debug/playground`;
- protected by `AdminCapability::can_manage`;
- pointed at `AdminRestBootstrap::run_playground`.

The callback added in this subunit is deliberately fail-closed and returns the existing repository-owned `invalid_request` envelope until the remaining request-local production composition is bound. It does not execute retrieval, generation, scoring, reranking, or any parallel Playground RAG implementation.

## Strict TDD evidence

### Genuine RED

`c514691b91e0ef854cae3e84b1363abb5f133dfe` / CI `34622828694` added a focused regression asserting the registered callback is callable.

The permanent jobs showed the failure was isolated to PHP behavior: `package`, `js-quality`, and complete `wordpress-smoke` passed, while `php-quality` failed after the route had been registered against a missing `AdminRestBootstrap::run_playground` method.

### GREEN

`c2cac2b1902923cd4153d0d8e19e5ee776d71373` adds the smallest production callback required by that RED. Exact-head CI `34630100634` passed all four permanent jobs:

- `php-quality`;
- `js-quality`;
- `package`;
- complete `wordpress-smoke`.

This GREEN proves only the route-callable contract. It does **not** prove full Task 6 execution or REST request binding.

## Review

Scoped correctness/security/performance review: **0 Critical / 0 Important unresolved** for this narrow subunit.

- Correctness: the route now resolves to a real callable and fails closed instead of invoking an undefined callback.
- Security: capability protection remains unchanged; no request-level credential/provider/model/vector-store/retrieval override is accepted; no provider/internal error is exposed.
- Performance: the temporary fail-closed callback performs no retrieval, provider, database, or network work.
- Accessibility: not applicable to this server-only checkpoint.

PR #18 has no unresolved inline review threads at this checkpoint. This scoped coordinator review does not replace the mandatory fresh independent Task 6 closeout review after production binding/integration is complete.

## Exact next unfinished work

Under a new genuine RED, bind the administrator request's explicit persisted bot/source/collection identifiers and bounded question to the already-verified Task 6 composition chain. The production path must resolve persisted configuration, construct the existing semantic + lexical `HybridRetriever`, create one request-local `PlaygroundRetrievalCapture`, construct the existing M11 `ChatOrchestrator` through `PlaygroundChatGraphResolver`, execute it exactly once through `ProductionPlaygroundExecutor`, and project only the existing `PlaygroundRestResource` safe response.

Do not add a parallel retrieval/generation path and do not accept arbitrary credentials, provider/model overrides, embedding overrides, vector-store options, or request-controlled retrieval limits.
