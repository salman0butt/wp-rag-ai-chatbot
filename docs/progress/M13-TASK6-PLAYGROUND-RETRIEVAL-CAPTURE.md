# M13 Task 6 — Playground Retrieval Capture

Status: **IMPLEMENTED — exact-head CI verification pending on this documentation SHA**

## Purpose

Task 6 must execute the existing M10/M11 path exactly once and expose diagnostics from the same `RetrievalResult` consumed by `ChatOrchestrator`. It must not run a second retrieval/scoring/reranking pass merely to build debug output.

The existing `ChatRetrievalObserver` seam provides the exact request-local `RetrievalResult`, but the Playground production executor still needed a concrete request-local holder it can read after one M11 execution. `PlaygroundRetrievalCapture` is that holder.

## TDD evidence

### Genuine RED

Test-only commit `78dabbc2b9d76eebdfcf0e0feec48e3424644a5f` / CI `34459998696`:

- PHP static analysis completed with no errors;
- PHPUnit reached the new regression;
- 700 tests / 2,977 assertions;
- exactly one error: `PlaygroundRetrievalCapture` did not exist.

This is the genuine RED witness for the new production behavior.

### Production implementation checkpoint — not GREEN

`20ee8a821734ebd8c74747effe1a136493ebca16` introduced the minimal request-local observer implementation, but CI `34460133517` stopped in PHPCS because the private retrieval-result property docblock lacked an `@var` tag. PHPUnit did not run on that SHA, so it is explicitly **not** claimed as GREEN.

### Style closeout / GREEN candidate

`09beea60ab8773e4cbafe81a1e6e58a0b5186fff` added only the missing property type documentation. Exact-head CI `34460212845` has `php-quality`, `js-quality`, and `package` green; complete `wordpress-smoke` must also finish green before this SHA is treated as the final GREEN witness.

## Correctness / security / performance review

- The capture implements the existing `ChatRetrievalObserver` contract; it does not introduce another retrieval API or code path.
- `observe()` stores the exact `RetrievalResult` object produced by the one M10 retrieval already used by M11.
- `result()` returns that exact object reference, so a future executor can project the same evidence through Task 5 `DebugTraceProjector` after chat execution.
- The capture starts empty, allowing the executor to detect a missing observation rather than fabricate diagnostics.
- It serializes, logs, hashes, or copies no query/provider/credential data itself.
- Storage and read are O(1); no scoring, reranking, network call, database query, or candidate copying occurs here.
- This subunit contains no UI, so there is no Task-6-specific accessibility interaction to review.

This is a coordinator review of this bounded subunit, not the mandatory fresh-session independent Task 6 closeout review.

## Exact continuation point

After this documentation SHA is fully green, continue Task 6 without reimplementing retrieval:

1. Build the smallest request-local production `PlaygroundExecutor` implementation around the existing M03/M10/M11 services.
2. Instantiate one `PlaygroundRetrievalCapture` per execution and pass it as the `ChatRetrievalObserver` to `ChatOrchestrator`.
3. Execute one M11 `ChatRequest` only once.
4. Require that the capture contains the exact retrieval result; project that result through Task 5 `DebugTraceProjector`.
5. Return the already-defined typed `PlaygroundExecutionResult` and existing bounded/allow-listed `PlaygroundRestResource` projection.
6. Only after that production composition is tested should `POST /admin/debug/playground` be registered behind `AdminCapability::can_manage`.
7. Add REST/integration/WordPress smoke coverage and perform the final genuinely independent Task 6 correctness/security/performance review before marking Task 6 complete.

Task 6 remains **IN PROGRESS**. PR #18 remains draft and must not merge while Tasks 6–8 are unfinished.
