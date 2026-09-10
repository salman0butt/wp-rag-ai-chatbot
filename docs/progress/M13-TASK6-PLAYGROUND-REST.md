# M13 Task 6 — Playground REST Execution

Status: **IN PROGRESS — exact-retrieval observation seam complete; REST composition/resource pending**

## Goal

Add protected `POST /admin/debug/playground` execution that reuses the existing M10/M11 production retrieval/RAG path, projects the exact retrieval evidence through Task 5 `DebugTraceProjector`, and returns only bounded administrator-safe diagnostics.

## Architecture constraint recovered

`ChatOrchestrator` owns the M10 `HybridRetriever` call internally and returns only `ChatResult`. Running retrieval separately for the playground and then invoking `ChatOrchestrator` would execute retrieval/scoring/reranking twice and could make the debug trace disagree with the evidence used to generate the answer.

Task 6 therefore first adds a request-local optional `ChatRetrievalObserver` seam. `ChatOrchestrator` invokes it immediately after the one successful production retrieval and passes the exact `RetrievalResult` that continues into grounding, prompt construction, generation, and citation validation. Existing callers remain compatible because the dependency defaults to `null`.

## TDD evidence

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

## Security / correctness review of this subunit

- No raw provider payload, credential, query, or exception data is newly serialized.
- Observer input is the existing M10 `RetrievalResult`; REST projection still must pass through the completed Task 5 `DebugTraceProjector`.
- Observation happens only after successful retrieval, so retrieval exceptions retain the existing M11 safe `retrieval_unavailable` mapping.
- Existing callers are unchanged because the observer dependency is nullable and defaults to `null`.
- Retrieval is not duplicated: the observer receives the same object used by downstream grounding/prompt/generation flow.
- The observer is request-local by contract; Task 6 must not store it globally or across requests.

This is a coordinator review, **not** the mandatory fresh-session independent Task 6 closeout review.

## Composition-root finding

The current plugin bootstrap registers provider, knowledge, job, and admin foundations, but there is no existing HTTP chat composition root that can simply be reused by the new admin playground route. Completing Task 6 therefore requires a small production runtime/composition seam that assembles existing M03/M10/M11 dependencies once for a request and allows the playground to attach its request-local retrieval observer. It must not create a second retrieval/provider implementation.

## Next unfinished work

1. Recover the concrete M03/M10/M11 dependency factories/registries used to create providers, vector/lexical retrieval, grounding, prompt, memory, and citation services.
2. Establish the smallest request-local production chat composition/executor seam that can accept `ChatRetrievalObserver` without duplicating M10/M11 logic.
3. Under a new genuine RED, add `PlaygroundRestResource` with `AdminCapability::can_manage`, bounded question plus explicit existing bot/retrieval configuration identifiers, and stable safe error mapping.
4. Execute one M11 request, capture its exact M10 result through the observer, project it with `DebugTraceProjector`, and combine only bounded/allow-listed `ChatResult` diagnostics.
5. Add REST registration/integration/smoke coverage.
6. Perform a genuinely fresh independent correctness/security/performance review, resolve all Critical/Important findings under RED → GREEN, and require all four permanent jobs GREEN on the final Task 6 SHA before marking Task 6 COMPLETE or starting Task 7.

## Merge state

Task 6 remains **IN PROGRESS**. PR #18 must remain draft and must not merge until Tasks 6–8 and final M13 closeout are complete.
