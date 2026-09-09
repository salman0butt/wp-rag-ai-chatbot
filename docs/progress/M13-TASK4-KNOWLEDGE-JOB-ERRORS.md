# M13 Task 4 — Safe knowledge job mutation errors

Status: **COMPLETE bounded slice**. Task 4 remains active.

## Scope

This slice adds Knowledge-local, accessible, allow-listed feedback for enqueue/cancel/retry failures without changing the Task 3 server contract.

- `invalid_transition` maps to repository-owned copy: `The job state changed. Refresh and try the action again.`
- every other mutation failure maps to repository-owned generic copy: `The job action could not be completed. Try again.`
- the alert uses `role="alert"` and a stable `data-knowledge-job-error` code.
- arbitrary backend/provider messages are never retained or rendered; `AdminApiError` continues to expose only stable code/status to the UI.
- stale mutation feedback is cleared before a new mutation and when Knowledge state is reset/left.
- successful lifecycle mutations remain server-authoritative by refetching the bounded Task 3 job inventory.

## TDD evidence

### Genuine RED

Commit `a6f94b95fb8e25bc1706d7b51deaa8804eaa1dfa`, CI `34336846896`:

- engine/package checks passed;
- JavaScript lint passed;
- TypeScript passed;
- Jest executed 23 suites / 58 tests;
- 22 suites / 56 tests passed;
- exactly two tests failed in `src-js/knowledge-job-errors.test.ts` because the expected Knowledge-local safe alert did not exist.

The regression fixtures deliberately included secret/raw-provider sentinel text and required it to remain absent from rendered UI.

### GREEN

Implementation commit `da93b2aeb352e777d46a7aa51e983d64c5891abb` was produced by the established self-deleting verification-runner fallback after native patch transport returned transient 404/429 responses.

The runner first applied exact-count source replacements, formatted `src-js/index.ts` with repository rules, then ran the complete `npm run verify:js` gate before committing. Verification result:

- JavaScript lint: PASS;
- TypeScript: PASS;
- Jest: 23/23 suites, 58/58 tests PASS;
- build: PASS;
- Pinecone live-gating: PASS;
- Chroma live-gating: PASS.

The temporary workflow deleted itself in the verified implementation commit and is not part of the product tree.

## Scoped review

Coordinator correctness/security/accessibility/performance review: **0 Critical / 0 Important**.

- Correctness: both enqueue and cancel/retry mutation paths clear stale feedback, map errors consistently, and preserve bounded authoritative refresh on success.
- Security/privacy: only stable allow-listed client codes choose repository-owned copy; raw response messages/provider payloads are not retained or rendered.
- Accessibility: mutation feedback is exposed through `role="alert"`; existing lifecycle controls remain native form/button controls.
- Performance: no additional network call, unbounded state, polling, or optimistic cache is introduced.

Independent reviewer/subagent transport remained unavailable due transient MCP 404/429 responses, so no independent-review result is fabricated. Final Task 4 independent closeout review remains required before Task 4 completion.

## Continuation

Task 4 remains active. Next add explicit Knowledge loading/empty/error behavior plus constrained-width/long-content responsive and keyboard/accessibility coverage under fresh RED/GREEN cycles. Then complete final Task 4 correctness/security/accessibility/performance and independent closeout review and require exact-final-SHA permanent CI GREEN before Task 5.
