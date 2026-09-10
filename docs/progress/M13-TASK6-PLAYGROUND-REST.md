# M13 Task 6 — Playground REST Execution

Status: **IN PROGRESS — exact-retrieval observation seam plus bounded/sanitized resource boundary complete; production composition/REST registration pending**

## Goal

Add protected `POST /admin/debug/playground` execution that reuses the existing M10/M11 production retrieval/RAG path, projects the exact retrieval evidence through Task 5 `DebugTraceProjector`, and returns only bounded administrator-safe diagnostics.

## Architecture constraint recovered

`ChatOrchestrator` owns the M10 `HybridRetriever` call internally and returns only `ChatResult`. Running retrieval separately for the playground and then invoking `ChatOrchestrator` would execute retrieval/scoring/reranking twice and could make the debug trace disagree with the evidence used to generate the answer.

Task 6 therefore first adds a request-local optional `ChatRetrievalObserver` seam. `ChatOrchestrator` invokes it immediately after the one successful production retrieval and passes the exact `RetrievalResult` that continues into grounding, prompt construction, generation, and citation validation. Existing callers remain compatible because the dependency defaults to `null`.

## TDD evidence — exact retrieval observation seam

### Test preparation — not RED

`3b280d7c1893db68cccdd6f567807ab6366e49f2` added the test-only observer contract regression, but CI `34442472735` stopped in PHPCS before PHPUnit because of test documentation/alignment conventions. This is explicitly **not** counted as RED.

### Genuine RED

`2a598018070a37f83e8d254c5e6918774a0bb6a2` / CI `34442552312`:

- PHPStan passed with no errors;
- PHPUnit reached the new regression;
- 693 tests / 2,930 assertions;
- exactly 2 failures, both proving the `ChatRetrievalObserver` contract/constructor seam did not yet exist.

### Intermediate implementation checkpoints — not GREEN

- `d5a7c80f67c364d3d56aae80f57fbcc3d5e43744` / CI `34442684602`: PHPCS constructor-doc alignment failure.
- `9e331386e1d9fb723cf926f8e9c513456144afd1` / CI `34442791561`: PHPStan correctly rejected the observer property because it was not yet read.
- `1f2ca6541520a1ce06d566f570aa34f4e1c3a4bc` / CI `34442967643`: PHPStan correctly rejected an unused accessor workaround.

These checkpoints are not represented as GREEN evidence.

### GREEN

Production seam commits:

- `25e35b4a565e5e7d3e19d50e4f6ffdd91a8092ca` — introduces `ChatRetrievalObserver`;
- `060fd76f9b97a587f612edb56dc4bf61832f3751` — final minimal production behavior: optional observer is called once with the exact already-produced `RetrievalResult` immediately after successful retrieval.

Exact-head CI `34443128752` for `060fd76f9b97a587f612edb56dc4bf61832f3751` is GREEN across all four permanent jobs:

- `php-quality`;
- `js-quality`;
- `package`;
- complete `wordpress-smoke`.

## TDD evidence — bounded/sanitized Playground resource boundary

### Question-bound test preparation — not RED

`5a38f3aa719e7359453749dd9a2c99837264a5a8` added the test-only `PlaygroundRestResource` question-bound contract. CI `34447357683` stopped in PHPCS before PHPUnit because of a test docblock callable-shape convention. This is explicitly **not** counted as RED.

### Genuine question-bound RED

`03185abaf040a333937b73fe9f8a38b7bb65352c` / CI `34447433278`:

- PHPCS and PHPStan passed;
- PHPUnit reached the new regression;
- 694 tests / 2,938 assertions;
- exactly 1 failure proving `PlaygroundRestResource` did not yet exist.

### Question-bound GREEN implementation

`d883c452615ce91b06ed1dda7e89f9c48921b681` introduced the smallest request-local resource boundary:

- mirrors the production `ChatRequest` 16,384-byte question ceiling;
- trims and rejects empty questions;
- rejects invalid input before the executor can run;
- delegates valid input only to its request-local executor seam.

### Safe-error test preparation — not RED

`c0af08627c4a3aa7e485cbe78cf2d6889031d53c` added retrieval/internal exception sentinel regressions, but CI `34447703276` stopped in PHPCS on two test-only short-ternary expressions. This is explicitly **not** counted as RED.

### Genuine safe-error RED

`ffad1ab31a98665d92dbed813e1479af63ac49fd` / CI `34447775577`:

- PHPCS and PHPStan passed;
- PHPUnit reached the two new exception regressions;
- both errored because exceptions escaped the resource boundary;
- the thrown messages contained the provider/internal sentinel strings, proving the boundary had no stable sanitization yet.

### Safe-error GREEN

`8900e407fad0dadcab2540b47091a808306672ef` adds the minimal typed failure normalization:

- `RetrievalException` -> repository-owned `retrieval_unavailable`;
- every other `Throwable` -> repository-owned `playground_failed`;
- raw provider/internal exception messages are never copied into the response;
- invalid/non-array executor output also becomes `playground_failed`.

Exact-head CI `34447914219` for `8900e407fad0dadcab2540b47091a808306672ef` is GREEN across all four permanent jobs:

- `php-quality`;
- `js-quality`;
- `package`;
- complete `wordpress-smoke`, including activation, database, provider, knowledge, file-ingestion, WooCommerce-knowledge, and environment cleanup steps.

## Security / correctness review of completed Task 6 subunits

- No raw provider payload, credential, query, or exception data is newly serialized.
- Observer input is the existing M10 `RetrievalResult`; final REST projection still must pass through the completed Task 5 `DebugTraceProjector`.
- Observation happens only after successful retrieval, so retrieval exceptions retain the existing M11-safe classification and the Playground resource maps them to `retrieval_unavailable`.
- Existing callers are unchanged because the observer dependency is nullable and defaults to `null`.
- Retrieval is not duplicated: the observer receives the same object used by downstream grounding/prompt/generation flow.
- The observer is request-local by contract; Task 6 must not store it globally or across requests.
- The Playground resource now rejects empty/oversized questions before execution and owns stable safe error envelopes rather than serializing throwable messages.
- The resource executor remains an internal request-local seam; production composition must return only an allow-listed typed/bounded result and must not pass arbitrary provider arrays through to HTTP unchanged.

This is a coordinator review, **not** the mandatory fresh-session independent Task 6 closeout review.

## Composition-root finding

The current plugin bootstrap registers provider, knowledge, job, and admin foundations, but there is no existing HTTP chat composition root that can simply be reused by the new admin playground route. Completing Task 6 therefore requires a small production runtime/composition seam that assembles existing M03/M10/M11 dependencies once for a request and allows the playground to attach its request-local retrieval observer. It must not create a second retrieval/provider implementation.

`AdminRestBootstrap` already establishes the correct WordPress pattern for protected administrator resources: request-local resource construction plus `AdminCapability::can_manage` permission callbacks. The Playground route should follow that pattern after the production chat executor/composition seam exists.

## Next unfinished work

1. Finish recovering the concrete M03/M10/M11 dependency factories/registries used to create providers, vector/lexical retrieval, grounding, prompt, memory, and citation services.
2. Establish the smallest request-local production chat composition/executor seam that can accept `ChatRetrievalObserver` without duplicating M10/M11 logic.
3. Define a typed/allow-listed Playground execution result so the HTTP resource never trusts arbitrary executor arrays as public output.
4. Under a new genuine RED, register protected `POST /admin/debug/playground` behind `AdminCapability::can_manage` and bind request JSON to the resource.
5. Execute one M11 request, capture its exact M10 result through the observer, project it with `DebugTraceProjector`, and combine only bounded/allow-listed `ChatResult` diagnostics.
6. Add REST registration/integration/smoke coverage.
7. Perform a genuinely fresh independent correctness/security/performance review, resolve all Critical/Important findings under RED -> GREEN, and require all four permanent jobs GREEN on the final Task 6 SHA before marking Task 6 COMPLETE or starting Task 7.

## Merge state

Task 6 remains **IN PROGRESS**. PR #18 must remain draft and must not merge until Tasks 6–8 and final M13 closeout are complete.
