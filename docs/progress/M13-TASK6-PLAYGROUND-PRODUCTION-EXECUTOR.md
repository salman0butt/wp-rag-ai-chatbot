# M13 Task 6 — Playground Production Executor

Status: **COMPLETE SUBUNIT — verified GREEN; Task 6 overall remains IN PROGRESS**

## Purpose

Task 6 must execute the existing M10/M11 production path exactly once and expose diagnostics from the same `RetrievalResult` consumed by `ChatOrchestrator`. The Playground must not run a second retrieval, scoring, fusion, or reranking pass solely to produce debug output.

This subunit adds the thin request-local `ProductionPlaygroundExecutor` boundary around an already-composed M11 `ChatOrchestrator`, trusted `ChatAccessContext`, the request-local `PlaygroundRetrievalCapture`, Task 5 `DebugTraceProjector`, the selected model identifier, and grounding mode.

## Behavior

`ProductionPlaygroundExecutor::execute()`:

1. rejects a capture that already contains a previous result, preventing stale observed retrieval evidence from being reused;
2. constructs one normalized `ChatRequest` from the validated question, selected model, and grounding mode;
3. invokes the injected M11 `ChatOrchestrator::respond()` exactly once;
4. reads the request-local `PlaygroundRetrievalCapture` after M11 has observed the exact production retrieval result;
5. fails closed if the expected retrieval observation is missing;
6. projects that exact result through Task 5 `DebugTraceProjector`;
7. returns the existing typed `PlaygroundExecutionResult` with normalized chat output, bounded/redacted debug trace, explicit model ID, and non-negative total latency.

The executor does not construct or invoke a second M10 retrieval path.

## TDD evidence

### Genuine RED

Test-only commit `c7fb9f84979625305f8aa6d0e02d96b5a7f970b8` / CI `34463385235`:

- PHP static analysis completed with no errors;
- PHPUnit reached the new production-executor contract regression;
- 701 tests / 2,981 assertions;
- exactly one failure: `ProductionPlaygroundExecutor` did not exist.

This is the genuine RED witness for this subunit.

### Production implementation checkpoints — not GREEN

`f81b65bc70a5f6262948dfaaf8be8d7a61da6d9f` introduced the minimal production executor, but CI `34463513780` stopped in PHPCS because five constructor-docblock alignment violations were present. PHPUnit did not run on that SHA, so it is explicitly **not** claimed as GREEN.

`defb792bad36cec49fb1863eed1e5469663acb19` corrected only those formatting violations. CI `34463626431` then reached PHPStan, which could not infer that the externally composed final `ChatOrchestrator` owns the same request-local observer instance injected into the executor. It therefore treated the post-`respond()` capture as permanently null and the projector path as unreachable. This checkpoint is also explicitly **not** GREEN.

`809b474a91319eb0ce2e21e37fbadeff6c146d8f` documented that cross-object callback boundary and added narrowly scoped PHPStan suppressions, but CI `34463801408` rejected the initial suppression-comment syntax before tests. No behavior changed and this checkpoint is **not** GREEN.

### GREEN

`8b6e80d5cd3943e236a5605b3f8d86d70cb8651c` corrected only the PHPStan suppression syntax while retaining the same production behavior. Exact-head CI `34464050456` completed successfully across all four permanent jobs:

- `php-quality`, including PHPCS, PHPStan, PHPUnit, and Composer audit;
- `js-quality`;
- `package`;
- complete `wordpress-smoke`, including environment startup, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment cleanup.

This is the final GREEN witness for the production-executor subunit.

## Correctness / security / performance review

- M11 remains the only chat execution path in this executor: one `ChatOrchestrator::respond()` call is made.
- The exact `RetrievalResult` observed by M11 is read from `PlaygroundRetrievalCapture` and passed to the existing Task 5 projector; no parallel M10 call exists here.
- Missing retrieval observation fails closed instead of fabricating or separately recomputing diagnostics.
- A capture that already contains evidence is rejected before execution, preventing stale observed evidence from crossing a later Playground result.
- The executor introduces no recursive serialization and no direct exposure of query text, provider payloads, credentials, or raw errors.
- Retrieval debug output remains bounded/redacted by `DebugTraceProjector`.
- Additional work outside the existing M11 path is O(1) plus the already-bounded Task 5 projection.
- The PHPStan suppressions are limited to the known cross-object observer callback that static analysis cannot correlate; they do not suppress general file/class errors.
- This subunit contains no UI, so there is no subunit-specific accessibility interaction to review.

This is a scoped coordinator review of the bounded subunit. It is **not** the mandatory genuinely independent final Task 6 closeout review, which remains required before Task 6 is marked complete.

## Exact continuation point

Task 6 remains **IN PROGRESS**. Continue without duplicating retrieval:

1. Recover the concrete existing WordPress production dependencies needed to compose one request-local M11 `ChatOrchestrator` and trusted `ChatAccessContext`.
2. Create one `PlaygroundRetrievalCapture` and inject that same instance into both the request-local `ChatOrchestrator` observer slot and `ProductionPlaygroundExecutor`.
3. Use the existing provider registry/configuration and existing M10/M11 services rather than creating a parallel retrieval/generation stack.
4. Wire that request-local executor into the existing bounded/sanitized `PlaygroundRestResource`.
5. Under a fresh genuine RED, register protected `POST /admin/debug/playground` behind `AdminCapability::can_manage`.
6. Add REST/integration/WordPress smoke coverage proving one production execution, bounded success output, and stable sanitized failures.
7. Perform the mandatory fresh-session independent Task 6 correctness/security/performance review and resolve every Critical/Important finding under RED → GREEN.
8. Require all four permanent CI jobs GREEN on the final Task 6 SHA before marking Task 6 COMPLETE or starting Task 7.

PR #18 remains draft and must not merge while Tasks 6–8 are unfinished.
